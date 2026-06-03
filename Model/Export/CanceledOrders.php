<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Export;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\File\WriteInterface;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Product;
use TurnTo\SocialCommerce\Logger\Monolog;

class CanceledOrders
{
    const FEED_NAME = 'canceled-orders-feed.tsv';
    const FEED_STYLE = 'cancelled-order.txt';
    protected const LOOKBACK_INTERVAL = 'P2D';
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var Monolog
     */
    protected $logger;
    /**
     * @var DateTimeFactory
     */
    protected $dateTimeFactory;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var Product
     */
    protected $product;
    /**
     * @var FeedClient
     */
    protected $feedClient;
    /**
     * @var Filesystem
     */
    protected $filesystem;
    /**
     * @var Orders
     */
    protected $ordersExport;
    /**
     * @var OrderCollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * CanceledOrders constructor.
     *
     * @param Config $config
     * @param Monolog $logger
     * @param DateTimeFactory $dateTimeFactory
     * @param StoreManagerInterface $storeManager
     * @param Product $product
     * @param FeedClient $feedClient
     * @param Filesystem $filesystem
     * @param Orders $ordersExport
     * @param OrderCollectionFactory $orderCollectionFactory
     */
    public function __construct(
        Config $config,
        Monolog $logger,
        DateTimeFactory $dateTimeFactory,
        StoreManagerInterface $storeManager,
        Product $product,
        FeedClient $feedClient,
        Filesystem $filesystem,
        Orders $ordersExport,
        OrderCollectionFactory $orderCollectionFactory
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->storeManager = $storeManager;
        $this->product = $product;
        $this->feedClient = $feedClient;
        $this->filesystem = $filesystem;
        $this->ordersExport = $ordersExport;
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * @param           $storeId
     * @param DateTime $fromDate
     * @param DateTime $toDate
     * @param bool $forceIncludeAllItems
     * @return string
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function getCanceledOrdersFeed(
        $storeId,
        DateTime $fromDate,
        DateTime $toDate,
        bool $forceIncludeAllItems = false
    ): string {
        $canceledOrders = $this->getCanceledOrders($storeId, $fromDate, $toDate);

        $tmpDir = $this->filesystem->getDirectoryWrite(DirectoryList::TMP);
        $tmpDir->create();
        $fileName = 'turnto_canceled_orders_feed_' . uniqid('', true) . '.tsv';
        $outputFile = $tmpDir->openFile($fileName, 'w+');

        try {
            $outputFile->writeCsv(
                [
                    'ORDERID',
                    'SKU'
                ],
                "\t",
                '"'
            );
            $this->writeOrdersToFeed($outputFile, $canceledOrders, $forceIncludeAllItems);
        } finally {
            $outputFile->close();
        }

        $absolutePath = $tmpDir->getAbsolutePath($fileName);
        if (!is_readable($absolutePath) || filesize($absolutePath) === 0) {
            if (is_file($absolutePath)) {
                unlink($absolutePath);
            }
            throw new LocalizedException(__('Invalid CSV data'));
        }

        return $absolutePath;
    }

    /**
     * CRON handler that sends the last 2 days of orders to TurnTo
     * @return void
     */
    public function cronUploadFeed()
    {
        foreach ($this->storeManager->getStores() as $store) {
            if ($this->config->getIsEnabled($store->getCode()) &&
                $this->config->getConfigBool(Config::ORDER_ENABLE_CANCELLED_FEED, $store->getCode())
            ) {
                $feedPath = null;
                try {
                    $feedPath = $this->getCanceledOrdersFeed(
                        $store->getId(),
                        $this->dateTimeFactory->create('now', new DateTimeZone('UTC'))
                            ->sub(new DateInterval(static::LOOKBACK_INTERVAL)),
                        $this->dateTimeFactory->create('now', new DateTimeZone('UTC'))
                    );
                    $this->feedClient->transmitFeedFile(
                        $feedPath,
                        self::FEED_NAME,
                        self::FEED_STYLE,
                        $store->getCode(),
                        true
                    );
                } catch (Exception $e) {
                    $this->logger->error(
                        'An error occurred while processing or transmitting canceled Orders Feed Cron',
                        [
                            'storeId' => $store->getId(),
                            'exception' => $e
                        ]
                    );
                } finally {
                    if (is_string($feedPath) && $feedPath !== '' && is_file($feedPath)) {
                        unlink($feedPath);
                    }
                }
            }
        }
    }

    /**
     * @param $storeId
     * @param $fromDate
     * @param $toDate
     *
     * @return Collection
     */
    protected function getCanceledOrders($storeId, $fromDate, $toDate)
    {
        return $this->orderCollectionFactory->create()
            ->addAttributeToFilter('status', ['eq' => 'canceled'])
            ->addAttributeToFilter(Orders::STORE_ID_FIELD_ID, ['eq' => $storeId])
            ->addAttributeToFilter(Orders::UPDATED_AT_FIELD_ID, ['gteq' => $fromDate->format('Y-m-d H:i:s')])
            ->addAttributeToFilter(Orders::UPDATED_AT_FIELD_ID, ['lteq' => $toDate->format('Y-m-d H:i:s')]);
    }

    /**
     * @param WriteInterface $outputFile
     * @param      $orders
     * @param bool $forceIncludeAllItems
     *
     * @return void
     */
    protected function writeOrdersToFeed(WriteInterface $outputFile, $orders, $forceIncludeAllItems)
    {
        if ($orders->getSize() === 0) {
            return;
        }

        $orderIds = [];
        $productIds = [];
        foreach ($orders as $order) {
            $orderIds[] = (int) $order->getEntityId();
            foreach ($order->getItems() as $item) {
                if ($item->isDeleted() || $item->getParentItemId()) {
                    continue;
                }
                $pid = (int) $item->getProductId();
                if ($pid > 0) {
                    $productIds[] = $pid;
                }
            }
        }
        $storeId = (int) $orders->getFirstItem()->getStoreId();
        $shipmentsByOrderId = $this->ordersExport->getShipmentsIndexedByOrderId($orderIds, $storeId);
        // Preload products for the whole batch once instead of loading per order.
        $productsById = $this->ordersExport->getProductsIndexedById($productIds);

        foreach ($orders as $order) {
            try {
                $oid = (int) $order->getEntityId();
                $orderShipments = $shipmentsByOrderId[$oid] ?? [];
                $items = $this->ordersExport->getItemData($order, $forceIncludeAllItems, $orderShipments, $productsById);
                foreach ($items as $item) {
                    $row = [];
                    $lineItem = $item[Orders::LINE_ITEM_FIELD_ID];
                    $product = $item[Orders::PRODUCT_FIELD_ID];
                    $sku = $this->config->getUseChildSku($order->getStoreId()) ? $lineItem->getSku() : $product->getSku();

                    $row[] = $order->getIncrementId();
                    $row[] = $this->product->turnToSafeEncoding($sku);

                    $outputFile->writeCsv($row, "\t", '"');
                }
            } catch (Exception $e) {
                $this->logger->error(
                    'An error occurred while writing order data to the canceled orders feed',
                    [
                        'exception' => $e,
                    ]
                );
            }
        }
    }
}
