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
use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\AbstractSimpleObject;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilderFactory;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilderFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\File\WriteInterface;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Product as ProductModel;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Export\Product as ExportProduct;

class Orders
{
    /**#@+
     * Field Id keys
     */
    const MAIN_TABLE_PREFIX = 'main_table.';

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
    /**#@-*/

    protected const LOOKBACK_INTERVAL = 'P2D';

    /**
     * Default page size
     */
    const DEFAULT_PAGE_SIZE = 25;

    /**
     * Batch size for shipment API pagination / order id chunks
     */
    private const SHIPMENT_PAGE_SIZE = 500;

    private const ORDER_ID_CHUNK_SIZE = 100;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderService;

    /**
     * @var ShipmentRepositoryInterface
     */
    protected $shipmentsService;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var ProductHelper
     */
    protected $productHelper;

    /**
     * @var Filesystem
     */
    protected $filesystem;

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
     * @var FeedClient
     */
    protected $feedClient;
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
     * Orders constructor.
     *
     * @param Config $config
     * @param Monolog $logger
     * @param DateTimeFactory $dateTimeFactory
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param ShipmentRepositoryInterface $shipmentsService
     * @param ProductRepositoryInterface $productRepository
     * @param ProductHelper $productHelper
     * @param StoreManagerInterface $storeManager
     * @param ProductModel $productModel
     * @param FeedClient $feedClient
     * @param Filesystem $filesystem
     * @param OrderCollectionFactory $orderCollection
     * @param ExportProduct $exportProduct
     * @param FilterBuilderFactory $filterBuilderFactory
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param SortOrderBuilderFactory $sortOrderBuilderFactory
     */
    public function __construct(
        Config $config,
        Monolog $logger,
        DateTimeFactory $dateTimeFactory,
        OrderRepositoryInterface $orderRepositoryInterface,
        ShipmentRepositoryInterface $shipmentsService,
        ProductRepositoryInterface $productRepository,
        ProductHelper $productHelper,
        StoreManagerInterface $storeManager,
        ProductModel $productModel,
        FeedClient $feedClient,
        Filesystem $filesystem,
        OrderCollectionFactory $orderCollection,
        ExportProduct $exportProduct,
        FilterBuilderFactory $filterBuilderFactory,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        SortOrderBuilderFactory $sortOrderBuilderFactory
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->orderService = $orderRepositoryInterface;
        $this->shipmentsService = $shipmentsService;
        $this->productRepository = $productRepository;
        $this->productHelper = $productHelper;
        $this->storeManager = $storeManager;
        $this->productModel = $productModel;
        $this->feedClient = $feedClient;
        $this->filesystem = $filesystem;
        $this->orderCollectionFactory = $orderCollection;
        $this->exportProduct = $exportProduct;
        $this->filterBuilderFactory = $filterBuilderFactory;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->sortOrderBuilderFactory = $sortOrderBuilderFactory;
    }

