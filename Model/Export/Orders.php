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

namespace TurnTo\SocialCommerce\Model\Export;

use Magento\Catalog\Helper\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use TurnTo\SocialCommerce\Helper\Config;
use TurnTo\SocialCommerce\Helper\Product as TurnToProductHelper;
use TurnTo\SocialCommerce\Logger\Monolog;

class Orders extends AbstractExport
{
    /**#@+
     * Field Id keys
     */
    const UPDATED_AT_FIELD_ID = 'updated_at';

    const STORE_ID_FIELD_ID = 'store_id';

    const ORDER_ID_FIELD_ID = 'order_id';

    const PRODUCT_FIELD_ID = 'product';

    const SHIP_DATE_FIELD_ID = 'shipDate';

    const LINE_ITEM_FIELD_ID = 'lineItem';
    /**#@-*/

    /**#@+
     * TurnTo Transmission Constants
     */
    const FEED_NAME = 'historical-orders-feed.tsv';

    const FEED_STYLE = 'tab-style.1';

    const FEED_MIME = 'text/tab-separated-values';
    /**#@-*/

    /**
     * Path to temp file used for writing, maximum of 16MB is used as in memory buffer
     */
    const TEMP_FILE_PATH = 'php://temp/maxmemory:16384';

    /**
     * @var OrderRepositoryInterface|null
     */
    protected $orderService = null;

    /**
     * @var ShipmentRepositoryInterface|null
     */
    protected $shipmentsService = null;

    /**
     * @var ProductRepository|null
     */
    protected $productRepository = null;

    /**
     * @var Product|null
     */
    protected $productHelper = null;

    /**
     * @var DirectoryList|null
     */
    protected $directoryList = null;
    /**
     * @var Product
     */
    protected $turnToProductHelper;

    /**
     * Orders constructor.
     *
     * @param Config $config
     * @param CollectionFactory $productCollectionFactory
     * @param Monolog $logger
     * @param DateTimeFactory $dateTimeFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param UrlFinderInterface $urlFinder
     * @param StoreManagerInterface $storeManager
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param ShipmentRepositoryInterface $shipmentsService
     * @param ProductRepository $productRepository
     * @param Product $productHelper
     * @param DirectoryList $directoryList
     * @param TurnToProductHelper $turnToProductHelper
     */
    public function __construct(
        Config $config,
        CollectionFactory $productCollectionFactory,
        Monolog $logger,
        DateTimeFactory $dateTimeFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        SortOrderBuilder $sortOrderBuilder,
        UrlFinderInterface $urlFinder,
        StoreManagerInterface $storeManager,
        OrderRepositoryInterface $orderRepositoryInterface,
        ShipmentRepositoryInterface $shipmentsService,
        ProductRepository $productRepository,
        Product $productHelper,
        DirectoryList $directoryList,
        TurnToProductHelper $turnToProductHelper
    )
    {
        parent::__construct(
            $config,
            $productCollectionFactory,
            $logger,
            $dateTimeFactory,
            $searchCriteriaBuilder,
            $filterBuilder,
            $sortOrderBuilder,
            $urlFinder,
            $storeManager
        );

        $this->orderService = $orderRepositoryInterface;
        $this->shipmentsService = $shipmentsService;
        $this->productRepository = $productRepository;
        $this->productHelper = $productHelper;
        $this->storeManager = $storeManager;
        $this->directoryList = $directoryList;
        $this->turnToProductHelper = $turnToProductHelper;
    }

