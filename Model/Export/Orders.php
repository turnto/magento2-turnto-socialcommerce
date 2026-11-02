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
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Api\AbstractSimpleObject;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilderFactory;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilderFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\File\WriteInterface;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\Address\CollectionFactory as OrderAddressCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Item\CollectionFactory as ShipmentItemCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Product as ProductModel;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Export\Product as ExportProduct;

class Orders
{
    /**#@+
     * Field Id keys
     */
    public const MAIN_TABLE_PREFIX = 'main_table.';

    public const UPDATED_AT_FIELD_ID = 'updated_at';

    public const STORE_ID_FIELD_ID = 'store_id';

    public const ORDER_ID_FIELD_ID = 'order_id';

    public const PRODUCT_FIELD_ID = 'product';

    public const SHIP_DATE_FIELD_ID = 'shipDate';

    public const LINE_ITEM_FIELD_ID = 'lineItem';
    /**#@-*/

    /**#@+
     * TurnTo Transmission Constants
     */
    public const FEED_NAME = 'historical-orders-feed.tsv';

    public const FEED_STYLE = 'tab-style.1';
    /**#@-*/

    public const LOOKBACK_INTERVAL = 'P2D';

    /**
     * Default page size
     */
    public const DEFAULT_PAGE_SIZE = 25;

    /**
     * Batch size for shipment API pagination / order id chunks
     */
    public const SHIPMENT_PAGE_SIZE = 500;

    public const ORDER_ID_CHUNK_SIZE = 100;

    public const FEED_FILE_PREFIX = 'turnto_orders_feed_';

    /**
     * @var ShipmentRepositoryInterface
     */
    protected $shipmentsService;

    /**
     * @var ProductCollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var ProductHelper
     */
    protected $productHelper;

    /**
     * @var ProductModel
     */
    protected $productModel;

    /**
     * @var OrderCollectionFactory
     */
    protected $orderCollectionFactory;
    /**
     * @var ExportProduct
     */
    protected $exportProduct;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
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
     * @var SearchCriteriaBuilderFactory
     */
    protected $searchCriteriaBuilderFactory;
    /**
     * @var SortOrderBuilderFactory
     */
    protected $sortOrderBuilderFactory;
    /**
     * @var FilterBuilderFactory
     */
    protected $filterBuilderFactory;
    /**
     * @var OrderItemRepositoryInterface
     */
    protected $orderItemRepository;
    /**
     * @var OrderAddressCollectionFactory
     */
    protected $orderAddressCollectionFactory;
    /**
     * @var ShipmentItemCollectionFactory
     */
    protected $shipmentItemCollectionFactory;
    /**
     * @var TemporaryFeedFile
     */
    protected $temporaryFeedFile;

    /**
     * Orders constructor.
     *
     * @param Config $config
     * @param Monolog $logger
     * @param DateTimeFactory $dateTimeFactory
     * @param ShipmentRepositoryInterface $shipmentsService
     * @param ProductCollectionFactory $productCollectionFactory
     * @param ProductHelper $productHelper
     * @param StoreManagerInterface $storeManager
     * @param ProductModel $productModel
     * @param OrderCollectionFactory $orderCollection
     * @param ExportProduct $exportProduct
     * @param FilterBuilderFactory $filterBuilderFactory
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param SortOrderBuilderFactory $sortOrderBuilderFactory
     * @param OrderItemRepositoryInterface $orderItemRepository
     * @param OrderAddressCollectionFactory $orderAddressCollectionFactory
     * @param ShipmentItemCollectionFactory $shipmentItemCollectionFactory
     * @param TemporaryFeedFile $temporaryFeedFile
     */
    public function __construct(
        Config $config,
        Monolog $logger,
        DateTimeFactory $dateTimeFactory,
        ShipmentRepositoryInterface $shipmentsService,
        ProductCollectionFactory $productCollectionFactory,
        ProductHelper $productHelper,
        StoreManagerInterface $storeManager,
        ProductModel $productModel,
        OrderCollectionFactory $orderCollection,
        ExportProduct $exportProduct,
        FilterBuilderFactory $filterBuilderFactory,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        SortOrderBuilderFactory $sortOrderBuilderFactory,
        OrderItemRepositoryInterface $orderItemRepository,
        OrderAddressCollectionFactory $orderAddressCollectionFactory,
        ShipmentItemCollectionFactory $shipmentItemCollectionFactory,
        TemporaryFeedFile $temporaryFeedFile
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->shipmentsService = $shipmentsService;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productHelper = $productHelper;
        $this->storeManager = $storeManager;
        $this->productModel = $productModel;
        $this->orderCollectionFactory = $orderCollection;
        $this->exportProduct = $exportProduct;
        $this->filterBuilderFactory = $filterBuilderFactory;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->sortOrderBuilderFactory = $sortOrderBuilderFactory;
        $this->orderItemRepository = $orderItemRepository;
        $this->orderAddressCollectionFactory = $orderAddressCollectionFactory;
        $this->shipmentItemCollectionFactory = $shipmentItemCollectionFactory;
        $this->temporaryFeedFile = $temporaryFeedFile;
    }