    /**
     * CRON handler that sends the last 2 days of orders to TurnTo
     * @return void
     */
    public function cronUploadFeed()
    {
        foreach ($this->storeManager->getStores() as $store) {
            if ($this->config->getIsEnabled($store->getCode())
                && $this->config->getConfigBool(Config::ORDER_ENABLE_FEED, $store->getCode())
            ) {
                $feedPath = null;
                try {
                    $feedPath = $this->getOrdersFeed(
                        $store->getId(),
                        $this->dateTimeFactory->create('now', new DateTimeZone('UTC'))
                            ->sub(new DateInterval(static::LOOKBACK_INTERVAL)),
                        $this->dateTimeFactory->create(
                            'now',
                            new DateTimeZone('UTC')
                        )
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
                        'An error occurred while sending the Historical Orders Feed report to TurnTo.',
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
     * Build feed as a TSV file and return its absolute path (caller should delete after transmission).
     *
     * @param int|string $storeId
     * @param DateTime $fromDate
     * @param DateTime $toDate
     * @param bool $forceIncludeAllItems
     * @return string Absolute filesystem path
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function getOrdersFeed(
        $storeId,
        DateTime $fromDate,
        DateTime $toDate,
        bool $forceIncludeAllItems = false
    ): string {
        $tmpDir = $this->filesystem->getDirectoryWrite(DirectoryList::TMP);
        $tmpDir->create();
        $fileName = 'turnto_orders_feed_' . uniqid('', true) . '.tsv';
        $outputFile = $tmpDir->openFile($fileName, 'w+');

        try {
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
     * @param $orderList
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
     * @param WriteInterface $outputFile
     * @param Collection $orders
     * @param bool $forceIncludeAllItems
     *
     * @return int
     */
    protected function writeOrdersToFeed(WriteInterface $outputFile, $orders, $forceIncludeAllItems)
    {
        if ($orders->getSize() === 0) {
            return 0;
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
        $shipmentsByOrderId = $this->fetchShipmentsIndexedByOrderId($orderIds, $storeId);
        // Preload every product referenced on this page in a single query to avoid an N+1 load per order.
        $productsById = $this->loadProductsByIds($productIds);

        $numberOfRecordsWritten = 0;
        foreach ($orders as $order) {
            try {
                $oid = (int) $order->getEntityId();
                $orderShipments = $shipmentsByOrderId[$oid] ?? [];
                $this->writeOrderToFeed($outputFile, $order, $forceIncludeAllItems, $orderShipments, $productsById);
            } catch (Exception $e) {
                $this->logger->error(
                    'An error occurred while writing order data to the historical orders feed.',
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
     * @param WriteInterface $outputFile
     * @param OrderInterface $order
     * @param bool $forceIncludeAllItems
     * @param ShipmentInterface[] $shipmentsForOrder
     * @param array|null $productsById Products preloaded for the batch, keyed by entity id.
     * @throws NoSuchEntityException
     */
    protected function writeOrderToFeed(
        WriteInterface $outputFile,
        OrderInterface $order,
        $forceIncludeAllItems,
        array $shipmentsForOrder = [],
        ?array $productsById = null
    ) {
        $items = $this->getItemData($order, $forceIncludeAllItems, $shipmentsForOrder, $productsById);
        if (empty($items)) {
            return;
        }

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
     * @param OrderInterface $order
     * @param bool $forceIncludeAllItems
     * @param ShipmentInterface[] $shipmentsForOrder
     * @param array|null $productsById Products preloaded for the batch, keyed by entity id. When null,
     *                                 products for this order are loaded on demand.
     *
     * @return array
     */
    public function getItemData(
        OrderInterface $order,
        $forceIncludeAllItems,
        array $shipmentsForOrder = [],
        ?array $productsById = null
    ) {
        $items = [];
        $orderId = (int) $order->getEntityId();

        if ($productsById === null) {
            $productIds = [];
            foreach ($order->getItems() as $item) {
                if ($item->isDeleted() || $item->getParentItemId()) {
                    continue;
                }
                $pid = (int) $item->getProductId();
                if ($pid > 0) {
                    $productIds[] = $pid;
                }
            }

            $productsById = $this->loadProductsByIds($productIds);
        }

        foreach ($order->getItems() as $item) {
            try {
                if (!$item->isDeleted() && !$item->getParentItemId()) {
                    $itemId = $item->getItemId();
                    $key = "$orderId.$itemId";
                    $productId = (int) $item->getProductId();
                    $product = $productsById[$productId] ?? null;
                    if (!$product) {
                        continue;
                    }
                    $items[$key] = [
                        self::LINE_ITEM_FIELD_ID => $item,
                        self::PRODUCT_FIELD_ID => $product,
                        self::SHIP_DATE_FIELD_ID => ''
                    ];
                }
            } catch (NoSuchEntityException $e) {
                // Do nothing
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
     * Bulk load products for a batch of order items, keyed by entity id.
     *
     * @param int[] $productIds
     * @return array Products keyed by entity id.
     */
    public function getProductsIndexedById(array $productIds): array
    {
        return $this->loadProductsByIds($productIds);
    }

    /**
     * @param int[] $productIds
     * @return array Products keyed by entity id.
     */
    private function loadProductsByIds(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter($productIds)));
        if ($productIds === []) {
            return [];
        }

        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter('entity_id', $productIds, 'in');
        $searchCriteriaBuilder->setPageSize(max(count($productIds), 1));

        $result = $this->productRepository->getList($searchCriteriaBuilder->create());
        $byId = [];
        foreach ($result->getItems() as $product) {
            $byId[(int) $product->getId()] = $product;
        }

        return $byId;
    }

    /**
     * @param int[] $orderIds
     * @return array<int, ShipmentInterface[]>
     */
    public function getShipmentsIndexedByOrderId(array $orderIds, int $storeId): array
    {
        return $this->fetchShipmentsIndexedByOrderId($orderIds, $storeId);
    }

    /**
     * @param int[] $orderIds
     * @return array<int, ShipmentInterface[]>
     */
    private function fetchShipmentsIndexedByOrderId(array $orderIds, int $storeId): array
    {
        $orderIds = array_values(array_unique(array_filter($orderIds)));
        if ($orderIds === []) {
            return [];
        }

        $grouped = [];
        foreach (array_chunk($orderIds, self::ORDER_ID_CHUNK_SIZE) as $orderIdChunk) {
            $page = 1;
            $totalPages = 1;

            do {
                $orderIdFilter = $this->filterBuilderFactory->create()
                    ->setField(self::ORDER_ID_FIELD_ID)
                    ->setValue($orderIdChunk)
                    ->setConditionType('in')
                    ->create();
                $storeFilter = $this->getFilter(self::STORE_ID_FIELD_ID, $storeId, 'eq');

                $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
                $searchCriteriaBuilder->setPageSize(self::SHIPMENT_PAGE_SIZE);
                $searchCriteriaBuilder->setCurrentPage($page);
                $searchCriteriaBuilder->addSortOrder($this->getSortOrder(self::ORDER_ID_FIELD_ID));
                $searchCriteriaBuilder->addFilters([$orderIdFilter]);
                $searchCriteriaBuilder->addFilters([$storeFilter]);

                $searchResult = $this->shipmentsService->getList($searchCriteriaBuilder->create());
                foreach ($searchResult->getItems() as $shipment) {
                    $oid = (int) $shipment->getOrderId();
                    $grouped[$oid][] = $shipment;
                }

                $totalCount = (int) $searchResult->getTotalCount();
                $totalPages = max(1, (int) ceil($totalCount / self::SHIPMENT_PAGE_SIZE));
                $page++;
            } while ($page <= $totalPages);
        }

        return $grouped;
    }

    /**
     * @param $itemData
     * @param OrderInterface $order
     * @param ShipmentInterface[] $shipments
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
     * @param Product $product
     * @param                                            $lineItemNumber
     * @param                                            $shipmentDate
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
