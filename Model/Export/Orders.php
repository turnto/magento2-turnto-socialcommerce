<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 6/24/16
 * Time: 12:42 PM
 */

namespace TurnTo\SocialCommerce\Model\Export;

use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\App\Filesystem\DirectoryList;

class Orders extends AbstractExport
{
    /**#@+
     * Field Id keys
     */
    const UPDATED_AT_FIELD_ID = 'updated_at';

    const ITEM_ID_FIELD_ID = 'item_id';
    
    const STORE_ID_FIELD_ID = 'store_id';
    
    const ORDER_ID_FIELD_ID = 'order_id';

    const PRODUCT_FIELD_ID = 'product';

    const SHIP_DATE_FIELD_ID = 'shipDate';

    const LINE_ITEM_FIELD_ID = 'lineItem';
    /**#@-*/

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface|null
     */
    protected $orderService = null;

    /**
     * @var \Magento\Sales\Api\ShipmentRepositoryInterface|null
     */
    protected $shipmentsService = null;

    /**
     * @var ProductRepository|null
     */
    protected $productRepository = null;

    /**
     * @var \Magento\Catalog\Helper\Product|null
     */
    protected $productHelper = null;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface|null
     */
    protected $storeManager = null;

    /**
     * @var \Magento\Framework\Filesystem\DirectoryList|null
     */
    protected $directoryList = null;

    /**
     * Orders constructor.
     *
     * @param \TurnTo\SocialCommerce\Helper\Config $config
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \TurnTo\SocialCommerce\Logger\Monolog $logger
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Framework\Stdlib\DateTime\DateTimeFactory $dateTimeFactory
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Framework\Api\FilterBuilder $filterBuilder
     * @param \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface
     * @param \Magento\Sales\Api\ShipmentRepositoryInterface $shipmentsService
     * @param ProductRepository $productRepository
     * @param \Magento\Catalog\Helper\Product $productHelper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Filesystem\DirectoryList $directoryList
     */
    public function __construct(
        \TurnTo\SocialCommerce\Helper\Config $config,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \TurnTo\SocialCommerce\Logger\Monolog $logger,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\Stdlib\DateTime\DateTimeFactory $dateTimeFactory,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface,
        \Magento\Sales\Api\ShipmentRepositoryInterface $shipmentsService,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Catalog\Helper\Product $productHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Filesystem\DirectoryList $directoryList
    ) {
        $this->orderService = $orderRepositoryInterface;
        $this->shipmentsService = $shipmentsService;

        $this->productRepository = $productRepository;
        $this->productHelper = $productHelper;
        $this->storeManager = $storeManager;
        $this->directoryList = $directoryList;

        parent::__construct(
            $config,
            $productCollectionFactory,
            $logger,
            $encryptor,
            $dateTimeFactory,
            $searchCriteriaBuilder,
            $filterBuilder,
            $sortOrderBuilder
        );
    }
    
    public function cronUploadFeed()
    {
        $varDirectory = $this->directoryList->getPath(DirectoryList::VAR_DIR) . '/';

        foreach ($this->storeManager->getStores() as $store) {
            if (
                $this->config->getIsEnabled($store->getCode())
                && $this->config->getIsHistoricalOrdersFeedEnabled($store->getCode())
            ) {
                $startedAtDateTime = $this->dateTimeFactory->create()->date(DATE_RFC3339, 0);
                $startDate = $this->config->getHistoricalOrdersFeedMostRecentExportTimestamp($store->getCode());
                $this->createOrdersFeed($store->getId(), $varDirectory . $store->getId() . '.csv' , $startDate);
                $this->config->setHistoricalOrdersFeedMostRecentExportTimestamp($store->getCode(), $startedAtDateTime);
            }
        }
    }

    /**
     * @param $storeId
     * @param $writeToPath
     * @param $startDateTime
     */
    public function createOrdersFeed($storeId, $writeToPath, $startDateTime) {
        $searchCriteria = $this->getOrdersSearchCriteria($storeId, $startDateTime);
        try {
            $outputHandle = fopen($writeToPath, 'w');
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
            $this->writeOrdersFeed($searchCriteria, $outputHandle, $storeId);
        } catch (\Exception $e) {
            $this->logger->error($e); //TODO make this better
        } finally {
            if (isset($outputHandle)) {
                fclose($outputHandle);
            }
        }
    }

