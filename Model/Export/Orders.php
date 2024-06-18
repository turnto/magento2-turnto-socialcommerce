<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Export;

use DateTime;
use Exception;
use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Api\AbstractSimpleObject;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Helper\Config;
use TurnTo\SocialCommerce\Helper\Product as TurnToProductHelper;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Export\Product as ExportProduct;

class Orders
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
     * Default page size
     */
    const DEFAULT_PAGE_SIZE = 25;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderService;

    /**
     * @var ShipmentRepositoryInterface
     */
    protected $shipmentsService;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var ProductHelper
     */
    protected $productHelper;

    /**
     * @var DirectoryList
     */
    protected $directoryList;
    /**
     * @var TurnToProductHelper
     */
    protected $turnToProductHelper;

    /**
     * @var File
     */
    protected $fileSystem;

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
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;
    /**
     * @var SortOrderBuilder
     */
    protected $sortOrderBuilder;
    /**
     * @var FilterBuilder
     */
    protected $filterBuilder;

    /**
     * Orders constructor.
     *
     * @param Config $config
     * @param Monolog $logger
     * @param DateTimeFactory $dateTimeFactory
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param ShipmentRepositoryInterface $shipmentsService
     * @param ProductRepository $productRepository
     * @param Product $productHelper
     * @param StoreManagerInterface $storeManager
     * @param DirectoryList $directoryList
     * @param TurnToProductHelper $turnToProductHelper
     * @param FeedClient $feedClient
     * @param File $fileSystem
     * @param OrderCollectionFactory $orderCollection
     * @param ExportProduct $exportProduct
     * @param FilterBuilder $filterBuilder
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     */
    public function __construct(
        Config $config,
        Monolog $logger,
        DateTimeFactory $dateTimeFactory,
        OrderRepositoryInterface $orderRepositoryInterface,
        ShipmentRepositoryInterface $shipmentsService,
        ProductRepository $productRepository,
        Product $productHelper,
        StoreManagerInterface $storeManager,
        DirectoryList $directoryList,
        TurnToProductHelper $turnToProductHelper,
        FeedClient $feedClient,
        File $fileSystem,
        OrderCollectionFactory $orderCollection,
        ExportProduct $exportProduct,
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->orderService = $orderRepositoryInterface;
        $this->shipmentsService = $shipmentsService;
        $this->productRepository = $productRepository;
        $this->productHelper = $productHelper;
        $this->storeManager = $storeManager;
        $this->directoryList = $directoryList;
        $this->turnToProductHelper = $turnToProductHelper;
        $this->feedClient = $feedClient;
        $this->fileSystem = $fileSystem;
        $this->orderCollectionFactory = $orderCollection;
        $this->exportProduct = $exportProduct;
        $this->filterBuilder = $filterBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
    }

    /**
     * CRON handler that sends the last 2 days of orders to TurnTo
     * @return void
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
                        $this->dateTimeFactory->create('now', new \DateTimeZone('UTC'))->sub(new \DateInterval('P25D')),
                        $this->dateTimeFactory->create(
                            'now',
                            new \DateTimeZone('UTC')

                        )
                    );
                    $this->feedClient->transmitFeedFile($orderFeed, self::FEED_NAME, self::FEED_STYLE, $store->getCode());
                } catch (Exception $e) {
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
     * @param DateTime $fromDate
     * @param DateTime $toDate
     * @param bool $forceIncludeAllItems
     *
     * @return null|string
     */
    public function getOrdersFeed(
        $storeId,
        DateTime $fromDate,
        DateTime $toDate,
        bool $forceIncludeAllItems = false
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

        } catch (Exception $e) {
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
     * @param $orderList
     * @param                                       $outputHandle
     * @param bool $forceIncludeAllItems
     */
    public function writeOrdersFeed($orderList, $outputHandle, $forceIncludeAllItems, $pageSize = 5000)
    {
        $orderList->setPageSize($pageSize);
        $pageLimit = $orderList->getLastPageNumber();
        $page = 1;

        while ($pageLimit < $page){
            $orders = $orderList->setPage($page,$pageSize);
            $this->writeOrdersToFeed($outputHandle, $orders, $forceIncludeAllItems);
            $page++;
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
        if (empty($orders)) {
            return 0;
        }

        $numberOfRecordsWritten = 0;
        foreach ($orders as $order) {
            try {
                $this->writeOrderToFeed($outputHandle, $order, $forceIncludeAllItems);
            } catch (Exception $e) {
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
     * @param OrderInterface $order
     * @param bool $forceIncludeAllItems
     * @throws NoSuchEntityException
     */
    protected function writeOrderToFeed($outputHandle, OrderInterface $order, $forceIncludeAllItems)
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
     * @param OrderInterface $order
     * @param bool $forceIncludeAllItems
     *
     * @return array
     */
    public function getItemData(OrderInterface $order, $forceIncludeAllItems)
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
                        self::SHIP_DATE_FIELD_ID => $order->getShipCreatedAt()
                    ];
                }
            } catch (NoSuchEntityException $e) {
                // Do nothing
            }
        }
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
     * @param $outputHandle
     * @param OrderInterface $order
     * @param OrderItemInterface $lineItem
     * @param Product $product
     * @param $lineItemNumber
     * @param $shipmentDate
     * @return void
     */
    protected function writeLineToFeed(
        $outputHandle,
        OrderInterface $order,
        OrderItemInterface $lineItem,
        Product $product,
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
        $row[] = $this->turnToProductHelper->turnToSafeEncoding($sku);

        $row[] = $lineItem->getOriginalPrice();
        $row[] = $this->productHelper->getImageUrl($product);
        $row[] = $shipmentDate;

        fputcsv($outputHandle, $row, "\t");
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
            ['ship_created_at'=>'created_at']
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
}