    /**
     * CRON handler that sends the last 2 days of orders to TurnTo
     *
     * @return void
     */
    public function cronUploadFeed()
    {
        foreach ($this->storeManager->getStores() as $store) {
            if ($this->config->getIsEnabled($store->getCode())
                && $this->config->getConfigBool(Config::ORDER_ENABLE_FEED, $store->getCode())
            ) {
                try {
                    $feedPath = $this->createOrdersFeedFile(
                        $store->getId(),
                        $this->dateTimeFactory->create('now', new DateTimeZone('UTC'))
                            ->sub(new DateInterval(static::LOOKBACK_INTERVAL)),
                        $this->dateTimeFactory->create(
                            'now',
                            new DateTimeZone('UTC')
                        )
                    );
                    $this->temporaryFeedFile->transmitAndDelete(
                        $feedPath,
                        self::FEED_NAME,
                        self::FEED_STYLE,
                        $store->getCode()
                    );
                } catch (Exception $e) {
                    $this->logger->error(
                        'An error occurred while sending the Historical Orders Feed report to TurnTo.',
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
     * Build the orders feed and return its TSV contents
     *
     * Prefer createOrdersFeedFile() for large exports; this loads the whole feed into memory.
     *
     * @param int|string $storeId
     * @param DateTime $fromDate
     * @param DateTime $toDate
     * @param bool $forceIncludeAllItems
     * @return string
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function getOrdersFeed(
        $storeId,
        DateTime $fromDate,
        DateTime $toDate,
        bool $forceIncludeAllItems = false
    ) {
        return $this->temporaryFeedFile->readAndDelete(
            $this->createOrdersFeedFile($storeId, $fromDate, $toDate, $forceIncludeAllItems)
        );
    }

    /**
     * Build the orders feed as a TSV file in var/tmp and return its absolute path
     *
     * The caller owns the file; TemporaryFeedFile::transmitAndDelete() sends and removes it.
     *
     * @param int|string $storeId
     * @param DateTime $fromDate
     * @param DateTime $toDate
     * @param bool $forceIncludeAllItems
     * @return string
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function createOrdersFeedFile(
        $storeId,
        DateTime $fromDate,
        DateTime $toDate,
        bool $forceIncludeAllItems = false
    ): string {
        return $this->temporaryFeedFile->create(
            self::FEED_FILE_PREFIX,
            function (WriteInterface $outputFile) use ($storeId, $fromDate, $toDate, $forceIncludeAllItems) {
                $outputFile->writeCsv(
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
                    "\t",
                    '"'
                );
                $orderFeed = $this->getOrders($storeId, $fromDate, $toDate);
                $this->writeOrdersFeed($orderFeed, $outputFile, $forceIncludeAllItems);
            }
        );
    }

    /**
     * Write orders to file
     *
     * @param Collection $orderList
     * @param WriteInterface $outputFile
     * @param bool $forceIncludeAllItems
     */
    public function writeOrdersFeed($orderList, WriteInterface $outputFile, $forceIncludeAllItems)
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
                    $this->writeOrdersToFeed($outputFile, $paginatedCollection, $forceIncludeAllItems);
                }
            } catch (Exception $e) {
                $this->logger->error(
                    'TurnTo Orders Export Exception: An exception was triggered while exporting orders.',
                    [
                        'page' => $i,
                        'page_limit' => $pageLimit,
                        'exception' => $e
                    ]
                );
            }
        }
    }

    /**
     * Resolve feed line items for a page of orders using one query per related entity type
     *
     * Order items, shipments, shipment items and products are bulk-loaded for the whole page instead of per order.
     *
     * @param iterable|OrderInterface[] $orders
     * @param bool $forceIncludeAllItems
     * @return array Line item data keyed by order entity id, in the shape returned by getItemData()
     */
    public function getItemDataForOrders(iterable $orders, $forceIncludeAllItems): array
    {
        $ordersByStoreId = [];
        foreach ($orders as $order) {
            $ordersByStoreId[(int) $order->getStoreId()][(int) $order->getEntityId()] = $order;
        }
        if ($ordersByStoreId === []) {
            return [];
        }

        $itemDataByOrderId = [];
        foreach ($ordersByStoreId as $storeId => $storeOrders) {
            $this->preloadOrderItems($storeOrders);
            $shipmentsByOrderId = $this->getShipmentsIndexedByOrderId(array_keys($storeOrders), $storeId);
            $productsById = $this->getProductsIndexedById($this->collectProductIds($storeOrders), $storeId);

            foreach ($storeOrders as $orderId => $order) {
                try {
                    $itemDataByOrderId[$orderId] = $this->getItemData(
                        $order,
                        $forceIncludeAllItems,
                        $shipmentsByOrderId[$orderId] ?? [],
                        $productsById
                    );
                } catch (Exception $e) {
                    $this->logger->error(
                        'An error occurred while collecting order line items for a TurnTo feed.',
                        [
                            'order_id' => $orderId,
                            'store_id' => $storeId,
                            'exception' => $e,
                        ]
                    );
                }
            }
        }

        return $itemDataByOrderId;
    }

    /**
     * @param WriteInterface $outputFile
     * @param Collection $orders
     * @param bool $forceIncludeAllItems
     *
     * @return int
     */
    protected function writeOrdersToFeed(WriteInterface $outputFile, $orders, $forceIncludeAllItems)
    {
        $itemDataByOrderId = $this->getItemDataForOrders($orders, $forceIncludeAllItems);
        if ($itemDataByOrderId === []) {
            return 0;
        }
        $this->preloadShippingAddresses($orders);

        $numberOfRecordsWritten = 0;
        foreach ($orders as $order) {
            $orderId = (int) $order->getEntityId();
            try {
                $this->writeItemsToFeed($outputFile, $order, $itemDataByOrderId[$orderId] ?? []);
            } catch (Exception $e) {
                $this->logger->error(
                    'An error occurred while writing order data to the historical orders feed.',
                    [
                        'order_id' => $orderId,
                        'store_id' => (int) $order->getStoreId(),
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
     * @param WriteInterface $outputFile
     * @param OrderInterface $order
     * @param bool $forceIncludeAllItems
     * @param ShipmentInterface[]|null $shipmentsForOrder Null loads the order's shipments on demand
     * @param array|null $productsById Null loads the order's products on demand
     * @throws NoSuchEntityException
     */
    protected function writeOrderToFeed(
        WriteInterface $outputFile,
        OrderInterface $order,
        $forceIncludeAllItems,
        ?array $shipmentsForOrder = null,
        ?array $productsById = null
    ) {
        $this->writeItemsToFeed(
            $outputFile,
            $order,
            $this->getItemData($order, $forceIncludeAllItems, $shipmentsForOrder, $productsById)
        );
    }

    /**
     * @param WriteInterface $outputFile
     * @param OrderInterface $order
     * @param array $items
     * @throws NoSuchEntityException
     */
    protected function writeItemsToFeed(WriteInterface $outputFile, OrderInterface $order, array $items): void
    {
        $itemNumber = 0;
        foreach ($items as $item) {
            $this->writeLineToFeed(
                $outputFile,
                $order,
                $item[self::LINE_ITEM_FIELD_ID],
                $item[self::PRODUCT_FIELD_ID],
                $itemNumber++,
                $item[self::SHIP_DATE_FIELD_ID]
            );
        }
    }

    /**
     * Resolve feed line items for one order
     *
     * Batch callers should use getItemDataForOrders(); when shipments or products are not supplied they are
     * loaded for this order alone.
     *
     * @param OrderInterface $order
     * @param bool $forceIncludeAllItems
     * @param ShipmentInterface[]|null $shipmentsForOrder
     * @param array|null $productsById Products keyed by entity id
     *
     * @return array
     */
    public function getItemData(
        OrderInterface $order,
        $forceIncludeAllItems,
        ?array $shipmentsForOrder = null,
        ?array $productsById = null
    ) {
        $items = [];
        $orderId = (int) $order->getEntityId();
        $storeId = (int) $order->getStoreId();

        if ($productsById === null) {
            $productsById = $this->getProductsIndexedById($this->collectProductIds([$order]), $storeId);
        }
        if ($shipmentsForOrder === null) {
            $shipmentsForOrder = $this->getShipmentsIndexedByOrderId([$orderId], $storeId)[$orderId] ?? [];
        }

        foreach ($order->getItems() as $item) {
            if ($this->isFeedLineItem($item)) {
                $product = $productsById[(int) $item->getProductId()] ?? null;
                if (!$product) {
                    continue;
                }
                $items["$orderId.{$item->getItemId()}"] = [
                    self::LINE_ITEM_FIELD_ID => $item,
                    self::PRODUCT_FIELD_ID => $product,
                    self::SHIP_DATE_FIELD_ID => ''
                ];
            }
        }

        $items = $this->addShipDateToItemData($items, $order, $shipmentsForOrder);
        if (
            !$forceIncludeAllItems
            && $this->config->getConfigBool(Config::ORDER_EXCLUDE_ITEMS_WITHOUT_DELIVERY_DATE, $order->getStore()->getCode())
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
     * Product ids of the top-level line items across the given orders
     *
     * @param iterable|OrderInterface[] $orders
     * @return int[]
     */
    protected function collectProductIds(iterable $orders): array
    {
        $productIds = [];
        foreach ($orders as $order) {
            foreach ($order->getItems() as $item) {
                $productId = (int) $item->getProductId();
                if ($productId > 0 && $this->isFeedLineItem($item)) {
                    $productIds[] = $productId;
                }
            }
        }

        return $productIds;
    }

    /**
     * Child items of configurable/bundle products are represented by their parent line
     *
     * @param OrderItemInterface $item
     * @return bool
     */
    protected function isFeedLineItem($item): bool
    {
        return !$item->isDeleted() && !$item->getParentItemId();
    }

    /**
     * Load the order items for every order with one repository query and attach them to their orders
     *
     * @param OrderInterface[] $ordersById Orders keyed by entity id
     * @return void
     */
    protected function preloadOrderItems(array $ordersById): void
    {
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(OrderItemInterface::ORDER_ID, array_keys($ordersById), 'in');

        $itemsByOrderId = [];
        foreach ($this->orderItemRepository->getList($searchCriteriaBuilder->create())->getItems() as $item) {
            $itemsByOrderId[(int) $item->getOrderId()][] = $item;
        }

        foreach ($ordersById as $orderId => $order) {
            $order->setItems($itemsByOrderId[$orderId] ?? []);
        }
    }

    /**
     * Load the addresses for every order with one query so getShippingAddress() does not query per order
     *
     * @param iterable|OrderInterface[] $orders
     * @return void
     */
    protected function preloadShippingAddresses(iterable $orders): void
    {
        $ordersById = [];
        foreach ($orders as $order) {
            if ($order instanceof DataObject) {
                $ordersById[(int) $order->getEntityId()] = $order;
            }
        }
        if ($ordersById === []) {
            return;
        }

        $addresses = $this->orderAddressCollectionFactory->create()
            ->addFieldToFilter('parent_id', ['in' => array_keys($ordersById)]);
        $addressesByOrderId = [];
        foreach ($addresses as $address) {
            $orderId = (int) $address->getParentId();
            $address->setOrder($ordersById[$orderId]);
            $addressesByOrderId[$orderId][] = $address;
        }

        foreach ($ordersById as $orderId => $order) {
            $order->setData('addresses', $addressesByOrderId[$orderId] ?? []);
        }
    }

    /**
     * Load products in the order's store scope, keyed by entity id
     *
     * @param int[] $productIds
     * @param int $storeId
     * @return ProductInterface[]
     */
    protected function getProductsIndexedById(array $productIds, int $storeId): array
    {
        $productIds = array_values(array_unique(array_filter($productIds)));
        if ($productIds === []) {
            return [];
        }

        $collection = $this->productCollectionFactory->create()
            ->setStoreId($storeId)
            ->addAttributeToSelect('*')
            ->addIdFilter($productIds);

        $productsById = [];
        foreach ($collection as $product) {
            $productsById[(int) $product->getId()] = $product;
        }

        return $productsById;
    }

    /**
     * Load shipments, and their items, for many orders in batches, keyed by order id
     *
     * @param int[] $orderIds
     * @param int $storeId
     * @return array<int, ShipmentInterface[]>
     */
    protected function getShipmentsIndexedByOrderId(array $orderIds, int $storeId): array
    {
        $orderIds = array_values(array_unique(array_filter($orderIds)));
        if ($orderIds === []) {
            return [];
        }

        $shipmentsByOrderId = [];
        $shipmentsById = [];
        foreach (array_chunk($orderIds, self::ORDER_ID_CHUNK_SIZE) as $orderIdChunk) {
            $page = 1;

            do {
                $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
                $searchCriteriaBuilder->setPageSize(self::SHIPMENT_PAGE_SIZE);
                $searchCriteriaBuilder->setCurrentPage($page);
                $searchCriteriaBuilder->addSortOrder($this->getSortOrder(self::ORDER_ID_FIELD_ID));
                $searchCriteriaBuilder->addFilters([$this->getFilter(self::ORDER_ID_FIELD_ID, $orderIdChunk, 'in')]);
                $searchCriteriaBuilder->addFilters([$this->getFilter(self::STORE_ID_FIELD_ID, $storeId, 'eq')]);

                $searchResult = $this->shipmentsService->getList($searchCriteriaBuilder->create());
                foreach ($searchResult->getItems() as $shipment) {
                    $shipmentsByOrderId[(int) $shipment->getOrderId()][] = $shipment;
                    $shipmentsById[(int) $shipment->getEntityId()] = $shipment;
                }

                $totalPages = max(1, (int) ceil((int) $searchResult->getTotalCount() / self::SHIPMENT_PAGE_SIZE));
                $page++;
            } while ($page <= $totalPages);
        }

        $this->preloadShipmentItems($shipmentsById);

        return $shipmentsByOrderId;
    }

    /**
     * Attach shipment items to their shipments with one query per batch
     *
     * @param ShipmentInterface[] $shipmentsById
     * @return void
     */
    protected function preloadShipmentItems(array $shipmentsById): void
    {
        $shipmentsById = array_filter(
            $shipmentsById,
            function ($shipment) {
                return $shipment instanceof DataObject;
            }
        );
        if ($shipmentsById === []) {
            return;
        }

        $itemsByShipmentId = [];
        foreach (array_chunk(array_keys($shipmentsById), self::SHIPMENT_PAGE_SIZE) as $shipmentIdChunk) {
            $shipmentItems = $this->shipmentItemCollectionFactory->create()
                ->addFieldToFilter('parent_id', ['in' => $shipmentIdChunk]);
            foreach ($shipmentItems as $shipmentItem) {
                $shipmentId = (int) $shipmentItem->getParentId();
                $shipmentItem->setShipment($shipmentsById[$shipmentId]);
                $itemsByShipmentId[$shipmentId][] = $shipmentItem;
            }
        }

        foreach ($shipmentsById as $shipmentId => $shipment) {
            $shipment->setItems($itemsByShipmentId[$shipmentId] ?? []);
        }
    }

    /**
     * Add shipping data
     *
     * @param ShipmentInterface[] $shipments
     * @param OrderInterface $order
     * @param array $itemData
     *
     * @return array
     */
    protected function addShipDateToItemData($itemData, OrderInterface $order, array $shipments)
    {
        $orderId = (int) $order->getEntityId();
        $storeId = (int) $order->getStoreId();

        // If this setting is on, we only send shipment data if the whole order has shipped
        $configExcludeDeliveryDateUntilAllItemsShipped = $this->config->getConfigBool(
            Config::ORDER_EXCLUDE_DELIVERY_DATE_ON_PARTIAL_SHIPMENT,
            $storeId
        );
        $allItemsShipped = false;
        if ($configExcludeDeliveryDateUntilAllItemsShipped) {
            $allItemsShipped = $this->getAllOrdersShipped($order);
        }
        // TRUE if: "Exclude Delivery Date..." is off, OR if it's on and all items have shipped
        $includeShipped = ($configExcludeDeliveryDateUntilAllItemsShipped && $allItemsShipped)
            || !$configExcludeDeliveryDateUntilAllItemsShipped;

        foreach ($shipments as $shipment) {
            foreach ($shipment->getItems() as $shipmentItem) {
                $itemId = $shipmentItem->getOrderItemId();
                $key = "$orderId.$itemId";
                if (isset($itemData[$key]) && $includeShipped) {
                    $itemData[$key][self::SHIP_DATE_FIELD_ID] = $shipment->getCreatedAt();
                }
            }
        }

        return $itemData;
    }

    /**
     * @param int|string $orderId
     * @param int|string $storeId
     * @return SearchCriteria
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
     * @param $fieldId
     * @param $value
     * @param $conditionType
     * @return Filter
     */
    public function getFilter($fieldId, $value, $conditionType)
    {
        return $this->filterBuilderFactory->create()
            ->setField($fieldId)
            ->setValue($value)
            ->setConditionType($conditionType)
            ->create();
    }

    /**
     * @param $sortOrder
     * @param $filters
     * @param $pageSize
     * @return SearchCriteria
     */
    public function getSearchCriteria($sortOrder, $filters = [], $pageSize = self::DEFAULT_PAGE_SIZE)
    {
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->setPageSize($pageSize)->addSortOrder($sortOrder);
        foreach ($filters as $filter) {
            //add as separate groups to get AND join instead of OR
            $searchCriteriaBuilder->addFilters([$filter]);
        }

        return $searchCriteriaBuilder->create();
    }

    /**
     * @param $fieldId
     * @param string $direction
     * @return AbstractSimpleObject
     */
    public function getSortOrder($fieldId, $direction = SortOrder::SORT_ASC)
    {
        return $this->sortOrderBuilderFactory->create()
            ->setField($fieldId)->setDirection($direction)->create();
    }

    /**
     * @param WriteInterface $outputFile
     * @param OrderInterface $order
     * @param OrderItemInterface $lineItem
     * @param ProductInterface $product
     * @param int $lineItemNumber
     * @param string $shipmentDate
     * @throws NoSuchEntityException
     */
    protected function writeLineToFeed(
        WriteInterface $outputFile,
        OrderInterface $order,
        OrderItemInterface $lineItem,
        ProductInterface $product,
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
        $row[] = $this->exportProduct->getProductUrl($product, $order->getStoreId());
        $row[] = $lineItemNumber;
        $row[] = $this->getOrderPostCode($order);
        $row[] = $order->getCustomerFirstname();
        $row[] = $order->getCustomerLastname();

        $sku = $this->config->getUseChildSku($order->getStoreId()) ? $lineItem->getSku() : $product->getSku();
        $row[] = $this->productModel->turnToSafeEncoding($sku);

        $row[] = $lineItem->getOriginalPrice();
        $row[] = $this->productHelper->getImageUrl($product);
        $row[] = $shipmentDate;

        $outputFile->writeCsv($row, "\t", '"');
    }

    /**
     * @param OrderInterface $order
     *
     * @return string
     */
    protected function getOrderPostCode(OrderInterface $order)
    {
        $postCode = '';
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            $postCode = $shippingAddress->getPostcode();
        }

        return $postCode;
    }

    /**
     * @param $storeId
     * @param $fromDate
     * @param $toDate
     * @return Collection
     */
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
                ['gteq' => $fromDate->format('Y-m-d H:i:s')],
                ['gteq' => $fromDate->format('Y-m-d H:i:s')]
            ]
        );
        $orderList->addFieldToFilter(
            self::MAIN_TABLE_PREFIX . self::UPDATED_AT_FIELD_ID,
            ['lteq' => $toDate->format('Y-m-d H:i:s')]
        );
        $orderList->getSelect()->group('main_table.entity_id');

        return $orderList;
    }

    /**
     * @param OrderInterface $order
     * @return bool
     */
    protected function getAllOrdersShipped(OrderInterface $order): bool
    {

        $items = $order->getItems();

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
