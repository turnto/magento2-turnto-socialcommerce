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

    /**#@+
     * TurnTo Transmission Constants
     */
    const FEED_NAME = 'historical-orders-feed';

    const FEED_STYLE = 'tab-style.1';

    const FEED_MIME = 'text/tab-separated-values';
    /**#@-*/

    /**
     * Path to temp file used for writing, maximum of 16MB is used as in memory buffer
     */
    const TEMP_FILE_PATH = 'php://temp/maxmemory:16384';

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
     * @param \TurnTo\SocialCommerce\Helper\Config $config
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \TurnTo\SocialCommerce\Logger\Monolog $logger
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Framework\Intl\DateTimeFactory $dateTimeFactory
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
        \Magento\Framework\Intl\DateTimeFactory $dateTimeFactory,
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

    /**
     * CRON handler that sends the last 2 days of orders to TurnTo
     */
    public function cronUploadFeed()
    {
        foreach ($this->storeManager->getStores() as $store) {
            if (
                $this->config->getIsEnabled($store->getCode())
                && $this->config->getIsHistoricalOrdersFeedEnabled($store->getCode())
            ) {
                try {
                    $feedData = $this->getOrdersFeed(
                        $store->getId(),
                        $this->dateTimeFactory->create('now', new \DateTimeZone('UTC'))->sub(new \DateInterval('P2D'))
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
     * @param $storeId
     * @param $startDateTime
     * @return null|string
     */
    public function getOrdersFeed($storeId, $startDateTime) {
        $csvData = null;
        $searchCriteria = $this->getOrdersSearchCriteria($storeId, $startDateTime);

        try {
            $outputHandle = fopen(self::TEMP_FILE_PATH, 'w');
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
            $this->writeOrdersFeed($searchCriteria, $outputHandle);
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
     * @param $storeId
     * @param $startDateTime
     * @return \Magento\Framework\Api\SearchCriteria
     */
    public function getOrdersSearchCriteria($storeId, $startDateTime)
    {
        if (!isset($startDateTime)) {
            $startDateTime = $this->dateTimeFactory->create('1900-1-1T00:00:00', new \DateTimeZone('UTC'));
        } elseif (is_string($startDateTime)) {
            $startDateTime = $this->dateTimeFactory->create($startDateTime, new \DateTimeZone('UTC'));
        }

        return $this->getSearchCriteria(
            $this->getSortOrder(self::UPDATED_AT_FIELD_ID),
            [
                $this->getFilter(self::STORE_ID_FIELD_ID, $storeId, 'eq'),
                $this->getFilter(self::UPDATED_AT_FIELD_ID, $startDateTime->format(DATE_ISO8601), 'gteq')
            ]
        );
    }

    /**
     * @param $feedData
     * @param \Magento\Store\Api\Data\StoreInterface $store
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
                        'siteKey' => $this->config
                            ->getSiteKey($store->getCode()),
                        'authKey' => $this->encryptor->decrypt($this->config
                            ->getAuthorizationKey($store->getCode())),
                        'feedStyle' => self::FEED_STYLE
                    ]
                )
                ->setFileUpload(self::FEED_NAME, 'file', $feedData, self::FEED_MIME);

            $response = $zendClient->request();

            if (!$response || !$response->isSuccessful()) {
                throw new \Exception('TurnTo catalog feed submission failed silently');
            }

            $body = $response->getBody();

            //It is possible to get a status 200 message who's body is an error message from TurnTo
            if (empty($body) || $body != Catalog::TURNTO_SUCCESS_RESPONSE) {
                throw new \Exception("TurnTo catalog feed submission failed with message: $body" );
            }
        } catch (\Exception $e) {
            $this->logger->error('An error occurred while transmitting the catalog feed to TurnTo',
                [
                    'exception' => $e,
                    'response' => $response ? 'null' : $response->getBody()
                ]
            );
            throw $e;
        }
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
        $row[] = $this->getOrderPostCode($order);
        $row[] = $order->getCustomerFirstname();
        $row[] = $order->getCustomerLastname();
        $row[] = $product->getSku();
        $row[] = $lineItem->getOriginalPrice();
        $row[] = $this->productHelper->getImageUrl($product);
        $row[] = $shipmentDate;
        
        fputcsv($outputHandle, $row, "\t");
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return string
     */
    protected function getOrderPostCode(\Magento\Sales\Api\Data\OrderInterface $order)
    {
        $postCode = '';
        $shippingAddress = $order->getShippingAddress();
        if (isset($shippingAddress)) {
            $postCode = $shippingAddress->getPostcode();
        }

        return $postCode;
    }
}

