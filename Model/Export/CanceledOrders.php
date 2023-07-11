<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Export;

use DateTime;
use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Helper\Config;
use TurnTo\SocialCommerce\Helper\Product as TurnToProductHelper;
use TurnTo\SocialCommerce\Logger\Monolog;

class CanceledOrders
{
    const FEED_NAME = 'canceled-orders-feed.tsv';
    const FEED_STYLE = 'cancelled-order.txt';
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
     * @var TurnToProductHelper
     */
    protected $turnToProductHelper;
    /**
     * @var DirectoryList
     */
    protected $directoryList;
    /**
     * @var FeedClient
     */
    protected $feedClient;
    /**
     * @var File
     */
    protected $fileSystem;
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
     * @param TurnToProductHelper $turnToProductHelper
     * @param DirectoryList $directoryList
     * @param FeedClient $feedClient
     * @param File $fileSystem
     * @param Orders $ordersExport
     * @param OrderCollectionFactory $orderCollectionFactory
     */
    public function __construct(
        Config                      $config,
        Monolog                     $logger,
        DateTimeFactory             $dateTimeFactory,
        StoreManagerInterface       $storeManager,
        TurnToProductHelper         $turnToProductHelper,
        DirectoryList               $directoryList,
        FeedClient                  $feedClient,
        File                        $fileSystem,
        Orders                      $ordersExport,
        OrderCollectionFactory $orderCollectionFactory
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->storeManager = $storeManager;
        $this->turnToProductHelper = $turnToProductHelper;
        $this->directoryList = $directoryList;
        $this->feedClient = $feedClient;
        $this->fileSystem = $fileSystem;
        $this->ordersExport = $ordersExport;
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * @param           $storeId
     * @param DateTime $fromDate
     * @param DateTime $toDate
     * @param bool $forceIncludeAllItems
     *
     * @return bool|string|null
     */
    public function getCanceledOrdersFeed(
        $storeId,
        DateTime $fromDate,
        DateTime $toDate,
        bool $forceIncludeAllItems = false
    ) {
        $csvData = null;
        $canceledOrders = $this->getCanceledOrders($storeId, $fromDate, $toDate);

        try {
            $this->fileSystem->checkAndCreateFolder($this->directoryList->getPath(DirectoryList::TMP));

            $outputFile = $this->directoryList->getPath(DirectoryList::TMP) . '/' . self::FEED_NAME;
            $outputHandle = fopen($outputFile, 'w+');
            fputcsv(
                $outputHandle,
                [
                    'ORDERID',
                    'SKU'
                ],
                "\t"
            );
            $this->writeOrdersToFeed($outputHandle, $canceledOrders, $forceIncludeAllItems);
            rewind($outputHandle);
            $csvData = stream_get_contents($outputHandle);
        } catch (Exception $e) {
            $this->logger->error(
                'An error occurred while creating or writing to the Historical Orders Feed export file',
                [
                    'storeId' => $storeId,
                    'exception' => $e
                ]
            );
        } finally {
            if (isset($outputHandle)) {
                fclose($outputHandle);
            }
        }

        return $csvData;
    }

    /**
     * CRON handler that sends the last 2 days of orders to TurnTo
     * @return void
     */
    public function cronUploadFeed()
    {
        foreach ($this->storeManager->getStores() as $store) {
            if ($this->config->getIsEnabled($store->getCode()) && $this->config->getIsCancelledOrdersFeedEnabled(
                $store->getCode()
            )) {
                try {
                    $feedData = $this->getCanceledOrdersFeed(
                        $store->getId(),
                        $this->dateTimeFactory->create('now', new \DateTimeZone('UTC'))->sub(new \DateInterval('P80D')),
                        $this->dateTimeFactory->create('now', new \DateTimeZone('UTC'))
                    );
                    $this->feedClient->transmitFeedFile($feedData, self::FEED_NAME, self::FEED_STYLE, $store->getCode());
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
            ->addAttributeToFilter(Orders::UPDATED_AT_FIELD_ID, ['gteq' => $fromDate->format(DATE_ATOM)])
            ->addAttributeToFilter(Orders::UPDATED_AT_FIELD_ID, ['lteq' => $toDate->format(DATE_ATOM)]);
    }

    /**
     * @param      $outputHandle
     * @param      $orders
     * @param bool $forceIncludeAllItems
     *
     * @return void
     */
    protected function writeOrdersToFeed($outputHandle, $orders, $forceIncludeAllItems)
    {
        foreach ($orders as $order) {
            try {
                $items = $this->ordersExport->getItemData($order, $forceIncludeAllItems);
                foreach ($items as $item) {
                    $row = [];
                    $lineItem = $item[Orders::LINE_ITEM_FIELD_ID];
                    $product = $item[Orders::PRODUCT_FIELD_ID];
                    $sku = $this->config->getUseChildSku($order->getStoreId()) ? $lineItem->getSku() : $product->getSku();

                    $row[] = $order->getIncrementId();
                    $row[] = $this->turnToProductHelper->turnToSafeEncoding($sku);

                    fputcsv($outputHandle, $row, "\t");
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
