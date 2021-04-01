<?php
/**
 * TurnTo_SocialCommerce
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright  Copyright (c) 2018 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Model\Manager\Export;

use Magento\Catalog\Helper\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactoryAlias;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use TurnTo\SocialCommerce\Helper\Config;
use TurnTo\SocialCommerce\Helper\Product as TurnToProductHelper;
use TurnTo\SocialCommerce\Logger\Monolog;

class CancelledOrders extends Orders
{
    const CANCELED_FEED_NAME = 'canceled-orders-feed.tsv';
    const FEED_STYLE = 'cancelled-order.txt';

    public function __construct(
        \TurnTo\SocialCommerce\Helper\Config $config,
        \TurnTo\SocialCommerce\Logger\Monolog $logger,
        \Magento\Sales\Api\ShipmentRepositoryInterface $shipmentsService,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Catalog\Helper\Product $productHelper,
        DirectoryList $directoryList,
        \TurnTo\SocialCommerce\Helper\Product $turnToProductHelper,
        \Magento\Framework\Filesystem\Io\File $fileSystem,
        OrderCollectionFactoryAlias $orderCollection,
        \TurnTo\SocialCommerce\Helper\Export\Order $orderExportHelper
    ) {
        parent::__construct(
            $config,
            $logger,
            $shipmentsService,
            $productRepository,
            $productHelper,
            $directoryList,
            $turnToProductHelper,
            $fileSystem,
            $orderCollection,
            $orderExportHelper
        );
    }

    /**
     * @param           $storeId
     * @param \DateTime $fromDate
     * @param \DateTime $toDate
     * @param bool      $forceIncludeAllItems
     *
     * @return bool|string|null
     */
    public function getCanceledOrdersFeed(
        $storeId,
        $cancelledOrders,
        $forceIncludeAllItems = false
    ) {
        $csvData = null;
        try {
            $this->fileSystem->checkAndCreateFolder($this->directoryList->getPath(DirectoryList::TMP));

            $outputFile = $this->directoryList->getPath(DirectoryList::TMP) . '/' . self::CANCELED_FEED_NAME;
            $outputHandle = fopen($outputFile, 'w+');
            fputcsv(
                $outputHandle,
                [
                    'ORDERID',
                    'SKU'
                ],
                "\t"
            );
            $this->writeOrdersToFeed($outputHandle, $cancelledOrders, $forceIncludeAllItems);
            rewind($outputHandle);
            $csvData = stream_get_contents($outputHandle);
        } catch (\Exception $e) {
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
     * @param $storeId
     * @param $fromDate
     * @param $toDate
     *
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection
     */
    public function getCanceledOrders($storeId, $fromDate, $toDate)
    {
        return $this->orderCollectionFactory->create()
            ->addAttributeToFilter('status', ['eq' => 'canceled'])
            ->addAttributeToFilter(self::STORE_ID_FIELD_ID, ['eq' => $storeId])
            ->addAttributeToFilter(self::UPDATED_AT_FIELD_ID, ['gteq' => $fromDate->format(DATE_ATOM)])
            ->addAttributeToFilter(self::UPDATED_AT_FIELD_ID, ['lteq' => $toDate->format(DATE_ATOM)]);
    }

    /**
     * @param      $outputHandle
     * @param      $orders
     * @param bool $forceIncludeAllItems
     *
     * @return int|void
     */
    protected function writeOrdersToFeed($outputHandle, $orders, $forceIncludeAllItems)
    {
        foreach ($orders as $order) {
            try {
                $items = $this->getItemData($order, $forceIncludeAllItems);
                foreach ($items as $item) {
                    $row = [];
                    $lineItem = $item[self::LINE_ITEM_FIELD_ID];
                    $product = $item[self::PRODUCT_FIELD_ID];
                    $sku = $this->config->getUseChildSku($order->getStoreId()) ? $lineItem->getSku() : $product->getSku();

                    $row[] = $order->getIncrementId();
                    $row[] = $this->turnToProductHelper->turnToSafeEncoding($sku);

                    fputcsv($outputHandle, $row, "\t");
                }
            } catch (\Exception $e) {
                $this->logger->error(
                    'An error occurred while writing order data to the canceled orders feed',
                    [
                        'exception' => $e,
                    ]
                );
            }
        }
    }

    /**
     * @param                                        $feedData
     * @param \Magento\Store\Api\Data\StoreInterface $store
     *
     * @throws \Exception
     */
    public function transmitFeed($feedData, \Magento\Store\Api\Data\StoreInterface $store)
    {
        $response = null;

        try {
            $zendClient = new \Magento\Framework\HTTP\ZendClient();
            $zendClient->setUri(
                $this->config->getFeedUploadAddress($store->getCode())
            )->setMethod(\Zend_Http_Client::POST)->setParameterPost(
                [
                    'siteKey' => $this->config->getSiteKey($store->getCode()),
                    'authKey' => $this->config->getAuthorizationKey($store->getCode()),
                    'feedStyle' => self::FEED_STYLE
                ]
            )->setFileUpload(self::CANCELED_FEED_NAME, 'file', $feedData, self::FEED_MIME);

            $response = $zendClient->request();

            if (!$response || !$response->isSuccessful()) {
                throw new \Exception('TurnTo order canceled feed submission failed silently');
            }

            $body = $response->getBody();

            //It is possible to get a status 200 message who's body is an error message from TurnTo
            if (empty($body) || $body != Catalog::TURNTO_SUCCESS_RESPONSE) {
                throw new \Exception("TurnTo canceled order feed submission failed with message: $body");
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'An error occurred while transmitting the canceled order feed to TurnTo',
                [
                    'exception' => $e,
                    'response' => $response ? $response->getBody() : 'null'
                ]
            );
            throw $e;
        }
    }
}
