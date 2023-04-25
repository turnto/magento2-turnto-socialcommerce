<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Export;

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

class Orders extends AbstractExport
{
    /**#@+
     * Field Id keys
     */
    CONST MAIN_TABLE_PREFIX = 'main_table.';

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
     * @var File
     */
    protected $fileSystem;

    /**
     * @var OrderCollectionFactoryAlias
     */
    protected $orderCollectionFactory;

    /**
     * Orders constructor.
     *
     * @param Config                      $config
     * @param CollectionFactory           $productCollectionFactory
     * @param Monolog                     $logger
     * @param DateTimeFactory             $dateTimeFactory
     * @param SearchCriteriaBuilder       $searchCriteriaBuilder
     * @param FilterBuilder               $filterBuilder
     * @param SortOrderBuilder            $sortOrderBuilder
     * @param UrlFinderInterface          $urlFinder
     * @param StoreManagerInterface       $storeManager
     * @param OrderRepositoryInterface    $orderRepositoryInterface
     * @param ShipmentRepositoryInterface $shipmentsService
     * @param ProductRepository           $productRepository
     * @param Product                     $productHelper
     * @param DirectoryList               $directoryList
     * @param TurnToProductHelper         $turnToProductHelper
     * @param File                        $fileSystem
     * @param OrderCollectionFactoryAlias $orderCollection
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
        TurnToProductHelper $turnToProductHelper,
        File $fileSystem,
        OrderCollectionFactoryAlias $orderCollection
    ) {
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
        $this->fileSystem = $fileSystem;
        $this->orderCollectionFactory = $orderCollection;
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
                    $orderFeed =$this->getOrdersFeed(
                        $store->getId(),
                        $this->dateTimeFactory->create('now', new \DateTimeZone('UTC'))->sub(new \DateInterval('P2D')),
                        $this->dateTimeFactory->create(
                            'now',
                            new \DateTimeZone('UTC')

                        )
                    );
                    $this->transmitFeed($orderFeed,$store);
                } catch (\Exception $e) {
                    $this->logger->error(
                        'An error occurred while sending the Historical Orders Feed report to TurnTo. Error:',
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
    public function getOrdersFeed(
        $storeId,
        \DateTime $fromDate,
        \DateTime $toDate,
        $forceIncludeAllItems = false
    ) {
        $csvData = null;
        try {
	        $this->fileSystem->checkAndCreateFolder($this->directoryList->getPath(DirectoryList::TMP));

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
                ],
                "\t"
            );
            $orderFeed = $this->getOrders($storeId, $fromDate, $toDate);
            $this->writeOrdersFeed($orderFeed, $outputHandle, $forceIncludeAllItems);
            rewind($outputHandle);
            $csvData = stream_get_contents($outputHandle);

        } catch (\Exception $e) {
            $this->logger->error(
                'An error occurred while creating or writing data to the Historical Orders Feed export file. Error:',
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
            $zendClient
                ->setUri($this->config
                    ->getFeedUploadAddress($store->getCode()))
                ->setMethod('POST')
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
                'An error occurred while transmitting the order feed to TurnTo. Error:',
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
    public function writeOrdersFeed($orderList, $outputHandle, $forceIncludeAllItems)
    {
        $pageLimit = $orderList->getLastPageNumber();
        $pageSize = $orderList->getPageSize();
        for ($i = 1; $i <= $pageLimit; $i++) {
            try {

                $paginatedCollection = clone $orderList;
                $paginatedCollection->clear();
                $paginatedCollection->setPageSize($pageSize)->setCurPage($i);
                $paginatedCollection->load();

                if ($paginatedCollection->count() > 0) {
                    $this->writeOrdersToFeed($outputHandle, $paginatedCollection, $forceIncludeAllItems);
                }
            } catch (\Exception $e) {
                $this->logger->error(
                    "TurnTo Orders Export Exception: An exception was triggered on page $i of $pageLimit.",
                    [
                        'exception' => $e
                    ]
                );
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
                    'An error occurred while writing order data to the historical orders feed. Error:',
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

        // If this setting is on, we only send shipment data if the whole order has shipped
        $configExcludeDeliveryDateUntilAllItemsShipped = $this->config->getExcludeDeliveryDateUntilAllItemsShipped($storeId);
        $allItemsShipped = false;
        if ($configExcludeDeliveryDateUntilAllItemsShipped) {
            $allItemsShipped = $this->getAllOrdersShipped($orderId);
        }
        // TRUE if: "Exclude Delivery Date..." is off, OR if it's on and all items have shipped
        $includeShipped = ($configExcludeDeliveryDateUntilAllItemsShipped && $allItemsShipped) || !$configExcludeDeliveryDateUntilAllItemsShipped;

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
                        if (isset($itemData[$key]) && $includeShipped) {
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
    ) {
        $row = [];

        $productName = $lineItem->getName();
        $productName = str_replace("\"", "'", $productName);
        $productName = str_replace("\n", "", $productName);

        $row[] = $order->getIncrementId();
        $row[] = $order->getCreatedAt();
        $row[] = $order->getCustomerEmail();
        $row[] = $productName;
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

    protected function getOrders($storeId, $fromDate, $toDate){
        $orderList = $this->orderCollectionFactory->create();

        $select = $orderList->getSelect();
        $select->joinLeft(
            ["shipment" => "sales_shipment"],
            'main_table.entity_id = shipment.order_id',
            []
        )->joinLeft(
            ["shipment_track" => "sales_shipment_track"],
            'shipment.entity_id = shipment_track.parent_id',
            ['ship_updated_at' => 'shipment_track.updated_at']
        );

        $orderList->addFieldToFilter(self::MAIN_TABLE_PREFIX . self::STORE_ID_FIELD_ID, ['eq' => $storeId]);
        $orderList->addFieldToFilter(
            [self::MAIN_TABLE_PREFIX . self::UPDATED_AT_FIELD_ID, 'shipment_track.updated_at'], [
                ['gteq' => $fromDate->format(DATE_ATOM)],
                ['gteq' => $fromDate->format(DATE_ATOM)]
            ]
        );
        $orderList->addFieldToFilter(
            self::MAIN_TABLE_PREFIX . self::UPDATED_AT_FIELD_ID,
            ['lteq' => $toDate->format(DATE_ATOM)]
        );
        $orderList->getSelect()->group('main_table.entity_id');

        return $orderList;
    }

    /**
     * @param $orderId
     * @return bool
     */
    protected function getAllOrdersShipped($orderId) {

        $items = $this->orderService->get($orderId)->getItems();

        $allItemsShipped = true;
        foreach ($items as $item) {
            // If the item has a parent item, that means it's just the associated Simple product, which doesn't actually
            //   track shipping and such, so we want to skip it.
            if ($item->getParentItem()) {
                continue;
            }

            $qtyOrdered = $item->getQtyOrdered();
            $qtyHandled = ($item->getQtyCanceled() + $item->getQtyRefunded() + $item->getQtyReturned() + $item->getQtyShipped());
            $qtyRemaining = $qtyOrdered - $qtyHandled;
            if ($qtyRemaining) {
                $allItemsShipped = false;
            }
        }

        return $allItemsShipped;
    }
}