    /**
     * @param $storeId
     * @param $startDateTime
     * @return \Magento\Framework\Api\SearchCriteria
     */
    public function getOrdersSearchCriteria($storeId, $startDateTime)
    {
        if (!isset($startDateTime)) {
            $startDateTime = $this->dateTimeFactory->create()->date(DATE_RFC3339, '1900-01-01');
        }

        return $this->getSearchCriteria(
            $this->getSortOrder(self::UPDATED_AT_FIELD_ID),
            [
                $this->getFilter(self::STORE_ID_FIELD_ID, $storeId, 'eq'),
                $this->getFilter(self::UPDATED_AT_FIELD_ID, $startDateTime, 'gteq')
            ]
        );
    }

    /**
     * @param \Magento\Framework\Api\SearchCriteria $searchCriteria
     * @param $outputHandle
     */
    public function writeOrdersFeed(\Magento\Framework\Api\SearchCriteria $searchCriteria, $outputHandle)
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
                $this->writeOrdersToFeed($outputHandle, $paginatedCollection);
            }
        }
    }

    /**
     * @param $outputHandle
     * @param $orders
     * @return int
     */
    protected function writeOrdersToFeed($outputHandle, $orders)
    {
        if (!isset($orders) || empty($orders)) {
            return 0;
        }

        $numberOfRecordsWritten = 0;
        foreach ($orders as $order) {
            try {
                $this->writeOrderToFeed($outputHandle, $order);
            } catch (\Exception $e) {
                $this->logger->error($e);
                //todo make the logging better
            } finally {
                $numberOfRecordsWritten++;
            }
        }

        return $numberOfRecordsWritten;
    }

    /**
     * @param $outputHandle
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     */
    protected function writeOrderToFeed($outputHandle, \Magento\Sales\Api\Data\OrderInterface $order)
    {
        $items = $this->getItemData($order);
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
     * @return array|mixed
     */
    public function getItemData(\Magento\Sales\Api\Data\OrderInterface $order)
    {
        $items = [];
        $orderId = $order->getEntityId();

        foreach ($order->getItems() as $item) {
            if (!$item->isDeleted() && !$item->getParentItemId()) {
                $itemId = $item->getItemId();
                $key = "$orderId.$itemId";
                $items[$key] = [
                    self::LINE_ITEM_FIELD_ID => $item,
                    self::PRODUCT_FIELD_ID => $this->productRepository->getById($item->getProductId()),
                    self::SHIP_DATE_FIELD_ID => ''
                ];
            }
        }
        $items = $this->addShipDateToItemData($items, $orderId, $order->getStoreId());

        return $items;
    }

    /**
     * @param $itemData
     * @param $orderId
     * @param $storeId
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
                foreach ($paginatedCollection as $shipment) {
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
     * @return \Magento\Framework\Api\SearchCriteria
     */
    public function getShipmentSearchCriteriaForOrder($orderId, $storeId)
    {
        return $this->getSearchCriteria(
            $this->getSortOrder(self::ITEM_ID_FIELD_ID),
            [
                $this->getFilter(self::STORE_ID_FIELD_ID, $storeId, 'eq'),
                $this->getFilter(self::ORDER_ID_FIELD_ID, $orderId, 'eq')
            ]
        );
    }

    /**
     * @param $outputHandle
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param \Magento\Sales\Api\Data\OrderItemInterface $lineItem
     * @param \Magento\Catalog\Model\Product $product
     * @param $lineItemNumber
     * @param $shipmentDate
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

        $row[] = $order->getIncrementId();
        $row[] = $order->getCreatedAt();
        $row[] = $order->getCustomerEmail();
        $row[] = $lineItem->getName();
        $row[] = $product->getProductUrl();
        $row[] = $lineItemNumber;
        $row[] = $order->getShippingAddress()->getPostcode();
        $row[] = $order->getCustomerFirstname();
        $row[] = $order->getCustomerLastname();
        $row[] = $product->getSku();
        $row[] = $lineItem->getOriginalPrice();
        $row[] = $this->productHelper->getImageUrl($product);
        $row[] = $shipmentDate;
        
        fputcsv($outputHandle, $row, "\t");
    }
}

