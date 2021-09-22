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

use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactoryAlias;
use Magento\Framework\App\Filesystem\DirectoryList;

class Orders
{

    CONST MAIN_TABLE_PREFIX = 'main_table.';

    const UPDATED_AT_FIELD_ID = 'updated_at';

    const STORE_ID_FIELD_ID = 'store_id';

    const FEED_NAME = 'historical-orders-feed.tsv';

    const FEED_STYLE = 'tab-style.1';

    const FEED_MIME = 'text/tab-separated-values';

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
     * @param       $outputHandle
     * @param array $orderData
     * @param bool $forceIncludeAllItems
     */
    protected function writeOrderToFeed($outputHandle, $orderData)
    {
        if (empty($orderData)) {
            return;
        }

        foreach ($orderData as $item) {
            $this->writeLineToFeed(
                $outputHandle,
                $item
            );
        }
    }


    /**
     * @param $outputHandle
     * @param $orderData
     * @param \Magento\Sales\Api\Data\OrderItemInterface $lineItem
     * @param \Magento\Catalog\Model\Product $product
     * @param $lineItemNumber
     * @param $shipmentDate
     */
    protected function writeLineToFeed(
        $outputHandle,
        $orderData
    ) {
        $row = [];

        $row[] = $orderData["ORDERID"];
        $row[] = $orderData["ORDERDATE"];
        $row[] = $orderData["EMAIL"];
        $row[] = $orderData["ITEMTITLE"];
        $row[] = $orderData["ITEMURL"];
        $row[] = $orderData["FIRSTNAME"];
        $row[] = $orderData["LASTNAME"];
        $row[] = $orderData["SKU"];
        $row[] = $orderData["ITEMLINEID"];
        $row[] = $orderData["ZIP"];
        $row[] = $orderData["PRICE"];
        $row[] = $orderData["ITEMIMAGEURL"];
        $row[] = $orderData["DELIVERYDATE"];

        fputcsv($outputHandle, $row, "\t");
    }

    public function getOrders($storeId, $fromDate, $toDate) {
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

    public function formatOrderData($orders, $forceIncludeAllItems = false) {
        $orderItems = [];

        $orderCounter = 0;
        foreach($orders as $order) {
            $orderData = $this->getItemData($order, $orderCounter, $forceIncludeAllItems);
            $orderItems[] = $orderData;
        }

        return $orderItems;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param bool $forceIncludeAllItems
     *
     * @return array|mixed
     */
    protected function getItemData(\Magento\Sales\Api\Data\OrderInterface $order, $orderCounter, $forceIncludeAllItems)
    {
        $items = [];
        $orderId = $order->getEntityId();

        foreach ($order->getItems() as $item) {
            try {
                if (!$item->isDeleted() && !$item->getParentItemId()) {
                    $itemId = $item->getItemId();
                    $key = "$orderId.$itemId";
                    $product = $this->productRepository->getById($item->getProductId());
                    $sku = $this->config->getUseChildSku($order->getStoreId()) ? $item->getSku() : $product->getSku();
                    $items[$key] = [
                        "ORDERID" => $order->getIncrementId(),
                        "ORDERDATE" => $order->getCreatedAt(),
                        "EMAIL" => $order->getCustomerEmail(),
                        "ITEMTITLE" => $item->getName(),
                        "ITEMURL" => $this->productHelper->getProductUrl($item, $order->getStoreId()),
                        "FIRSTNAME" => $order->getCustomerFirstname(),
                        "LASTNAME" => $order->getCustomerLastname(),
                        "SKU" => $this->turnToProductHelper->turnToSafeEncoding($sku),
                        "ITEMLINEID" => $orderCounter,
                        "ZIP" =>  $this->orderExportHelper->getOrderPostCode($order),
                        "PRICE" => $item->getOriginalPrice(),
                        "ITEMIMAGEURL" => $this->productHelper->getImageUrl($product),
                        "DELIVERYDATE" => ''
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
        $searchCriteria = $this->orderExportHelper->getShipmentSearchCriteriaForOrder($orderId, $storeId);
        $shipmentsList = $this->shipmentsService->getList($searchCriteria);
        $pageLimit = $shipmentsList->getLastPageNumber();
        $pageSize = $shipmentsList->getPageSize();

        // If this setting is on, we only send shipment data if the whole order has shipped
        $configExcludeDeliveryDateUntilAllItemsShipped = $this->config->getExcludeDeliveryDateUntilAllItemsShipped($storeId);
        $allItemsShipped = false;
        if ($configExcludeDeliveryDateUntilAllItemsShipped) {
            $allItemsShipped = $this->orderExportHelper->getAllItemsShipped($orderId);
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
                            $itemData[$key]["DELIVERYDATE"] = $shipment->getCreatedAt();
                        }
                    }
                }
            }
        }

        return $itemData;
    }

    /**
     * @param $storeId
     * @param $orderData
     * @param bool $forceIncludeAllItems
     * @return false|string|null
     */
    public function generateOrdersFeed(
        $storeId,
        $orderData,
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
                    'FIRSTNAME',
                    'LASTNAME',
                    'SKU',
                    'ITEMLINEID',
                    'ZIP',
                    'PRICE',
                    'ITEMIMAGEURL',
                    'DELIVERYDATE'
                ],
                "\t"
            );

            foreach($orderData as $orderDatum) {
                $this->writeOrderToFeed($outputHandle, $orderDatum, $forceIncludeAllItems);
            }
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

}
