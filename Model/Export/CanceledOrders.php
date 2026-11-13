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
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\File\WriteInterface;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Product;
use TurnTo\SocialCommerce\Logger\Monolog;

class CanceledOrders
{
    public const FEED_NAME = 'canceled-orders-feed.tsv';
    public const FEED_STYLE = 'cancelled-order.txt';
    protected const LOOKBACK_INTERVAL = 'P2D';
    protected const FEED_FILE_PREFIX = 'turnto_canceled_orders_feed_';
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
     * @var Orders
     */
    protected $ordersExport;
    /**
     * @var OrderCollectionFactory
     */
    protected $orderCollectionFactory;
    /**
     * @var TemporaryFeedFile
     */
    protected $temporaryFeedFile;

    /**
     * CanceledOrders constructor.
     *
     * @param Config $config
     * @param Monolog $logger
     * @param DateTimeFactory $dateTimeFactory
     * @param StoreManagerInterface $storeManager
     * @param Product $product
     * @param Orders $ordersExport
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param TemporaryFeedFile $temporaryFeedFile
     */
    public function __construct(
        Config $config,
        Monolog $logger,
        DateTimeFactory $dateTimeFactory,
        StoreManagerInterface $storeManager,
        Product $product,
        Orders $ordersExport,
        OrderCollectionFactory $orderCollectionFactory,
        TemporaryFeedFile $temporaryFeedFile
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->storeManager = $storeManager;
        $this->product = $product;
        $this->ordersExport = $ordersExport;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->temporaryFeedFile = $temporaryFeedFile;
    }

    /**
     * Build the canceled orders feed and return its TSV contents
     *
     * Prefer createCanceledOrdersFeedFile() for large exports; this loads the whole feed into memory.
     *
     * @param int|string $storeId
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
        return $this->temporaryFeedFile->readAndDelete(
            $this->createCanceledOrdersFeedFile($storeId, $fromDate, $toDate, $forceIncludeAllItems)
        );
    }

    /**
     * Build the canceled orders feed as a TSV file in var/tmp and return its absolute path
     *
     * @param int|string $storeId
     * @param DateTime $fromDate
     * @param DateTime $toDate
     * @param bool $forceIncludeAllItems
     * @return string
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function createCanceledOrdersFeedFile(
        $storeId,
        DateTime $fromDate,
        DateTime $toDate,
        bool $forceIncludeAllItems = false
    ): string {
        $canceledOrders = $this->getCanceledOrders($storeId, $fromDate, $toDate);

        return $this->temporaryFeedFile->create(
            self::FEED_FILE_PREFIX,
            function (WriteInterface $outputFile) use ($canceledOrders, $forceIncludeAllItems) {
                $outputFile->writeCsv(
                    [
                        'ORDERID',
                        'SKU'
                    ],
                    "\t",
                    '"'
                );
                $this->writeOrdersToFeed($outputFile, $canceledOrders, $forceIncludeAllItems);
            }
        );
    }

    /**
     * CRON handler that sends the last 2 days of canceled orders to TurnTo
     *
     * Canceled items are rarely shipped, so every item is sent regardless of the delivery date settings.
     *
     * @return void
     */
    public function cronUploadFeed()
    {
        foreach ($this->storeManager->getStores() as $store) {
            if ($this->config->getIsEnabled($store->getCode()) &&
                $this->config->getConfigBool(Config::ORDER_ENABLE_CANCELLED_FEED, $store->getCode())
            ) {
                try {
                    $feedPath = $this->createCanceledOrdersFeedFile(
                        $store->getId(),
                        $this->dateTimeFactory->create('now', new DateTimeZone('UTC'))
                            ->sub(new DateInterval(static::LOOKBACK_INTERVAL)),
                        $this->dateTimeFactory->create('now', new DateTimeZone('UTC')),
                        true
                    );
                    $this->temporaryFeedFile->transmitAndDelete(
                        $feedPath,
                        self::FEED_NAME,
                        self::FEED_STYLE,
                        $store->getCode()
                    );
                } catch (Exception $e) {
                    $this->logger->error(
                        'An error occurred while processing or transmitting canceled Orders Feed Cron',
                        [
                            'storeId' => $store->getId(),
                            'exception' => $e
                        ]
                    );
                }
            }
        }
    }

    /**
     * Create collection of canceled orders
     *
     * @param int|string $storeId
     * @param DateTime $fromDate
     * @param DateTime $toDate
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
     * Write orders to file
     *
     * @param WriteInterface $outputFile
     * @param Collection $orders
     * @param bool $forceIncludeAllItems
     *
     * @return void
     */
    protected function writeOrdersToFeed(WriteInterface $outputFile, $orders, $forceIncludeAllItems)
    {
        $itemDataByOrderId = $this->ordersExport->getItemDataForOrders($orders, $forceIncludeAllItems);

        foreach ($orders as $order) {
            $orderId = (int) $order->getEntityId();
            try {
                foreach ($itemDataByOrderId[$orderId] ?? [] as $item) {
                    $sku = $this->ordersExport->getFeedSku(
                        $item[Orders::LINE_ITEM_FIELD_ID],
                        $item[Orders::PRODUCT_FIELD_ID],
                        $order->getStoreId(),
                        $item[Orders::ORDERED_PRODUCT_FIELD_ID] ?? null
                    );

                    $outputFile->writeCsv(
                        [$order->getIncrementId(), $this->product->turnToSafeEncoding($sku)],
                        "\t",
                        '"'
                    );
                }
            } catch (Exception $e) {
                $this->logger->error(
                    'An error occurred while writing order data to the canceled orders feed',
                    [
                        'order_id' => $orderId,
                        'store_id' => (int) $order->getStoreId(),
                        'exception' => $e,
                    ]
                );
            }
        }
    }
}