    /**
     * CRON handler that sends the last 2 days of orders to TurnTo
     */
    public function cronUploadFeed()
    {
        foreach ($this->storeManager->getStores() as $store) {
            if ($this->config->getIsEnabled($store->getCode())
                && $this->config->getIsHistoricalOrdersFeedEnabled($store->getCode())
            ) {
                try {
                    $feedData = $this->getOrdersFeed(
                        $store->getId(),
                        $this->dateTimeFactory->create('now', new \DateTimeZone('UTC'))->sub(new \DateInterval('P2D')),
                        $this->dateTimeFactory->create('now', new \DateTimeZone('UTC'))
                    );
                    $this->transmitFeed($feedData, $store);
                } catch (\Exception $e) {
                    $this->logger->error(
                        'An error occurred while processing Historical Orders Feed Cron',
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
     * @param           $storeId
     * @param \DateTime $fromDate
     * @param \DateTime $toDate
     * @param bool $forceIncludeAllItems
     *
     * @return null|string
     */
    public function getOrdersFeed($storeId, \DateTime $fromDate, \DateTime $toDate, $forceIncludeAllItems = false)
    {
        $csvData = null;
        $searchCriteria = $this->getOrdersSearchCriteria($storeId, $fromDate, $toDate);

        try {
            $outputFile = $this->directoryList->getPath(DirectoryList::TMP) . '/tuntoexport.csv';
            $outputHandle = fopen($outputFile, 'w+');
            fputcsv(
                $outputHandle,
                [
                    'ORDERID',
                    'ORDERDATE',
                    'EMAIL',
                    'ITEMTITLE',
                    'ITEMURL',
                    'ITEMLINEID',
                    'ZIP',
                    'FIRSTNAME',
                    'LASTNAME',
                    'SKU',
                    'PRICE',
                    'ITEMIMAGEURL',
                    'DELIVERYDATE'
                ], "\t"
            );
            $this->writeOrdersFeed($searchCriteria, $outputHandle, $forceIncludeAllItems);
            rewind($outputHandle);
            $csvData = stream_get_contents($outputHandle);
        } catch (\Exception $e) {
            $this->logger->error(
                'An error occurred while processing Historical Orders Feed Cron',
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
     * @param           $storeId
     * @param \DateTime $fromDate
     * @param \DateTime $toDate
     *
     * @return \Magento\Framework\Api\SearchCriteria
     */
    public function getOrdersSearchCriteria($storeId, \DateTime $fromDate, \DateTime $toDate)
    {
        return $this->getSearchCriteria(
            $this->getSortOrder(self::UPDATED_AT_FIELD_ID),
            [
                $this->getFilter(self::STORE_ID_FIELD_ID, $storeId, 'eq'),
                $this->getFilter(self::UPDATED_AT_FIELD_ID, $fromDate->format(DATE_ATOM), 'gteq'),
                $this->getFilter(self::UPDATED_AT_FIELD_ID, $toDate->format(DATE_ATOM), 'lteq')
            ]
        );
    }

    /**
     * @param                                        $feedData
     * @param \Magento\Store\Api\Data\StoreInterface $store
     *
     * @throws \Exception
     */
    protected function transmitFeed($feedData, \Magento\Store\Api\Data\StoreInterface $store)
    {
        $response = null;

        try {
            $zendClient = new \Magento\Framework\HTTP\ZendClient;
            $zendClient
                ->setUri($this->config
                    ->getFeedUploadAddress($store->getCode()))
                ->setMethod(\Zend_Http_Client::POST)
                ->setParameterPost(
                    [
                        'siteKey' => $this->config->getSiteKey($store->getCode()),
                        'authKey' => $this->config->getAuthorizationKey($store->getCode()),
                        'feedStyle' => self::FEED_STYLE
                    ]
                )
                ->setFileUpload(self::FEED_NAME, 'file', $feedData, self::FEED_MIME);

            $response = $zendClient->request();

            if (!$response || !$response->isSuccessful()) {
                throw new \Exception('TurnTo order feed submission failed silently');
            }

            $body = $response->getBody();

            //It is possible to get a status 200 message who's body is an error message from TurnTo
            if (empty($body) || $body != Catalog::TURNTO_SUCCESS_RESPONSE) {
                throw new \Exception("TurnTo order feed submission failed with message: $body");
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'An error occurred while transmitting the order feed to TurnTo',
                [
                    'exception' => $e,
                    'response' => $response ? $response->getBody() : 'null'
                ]
            );
            throw $e;
        }
    }

    /**
     * @param \Magento\Framework\Api\SearchCriteria $searchCriteria
     * @param                                       $outputHandle
     * @param bool $forceIncludeAllItems
     */
    public function writeOrdersFeed(\Magento\Framework\Api\SearchCriteria $searchCriteria, $outputHandle, $forceIncludeAllItems)
    {
        $orderList = $this->orderService->getList($searchCriteria);

        $pageLimit = $orderList->getLastPageNumber();
        $pageSize = $orderList->getPageSize();
        for ($i = 1; $i <= $pageLimit; $i++) {
            $paginatedCollection = clone $orderList;
            $paginatedCollection->clear();
            $paginatedCollection->setPageSize($pageSize)->setCurPage($i);
            $paginatedCollection->load();

            if ($paginatedCollection->count() > 0) {
                $this->writeOrdersToFeed($outputHandle, $paginatedCollection, $forceIncludeAllItems);
            }
        }
    }

    /**
     * @param      $outputHandle
     * @param      $orders
     * @param bool $forceIncludeAllItems
     *
     * @return int
     */
    protected function writeOrdersToFeed($outputHandle, $orders, $forceIncludeAllItems)
    {
        if (!isset($orders) || empty($orders)) {
            return 0;
        }

        $numberOfRecordsWritten = 0;
        foreach ($orders as $order) {
            try {
                $this->writeOrderToFeed($outputHandle, $order, $forceIncludeAllItems);
            } catch (\Exception $e) {
                $this->logger->error(
                    'An error occurred while writing the historical orders feed',
                    [
                        'exception' => $e,
                    ]
                );
            } finally {
                $numberOfRecordsWritten++;
            }
        }

        return $numberOfRecordsWritten;
    }

    /**
     * @param                                        $outputHandle
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param bool $forceIncludeAllItems
     */
    protected function writeOrderToFeed($outputHandle, \Magento\Sales\Api\Data\OrderInterface $order, $forceIncludeAllItems)
    {
        $items = $this->getItemData($order, $forceIncludeAllItems);
        if (empty($items)) {
            return;
        }

        $itemNumber = 0;
        foreach ($items as $item) {
            $this->writeLineToFeed(
                $outputHandle,
                $order,
                $item[self::LINE_ITEM_FIELD_ID],
                $item[self::PRODUCT_FIELD_ID],
                $itemNumber++,
                $item[self::SHIP_DATE_FIELD_ID]
            );
        }
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param bool $forceIncludeAllItems
     *
     * @return array|mixed
     */
    public function getItemData(\Magento\Sales\Api\Data\OrderInterface $order, $forceIncludeAllItems)
    {
        $items = [];
        $orderId = $order->getEntityId();

        foreach ($order->getItems() as $item) {
            try {
                if (!$item->isDeleted() && !$item->getParentItemId()) {
                    $itemId = $item->getItemId();
                    $key = "$orderId.$itemId";
                    $items[$key] = [
                        self::LINE_ITEM_FIELD_ID => $item,
                        self::PRODUCT_FIELD_ID => $this->productRepository->getById($item->getProductId()),
                        self::SHIP_DATE_FIELD_ID => ''
                    ];
                }
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                // Do nothing
            }
        }

        $items = $this->addShipDateToItemData($items, $orderId, $order->getStoreId());
        if (
            !$forceIncludeAllItems
            && $this->config->getExcludeItemsWithoutDeliveryDate($order->getStore()->getCode())
        ) {
            foreach ($items as $key => $item) {
                if (empty($item['shipDate'])) {
                    unset($items[$key]);
                }
            }
        }

        return $items;
    }

    /**
     * @param $itemData
     * @param $orderId
     * @param $storeId
     *
     * @return mixed
     */
    protected function addShipDateToItemData($itemData, $orderId, $storeId)
    {
        $searchCriteria = $this->getShipmentSearchCriteriaForOrder($orderId, $storeId);
        $shipmentsList = $this->shipmentsService->getList($searchCriteria);
        $pageLimit = $shipmentsList->getLastPageNumber();
        $pageSize = $shipmentsList->getPageSize();

        for ($i = 1; $i <= $pageLimit; $i++) {
            $paginatedCollection = clone $shipmentsList;
            $paginatedCollection->clear();
            $paginatedCollection->setPageSize($pageSize)->setCurPage($i);
            $paginatedCollection->load();

            if ($paginatedCollection->count() > 0) {
                foreach ($paginatedCollection->getItems() as $shipment) {
                    foreach ($shipment->getItems() as $shipmentItem) {
                        $itemId = $shipmentItem->getOrderItemId();
                        $key = "$orderId.$itemId";
                        if (isset($itemData[$key])) {
                            $itemData[$key][self::SHIP_DATE_FIELD_ID] = $shipment->getCreatedAt();
                        }
                    }
                }
            }
        }

        return $itemData;
    }

    /**
     * @param $orderId
     * @param $storeId
     *
     * @return \Magento\Framework\Api\SearchCriteria
     */
    public function getShipmentSearchCriteriaForOrder($orderId, $storeId)
    {
        return $this->getSearchCriteria(
            $this->getSortOrder(self::ORDER_ID_FIELD_ID),
            [
                $this->getFilter(self::STORE_ID_FIELD_ID, $storeId, 'eq'),
                $this->getFilter(self::ORDER_ID_FIELD_ID, $orderId, 'eq')
            ]
        );
    }

    /**
     * @param                                            $outputHandle
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param \Magento\Sales\Api\Data\OrderItemInterface $lineItem
     * @param \Magento\Catalog\Model\Product $product
     * @param                                            $lineItemNumber
     * @param                                            $shipmentDate
     */
    protected function writeLineToFeed(
        $outputHandle,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Sales\Api\Data\OrderItemInterface $lineItem,
        \Magento\Catalog\Model\Product $product,
        $lineItemNumber,
        $shipmentDate
    )
    {
        $row = [];

        $row[] = $order->getIncrementId();
        $row[] = $order->getCreatedAt();
        $row[] = $order->getCustomerEmail();
        $row[] = $lineItem->getName();
        $row[] = $this->getProductUrl($product, $order->getStoreId());
        $row[] = $lineItemNumber;
        $row[] = $this->getOrderPostCode($order);
        $row[] = $order->getCustomerFirstname();
        $row[] = $order->getCustomerLastname();

        $sku = $this->config->getUseChildSku($order->getStoreId()) ? $lineItem->getSku() : $product->getSku();
        $row[] = $this->turnToProductHelper->turnToSafeEncoding($sku);

        $row[] = $lineItem->getOriginalPrice();
        $row[] = $this->productHelper->getImageUrl($product);
        $row[] = $shipmentDate;

        fputcsv($outputHandle, $row, "\t");
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     *
     * @return string
     */
    protected function getOrderPostCode(\Magento\Sales\Api\Data\OrderInterface $order)
    {
        $postCode = '';
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            $postCode = $shippingAddress->getPostcode();
        }

        return $postCode;
    }
}
