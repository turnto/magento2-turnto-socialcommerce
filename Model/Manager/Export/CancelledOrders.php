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

class CancelledOrders
{
    const CANCELED_FEED_NAME = 'canceled-orders-feed.tsv';
    const FEED_STYLE = 'cancelled-order.txt';

    /**
     * @var \TurnTo\SocialCommerce\Helper\Config
     */
    protected $config;

    /**
     * @var \TurnTo\SocialCommerce\Logger\Monolog
     */
    protected $logger;

    /**
     * @var \Magento\Sales\Api\ShipmentRepositoryInterface
     */
    protected $shipmentsService;

    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    protected $productRepository;

    /**
     * @var \Magento\Catalog\Helper\Product
     */
    protected $productHelper;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @var \TurnTo\SocialCommerce\Helper\Product
     */
    protected $turnToProductHelper;

    /**
     * @var \Magento\Framework\Filesystem\Io\File
     */
    protected $fileSystem;

    /**
     * @var OrderCollectionFactoryAlias
     */
    protected $orderCollectionFactory;

    /**
     * @var
     */
    protected $orderExportHelper;

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
        $this->config = $config;
        $this->logger = $logger;
        $this->shipmentsService = $shipmentsService;
        $this->productRepository = $productRepository;
        $this->productHelper = $productHelper;
        $this->directoryList = $directoryList;
        $this->turnToProductHelper = $turnToProductHelper;
        $this->fileSystem = $fileSystem;
        $this->orderCollectionFactory = $orderCollection;
        $this->orderExportHelper = $orderExportHelper;

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
        $cancelledOrderData,
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
            $this->writeOrdersToFeed($outputHandle, $cancelledOrderData, $forceIncludeAllItems);
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
            ->addAttributeToFilter('store_id', ['eq' => $storeId])
            ->addAttributeToFilter('updated_at', ['gteq' => $fromDate->format(DATE_ATOM)])
            ->addAttributeToFilter('updated_at', ['lteq' => $toDate->format(DATE_ATOM)]);
    }

    public function formatCancelledOrderData($cancelledOrders) {
        $cancelledOrderData = [];

        foreach($cancelledOrders as $cancelledOrder) {

            foreach($cancelledOrder as $cancelledItem) {
                $product = $this->productRepository->getById($cancelledItem->getProductId());
                $productSku = $this->config->getUseChildSku($cancelledOrder->getStoreId()) ? $cancelledItem->getSku() : $product->getSku();

                $cancelledOrderData[] = [
                    "ORDERID" => $cancelledOrder->getIncrementId(),
                    "SKU" => $productSku
                ];
            }
        }

        return $cancelledOrderData;
    }

    /**
     * @param      $outputHandle
     * @param      $orders
     * @param bool $forceIncludeAllItems
     *
     * @return int|void
     */
    protected function writeOrdersToFeed($outputHandle, $cancelledOrderData, $forceIncludeAllItems)
    {
        foreach ($cancelledOrderData as $cancelledOrderDatum) {
            try {

                foreach ($cancelledOrderDatum as $cancelledItem) {
                    $row = [];

                    $row[] = $cancelledItem["ORDERID"];
                    $row[] = $cancelledItem["SKU"];

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
