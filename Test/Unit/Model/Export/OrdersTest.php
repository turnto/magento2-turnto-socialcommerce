<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use ArrayIterator;
use DateTime;
use Exception;
use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\FilterBuilderFactory;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Api\SortOrderBuilderFactory;
use Magento\Framework\DB\Select;
use Magento\Framework\Filesystem\File\WriteInterface;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Sales\Api\Data\OrderItemSearchResultInterface;
use Magento\Sales\Api\Data\ShipmentSearchResultInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Item as ShipmentItem;
use Magento\Sales\Model\ResourceModel\Order\Address\Collection as AddressCollection;
use Magento\Sales\Model\ResourceModel\Order\Address\CollectionFactory as AddressCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Item as InvoiceItem;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection as InvoiceCollection;
use Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory as InvoiceCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Item\Collection as InvoiceItemCollection;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Item\CollectionFactory as InvoiceItemCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Item\Collection as ShipmentItemCollection;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Item\CollectionFactory as ShipmentItemCollectionFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Export\OrderLineItem;
use TurnTo\SocialCommerce\Model\Export\Orders;
use TurnTo\SocialCommerce\Model\Export\Product as ExportProduct;
use TurnTo\SocialCommerce\Model\Export\TemporaryFeedFile;
use TurnTo\SocialCommerce\Model\Product as ProductModel;

class OrdersTest extends TestCase
{
    /**
     * @var array<string, bool>
     */
    private $configFlags = [];
    /**
     * @var Monolog&MockObject
     */
    private $logger;
    /**
     * @var TemporaryFeedFile&MockObject
     */
    private $temporaryFeedFile;
    /**
     * @var StoreManagerInterface&MockObject
     */
    private $storeManager;
    /**
     * @var OrderCollectionFactory&MockObject
     */
    private $orderCollectionFactory;
    /**
     * @var ProductCollectionFactory&MockObject
     */
    private $productCollectionFactory;
    /**
     * @var AddressCollectionFactory&MockObject
     */
    private $addressCollectionFactory;
    /**
     * @var ShipmentItemCollectionFactory&MockObject
     */
    private $shipmentItemCollectionFactory;
    /**
     * @var ExportProduct
     */
    private $exportProduct;
    /**
     * @var ProductHelper
     */
    private $productHelper;

    /**
     * @var OrderItem[]
     */
    private $orderItems = [];
    /**
     * @var Shipment[]
     */
    private $shipments = [];
    /**
     * @var ShipmentItem[]
     */
    private $shipmentItems = [];
    /**
     * @var CatalogProduct[]
     */
    private $products = [];
    /**
     * @var Address[]
     */
    private $addresses = [];

    /**
     * @var int
     */
    private $orderItemQueries = 0;
    /**
     * @var int
     */
    private $shipmentQueries = 0;
    /**
     * @var array[] Each entry is [storeId, productIds]
     */
    private $productQueries = [];
    /**
     * @var array[] Each entry is the parent_id filter of a shipment item query
     */
    private $shipmentItemQueries = [];
    /**
     * @var array[] Each entry is [field, value, condition] passed to SearchCriteriaBuilder::addFilter
     */
    private $searchFilters = [];
    /**
     * @var int
     */
    private $addressQueries = 0;
    /**
     * @var bool
     */
    private $useChildSku = false;
    /**
     * @var Invoice[]
     */
    private $invoices = [];
    /**
     * @var InvoiceItem[]
     */
    private $invoiceItems = [];
    /**
     * @var array[] Each entry is the order_id filter of an invoice query
     */
    private $invoiceQueries = [];

    protected function setUp(): void
    {
        $this->useChildSku = false;
        $this->invoices = [];
        $this->invoiceItems = [];
        $this->invoiceQueries = [];
        $this->configFlags = [];
        $this->orderItems = [];
        $this->shipments = [];
        $this->shipmentItems = [];
        $this->products = [];
        $this->addresses = [];
        $this->orderItemQueries = 0;
        $this->shipmentQueries = 0;
        $this->productQueries = [];
        $this->shipmentItemQueries = [];
        $this->searchFilters = [];

        $this->addressQueries = 0;

        $this->logger = $this->createStub(Monolog::class);
        $this->temporaryFeedFile = $this->createStub(TemporaryFeedFile::class);
        $this->storeManager = $this->createStub(StoreManagerInterface::class);
        $this->orderCollectionFactory = $this->createStub(OrderCollectionFactory::class);
        $this->productCollectionFactory = $this->createStub(ProductCollectionFactory::class);
        $this->addressCollectionFactory = $this->createStub(AddressCollectionFactory::class);
        $this->shipmentItemCollectionFactory = $this->createStub(ShipmentItemCollectionFactory::class);
        $this->exportProduct = $this->createStub(ExportProduct::class);
        $this->productHelper = $this->createStub(ProductHelper::class);

        $this->productCollectionFactory->method('create')->willReturnCallback(function () {
            return $this->createProductCollection();
        });
        $this->shipmentItemCollectionFactory->method('create')->willReturnCallback(function () {
            return $this->createShipmentItemCollection();
        });
        $this->addressCollectionFactory->method('create')->willReturnCallback(function () {
            $this->addressQueries++;
            $collection = $this->createStub(AddressCollection::class);
            $collection->method('addFieldToFilter')->willReturnSelf();
            $collection->method('getIterator')->willReturnCallback(function () {
                return new ArrayIterator($this->addresses);
            });

            return $collection;
        });
    }

    public function testGetItemDataForOrdersLoadsRelatedEntitiesOnceForTheWholePage(): void
    {
        $orderA = $this->createOrder(1, 1);
        $orderB = $this->createOrder(2, 1);
        $this->orderItems = [
            $this->createOrderItem(11, 1, 101),
            $this->createOrderItem(12, 1, 102, 11),
            $this->createOrderItem(21, 2, 102),
        ];
        $shipment = $this->createShipment(500, 1, '2026-01-02 10:00:00');
        $this->shipments = [$shipment];
        $this->shipmentItems = [$this->createShipmentItem(500, 11)];
        $this->products = [$this->createProduct(101), $this->createProduct(102)];

        $itemData = $this->createOrders()->getItemDataForOrders(
            new FakeOrderCollection([$orderA, $orderB]),
            true
        );

        $this->assertSame(1, $this->orderItemQueries);
        $this->assertSame(1, $this->shipmentQueries);
        $this->assertSame([[1, [101, 102]]], $this->productQueries);
        $this->assertSame([[500]], $this->shipmentItemQueries);
        $this->assertContains(['order_id', [1, 2], 'in'], $this->searchFilters);

        $this->assertSame([1, 2], array_keys($itemData));
        $this->assertSame(['1.11'], array_keys($itemData[1]), 'Child items are represented by their parent line.');
        $this->assertSame('2026-01-02 10:00:00', $itemData[1]['1.11'][Orders::SHIP_DATE_FIELD_ID]);
        $this->assertSame($this->products[0], $itemData[1]['1.11'][Orders::PRODUCT_FIELD_ID]);
        $this->assertSame('', $itemData[2]['2.21'][Orders::SHIP_DATE_FIELD_ID]);
        $this->assertSame([$this->shipmentItems[0]], $shipment->getItems());
    }

    public function testGetItemDataForOrdersLoadsProductsInEachOrdersStoreScope(): void
    {
        $this->orderItems = [
            $this->createOrderItem(11, 1, 101),
            $this->createOrderItem(21, 2, 102),
        ];
        $this->products = [$this->createProduct(101), $this->createProduct(102)];

        $this->createOrders()->getItemDataForOrders(
            [$this->createOrder(1, 1), $this->createOrder(2, 2)],
            true
        );

        $this->assertSame([[1, [101]], [2, [102]]], $this->productQueries);
    }

    public function testGetItemDataForOrdersLogsAndSkipsAnOrderThatFails(): void
    {
        $failingStore = $this->createStub(Store::class);
        $failingStore->method('getCode')->willThrowException(new Exception('store failure'));
        $this->orderItems = [
            $this->createOrderItem(11, 1, 101),
            $this->createOrderItem(21, 2, 101),
        ];
        $this->products = [$this->createProduct(101)];

        $this->logger = $this->createMock(Monolog::class);
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->isString(),
                $this->callback(function (array $context) {
                    return $context['order_id'] === 2
                        && $context['store_id'] === 1
                        && $context['exception'] instanceof Exception;
                })
            );

        $itemData = $this->createOrders()->getItemDataForOrders(
            [$this->createOrder(1, 1), $this->createOrder(2, 1, $failingStore)],
            false
        );

        $this->assertSame([1], array_keys($itemData));
    }

    public function testGetItemDataForOrdersReturnsNothingForAnEmptyPage(): void
    {
        $this->assertSame([], $this->createOrders()->getItemDataForOrders(new FakeOrderCollection([]), true));
        $this->assertSame(0, $this->orderItemQueries);
        $this->assertSame([], $this->productQueries);
    }

    public function testGetItemDataUsesSuppliedShipmentsAndProductsWithoutQuerying(): void
    {
        $order = $this->createOrder(1, 1);
        $order->setItems([$this->createOrderItem(11, 1, 101)]);
        $product = $this->createProduct(101);

        $itemData = $this->createOrders()->getItemData($order, true, [], [101 => $product]);

        $this->assertSame(0, $this->shipmentQueries);
        $this->assertSame([], $this->productQueries);
        $this->assertSame($product, $itemData['1.11'][Orders::PRODUCT_FIELD_ID]);
    }

    public function testGetItemDataLoadsShipmentsAndProductsWhenCalledWithTwoArguments(): void
    {
        $order = $this->createOrder(1, 1);
        $order->setItems([$this->createOrderItem(11, 1, 101), $this->createOrderItem(12, 1, 999)]);
        $this->shipments = [$this->createShipment(500, 1, '2026-01-02 10:00:00')];
        $this->shipmentItems = [$this->createShipmentItem(500, 11)];
        $this->products = [$this->createProduct(101)];

        $itemData = $this->createOrders()->getItemData($order, true);

        $this->assertSame(1, $this->shipmentQueries);
        $this->assertSame([[1, [101, 999]]], $this->productQueries);
        $this->assertSame(['1.11'], array_keys($itemData), 'Items whose product no longer exists are skipped.');
        $this->assertSame('2026-01-02 10:00:00', $itemData['1.11'][Orders::SHIP_DATE_FIELD_ID]);
    }

    public function testGetItemDataExcludesUnshippedItemsWhenConfigured(): void
    {
        $this->configFlags[Config::ORDER_EXCLUDE_ITEMS_WITHOUT_DELIVERY_DATE] = true;
        $order = $this->createOrder(1, 1);
        $order->setItems([$this->createOrderItem(11, 1, 101), $this->createOrderItem(12, 1, 102)]);
        $shipment = $this->createShipment(500, 1, '2026-01-02 10:00:00');
        $shipment->setItems([$this->createShipmentItem(500, 11)]);
        $products = [101 => $this->createProduct(101), 102 => $this->createProduct(102)];

        $orders = $this->createOrders();

        $this->assertSame(['1.11'], array_keys($orders->getItemData($order, false, [$shipment], $products)));
        $this->assertSame(
            ['1.11', '1.12'],
            array_keys($orders->getItemData($order, true, [$shipment], $products)),
            'Forced exports include every item.'
        );
    }

    public function testGetItemDataForOrdersUsesTheInvoiceDateAsTheDeliveryDateOfVirtualItems(): void
    {
        $this->orderItems = [
            $this->createVirtualOrderItem(11, 1, 101),
            $this->createVirtualOrderItem(12, 1, 102),
            $this->createOrderItem(13, 1, 103),
            $this->createOrderItem(21, 2, 103),
        ];
        $this->products = [$this->createProduct(101), $this->createProduct(102), $this->createProduct(103)];
        $this->invoices = [
            $this->createInvoice(700, 1, '2026-01-03 09:00:00'),
            $this->createInvoice(701, 1, '2026-01-02 09:00:00'),
        ];
        $this->invoiceItems = [
            $this->createInvoiceItem(700, 11),
            $this->createInvoiceItem(701, 11),
            $this->createInvoiceItem(701, 12, 0.0),
            $this->createInvoiceItem(701, 13),
        ];

        $itemData = $this->createOrders()->getItemDataForOrders(
            new FakeOrderCollection([$this->createOrder(1, 1), $this->createOrder(2, 1)]),
            true
        );

        $this->assertSame([[1]], $this->invoiceQueries, 'Invoices are only loaded for orders with virtual items.');
        $this->assertSame(
            '2026-01-02 09:00:00',
            $itemData[1]['1.11'][Orders::SHIP_DATE_FIELD_ID],
            'The earliest invoice containing the item is its delivery date.'
        );
        $this->assertSame(
            '',
            $itemData[1]['1.12'][Orders::SHIP_DATE_FIELD_ID],
            'An invoice line with no quantity does not deliver the item.'
        );
        $this->assertSame(
            '',
            $itemData[1]['1.13'][Orders::SHIP_DATE_FIELD_ID],
            'Physical items are only delivered by a shipment.'
        );
    }

    public function testGetItemDataKeepsInvoicedVirtualItemsWhenItemsWithoutDeliveryDateAreExcluded(): void
    {
        $this->configFlags[Config::ORDER_EXCLUDE_ITEMS_WITHOUT_DELIVERY_DATE] = true;
        $order = $this->createOrder(1, 1);
        $order->setItems([$this->createVirtualOrderItem(11, 1, 101), $this->createVirtualOrderItem(12, 1, 102)]);
        $products = [101 => $this->createProduct(101), 102 => $this->createProduct(102)];

        $itemData = $this->createOrders()->getItemData(
            $order,
            false,
            [],
            $products,
            [11 => '2026-01-02 09:00:00']
        );

        $this->assertSame(['1.11'], array_keys($itemData), 'Virtual items that are not invoiced yet are excluded.');
        $this->assertSame('2026-01-02 09:00:00', $itemData['1.11'][Orders::SHIP_DATE_FIELD_ID]);
    }

    public function testGetItemDataLoadsInvoiceDatesWhenNotSupplied(): void
    {
        $order = $this->createOrder(1, 1);
        $order->setItems([$this->createVirtualOrderItem(11, 1, 101)]);
        $this->invoices = [$this->createInvoice(700, 1, '2026-01-02 09:00:00')];
        $this->invoiceItems = [$this->createInvoiceItem(700, 11)];

        $itemData = $this->createOrders()->getItemData($order, true, [], [101 => $this->createProduct(101)]);

        $this->assertSame([[1]], $this->invoiceQueries);
        $this->assertSame('2026-01-02 09:00:00', $itemData['1.11'][Orders::SHIP_DATE_FIELD_ID]);
    }

    public function testVirtualItemsDoNotHoldBackDeliveryDatesUntilAllItemsShip(): void
    {
        $this->configFlags[Config::ORDER_EXCLUDE_DELIVERY_DATE_ON_PARTIAL_SHIPMENT] = true;
        $order = $this->createOrder(1, 1);
        $order->setItems([
            $this->createVirtualOrderItem(11, 1, 101),
            $this->createOrderItem(12, 1, 102, null, 'simple', 'Item', 10.0, ['qty_shipped' => 1.0]),
        ]);
        $shipment = $this->createShipment(500, 1, '2026-01-04 10:00:00');
        $shipment->setItems([$this->createShipmentItem(500, 12)]);
        $products = [101 => $this->createProduct(101), 102 => $this->createProduct(102)];

        $itemData = $this->createOrders()->getItemData(
            $order,
            true,
            [$shipment],
            $products,
            [11 => '2026-01-02 09:00:00']
        );

        $this->assertSame('2026-01-02 09:00:00', $itemData['1.11'][Orders::SHIP_DATE_FIELD_ID]);
        $this->assertSame('2026-01-04 10:00:00', $itemData['1.12'][Orders::SHIP_DATE_FIELD_ID]);
    }

    public function testVirtualItemsWaitForTheRestOfTheOrderToShipWhenConfigured(): void
    {
        $this->configFlags[Config::ORDER_EXCLUDE_DELIVERY_DATE_ON_PARTIAL_SHIPMENT] = true;
        $order = $this->createOrder(1, 1);
        $order->setItems([$this->createVirtualOrderItem(11, 1, 101), $this->createOrderItem(12, 1, 102)]);
        $products = [101 => $this->createProduct(101), 102 => $this->createProduct(102)];

        $itemData = $this->createOrders()->getItemData($order, true, [], $products, [11 => '2026-01-02 09:00:00']);

        $this->assertSame('', $itemData['1.11'][Orders::SHIP_DATE_FIELD_ID]);
    }

    public function testGetItemDataAttributesGroupedLinesToTheGroupedParent(): void
    {
        $groupedOptions = [
            'info_buyRequest' => ['super_product_config' => ['product_type' => 'grouped', 'product_id' => 200]],
        ];
        $this->orderItems = [
            $this->createOrderItem(11, 1, 101, null, 'child-a', 'Item', 10.0, ['product_options' => $groupedOptions]),
            $this->createOrderItem(12, 1, 102, null, 'child-b', 'Item', 10.0, [
                'product_options' => [
                    'info_buyRequest' => [
                        'super_product_config' => ['product_type' => 'grouped', 'product_id' => 999],
                    ],
                ],
            ]),
        ];
        $grouped = $this->createProduct(200);
        $childA = $this->createProduct(101);
        $childB = $this->createProduct(102);
        $this->products = [$childA, $childB, $grouped];

        $itemData = $this->createOrders()->getItemDataForOrders(new FakeOrderCollection([$this->createOrder(1, 1)]), true);

        $this->assertSame([[1, [101, 200, 102, 999]]], $this->productQueries);
        $this->assertSame($grouped, $itemData[1]['1.11'][Orders::PRODUCT_FIELD_ID]);
        $this->assertSame($childA, $itemData[1]['1.11'][Orders::ORDERED_PRODUCT_FIELD_ID]);
        $this->assertSame(
            $childB,
            $itemData[1]['1.12'][Orders::PRODUCT_FIELD_ID],
            'Lines fall back to the ordered product when the grouped parent no longer exists.'
        );
    }

    public function testWriteOrdersToFeedSendsTheGroupedParentUrlAndSku(): void
    {
        $row = $this->writeGroupedOrderLine();

        $this->assertSame('https://example.test/grouped.html', $row[4]);
        $this->assertSame('grouped-sku', $row[9]);
    }

    public function testWriteOrdersToFeedSendsTheGroupedParentUrlWithTheChildSkuInChildSkuMode(): void
    {
        $this->useChildSku = true;

        $row = $this->writeGroupedOrderLine();

        $this->assertSame('https://example.test/grouped.html', $row[4]);
        $this->assertSame('child-sku', $row[9]);
    }

    /**
     * @return array Feed row written for one line of grouped product 200, ordered as child 101
     */
    private function writeGroupedOrderLine(): array
    {
        $order = $this->createOrder(1, 1);
        $this->orderItems = [
            $this->createOrderItem(11, 1, 101, null, 'child-sku', 'Item', 10.0, [
                'product_options' => [
                    'super_product_config' => ['product_type' => 'grouped', 'product_id' => 200],
                ],
            ]),
        ];
        $grouped = $this->createProduct(200, 'grouped-sku');
        $this->products = [$this->createProduct(101, 'child-sku'), $grouped];
        $exportProduct = $this->createStub(ExportProduct::class);
        $exportProduct->method('getProductUrl')->willReturnCallback(function ($product) use ($grouped) {
            return $product === $grouped ? 'https://example.test/grouped.html' : 'https://example.test/child.html';
        });
        $this->exportProduct = $exportProduct;

        $rows = [];
        $outputFile = $this->createStub(WriteInterface::class);
        $outputFile->method('writeCsv')->willReturnCallback(function (array $row) use (&$rows) {
            $rows[] = $row;

            return 1;
        });

        $this->createOrders()->callWriteOrdersToFeed($outputFile, new FakeOrderCollection([$order]), true);
        $this->assertCount(1, $rows);

        return $rows[0];
    }

    public function testGetProductsIndexedByIdDeduplicatesIdsAndKeysByEntityId(): void
    {
        $this->products = [$this->createProduct(10), $this->createProduct(11)];

        $productsById = $this->createOrders()->callGetProductsIndexedById([10, 11, 10, 0], 3);

        $this->assertSame([[3, [10, 11]]], $this->productQueries);
        $this->assertSame([10, 11], array_keys($productsById));
        $this->assertSame([], $this->createOrders()->callGetProductsIndexedById([], 3));
        $this->assertCount(1, $this->productQueries, 'No query is made without product ids.');
    }

    public function testPreloadShippingAddressesAttachesAddressesWithOneQuery(): void
    {
        $orderA = $this->createOrder(1, 1);
        $orderB = $this->createOrder(2, 1);
        $address = $this->createStub(Address::class);
        $address->method('getParentId')->willReturn(1);
        $this->addresses = [$address];

        $this->createOrders()->callPreloadShippingAddresses([$orderA, $orderB]);

        $this->assertSame(1, $this->addressQueries);
        $this->assertSame([$address], $orderA->getData('addresses'));
        $this->assertSame([], $orderB->getData('addresses'));
    }

    public function testWriteOrdersToFeedWritesOneRowPerLineItem(): void
    {
        $order = $this->createOrder(1, 1);
        $order->method('getIncrementId')->willReturn('100000001');
        $order->method('getCreatedAt')->willReturn('2026-01-01 08:00:00');
        $order->method('getCustomerEmail')->willReturn('jane@example.com');
        $order->method('getCustomerFirstname')->willReturn('Jane');
        $order->method('getCustomerLastname')->willReturn('Doe');
        $shippingAddress = $this->createStub(Address::class);
        $shippingAddress->method('getPostcode')->willReturn('12345');
        $order->method('getShippingAddress')->willReturn($shippingAddress);

        $this->orderItems = [$this->createOrderItem(11, 1, 101, null, 'simple', "Tee \"Blue\"\n", 19.99)];
        $product = $this->createProduct(101);
        $product->method('getSku')->willReturn('parent-sku');
        $this->products = [$product];
        $exportProduct = $this->createStub(ExportProduct::class);
        $exportProduct->method('getProductUrl')->willReturn('https://example.test/tee.html');
        $this->exportProduct = $exportProduct;
        $productHelper = $this->createStub(ProductHelper::class);
        $productHelper->method('getImageUrl')->willReturn('https://example.test/tee.jpg');
        $this->productHelper = $productHelper;

        $rows = [];
        $outputFile = $this->createStub(WriteInterface::class);
        $outputFile->method('writeCsv')->willReturnCallback(function (array $row) use (&$rows) {
            $rows[] = $row;

            return 1;
        });

        $written = $this->createOrders()->callWriteOrdersToFeed(
            $outputFile,
            new FakeOrderCollection([$order]),
            true
        );

        $this->assertSame(1, $written);
        $this->assertSame(
            [[
                '100000001',
                '2026-01-01 08:00:00',
                'jane@example.com',
                "Tee 'Blue'",
                'https://example.test/tee.html',
                0,
                '12345',
                'Jane',
                'Doe',
                'parent-sku',
                19.99,
                'https://example.test/tee.jpg',
                '',
            ]],
            $rows
        );
    }

    public function testGetOrdersFeedReturnsTheFeedContents(): void
    {
        $this->temporaryFeedFile = $this->createMock(TemporaryFeedFile::class);
        $this->temporaryFeedFile->method('create')->willReturn('/var/www/html/var/tmp/orders.tsv');
        $this->temporaryFeedFile->expects($this->once())
            ->method('readAndDelete')
            ->with('/var/www/html/var/tmp/orders.tsv')
            ->willReturn("ORDERID\n");

        $this->assertSame(
            "ORDERID\n",
            $this->createOrders()->getOrdersFeed(1, new DateTime('-1 day'), new DateTime())
        );
    }

    public function testCreateOrdersFeedFileWritesTheHeaderRowAndReturnsThePath(): void
    {
        $orderCollection = $this->createStub(OrderCollection::class);
        $select = $this->createStub(Select::class);
        $select->method('joinLeft')->willReturnSelf();
        $select->method('group')->willReturnSelf();
        $orderCollection->method('getSelect')->willReturn($select);
        $orderCollection->method('addFieldToFilter')->willReturnSelf();
        $orderCollection->method('getLastPageNumber')->willReturn(0);
        $this->orderCollectionFactory->method('create')->willReturn($orderCollection);

        $rows = [];
        $outputFile = $this->createStub(WriteInterface::class);
        $outputFile->method('writeCsv')->willReturnCallback(function (array $row) use (&$rows) {
            $rows[] = $row;

            return 1;
        });
        $this->temporaryFeedFile->method('create')->willReturnCallback(
            function (string $prefix, callable $writeRows) use ($outputFile) {
                $writeRows($outputFile);

                return '/var/www/html/var/tmp/' . $prefix . 'x.tsv';
            }
        );

        $path = $this->createOrders()->createOrdersFeedFile(1, new DateTime('-1 day'), new DateTime(), true);

        $this->assertSame('/var/www/html/var/tmp/turnto_orders_feed_x.tsv', $path);
        $this->assertCount(1, $rows);
        $this->assertSame('ORDERID', $rows[0][0]);
        $this->assertSame('DELIVERYDATE', $rows[0][12]);
    }

    public function testCronUploadFeedTransmitsAndDeletesEachEnabledStoresFeed(): void
    {
        $this->configFlags[Config::ORDER_ENABLE_FEED] = true;
        $this->stubStores();
        $this->logger = $this->createMock(Monolog::class);
        $this->temporaryFeedFile = $this->createMock(TemporaryFeedFile::class);
        $this->temporaryFeedFile->method('create')->willReturn('/tmp/orders.tsv');

        $this->temporaryFeedFile->expects($this->once())
            ->method('transmitAndDelete')
            ->with('/tmp/orders.tsv', Orders::FEED_NAME, Orders::FEED_STYLE, 'default');
        $this->logger->expects($this->never())->method('error');

        $this->createOrders()->cronUploadFeed();
    }

    public function testCronUploadFeedLogsStoreAndExceptionWhenTheFeedFails(): void
    {
        $this->configFlags[Config::ORDER_ENABLE_FEED] = true;
        $this->stubStores();
        $failure = new Exception('disk full');
        $this->logger = $this->createMock(Monolog::class);
        $this->temporaryFeedFile = $this->createMock(TemporaryFeedFile::class);
        $this->temporaryFeedFile->method('create')->willThrowException($failure);

        $this->temporaryFeedFile->expects($this->never())->method('transmitAndDelete');
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->isString(), ['storeId' => 1, 'exception' => $failure]);

        $this->createOrders()->cronUploadFeed();
    }

    public function testCronUploadFeedSkipsStoresWithTheFeedDisabled(): void
    {
        $this->stubStores();
        $this->temporaryFeedFile = $this->createMock(TemporaryFeedFile::class);

        $this->temporaryFeedFile->expects($this->never())->method('create');

        $this->createOrders()->cronUploadFeed();
    }

    private function createOrders(): TestableOrders
    {
        $config = $this->createStub(Config::class);
        $config->method('getIsEnabled')->willReturn(true);
        $config->method('getUseChildSku')->willReturnCallback(function () {
            return $this->useChildSku;
        });
        $config->method('getConfigBool')->willReturnCallback(function ($path) {
            return $this->configFlags[$path] ?? false;
        });

        $dateTimeFactory = $this->createStub(DateTimeFactory::class);
        $dateTimeFactory->method('create')->willReturnCallback(function () {
            return new DateTime();
        });

        $productModel = $this->createStub(ProductModel::class);
        $productModel->method('turnToSafeEncoding')->willReturnArgument(0);

        return new TestableOrders(
            $config,
            $this->logger,
            $dateTimeFactory,
            $this->createShipmentRepository(),
            $this->productCollectionFactory,
            $this->productHelper,
            $this->storeManager,
            $productModel,
            $this->orderCollectionFactory,
            $this->exportProduct,
            $this->createFilterBuilderFactory(),
            $this->createSearchCriteriaBuilderFactory(),
            $this->createSortOrderBuilderFactory(),
            $this->createOrderItemRepository(),
            $this->addressCollectionFactory,
            $this->shipmentItemCollectionFactory,
            $this->temporaryFeedFile,
            $this->createInvoiceCollectionFactory(),
            $this->createInvoiceItemCollectionFactory(),
            new OrderLineItem($config)
        );
    }

    private function stubStores(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getCode')->willReturn('default');
        $this->storeManager->method('getStores')->willReturn([$store]);
    }

    /**
     * Order double that keeps the items and data set on it, like the real model
     *
     * @param int $orderId
     * @param int $storeId
     * @param Store|null $store
     * @return Order
     */
    private function createOrder(int $orderId, int $storeId, ?Store $store = null): Order
    {
        if ($store === null) {
            $store = $this->createStub(Store::class);
            $store->method('getCode')->willReturn('default');
        }

        $items = [];
        $data = [];
        $order = $this->createStub(Order::class);
        $order->method('getEntityId')->willReturn($orderId);
        $order->method('getStoreId')->willReturn($storeId);
        $order->method('getStore')->willReturn($store);
        $order->method('setItems')->willReturnCallback(function ($newItems) use (&$items, $order) {
            $items = $newItems;

            return $order;
        });
        $order->method('getItems')->willReturnCallback(function () use (&$items) {
            return $items;
        });
        $order->method('setData')->willReturnCallback(function ($key, $value = null) use (&$data, $order) {
            $data[$key] = $value;

            return $order;
        });
        $order->method('getData')->willReturnCallback(function ($key = '') use (&$data) {
            return $data[$key] ?? null;
        });

        return $order;
    }

    private function createOrderItem(
        int $itemId,
        int $orderId,
        int $productId,
        ?int $parentItemId = null,
        string $sku = 'simple',
        string $name = 'Item',
        float $originalPrice = 10.0,
        array $data = []
    ): OrderItem {
        $item = $this->createStub(OrderItem::class);
        $item->method('getItemId')->willReturn($itemId);
        $item->method('getOrderId')->willReturn($orderId);
        $item->method('getProductId')->willReturn($productId);
        $item->method('getParentItemId')->willReturn($parentItemId);
        $item->method('isDeleted')->willReturn(false);
        $item->method('getSku')->willReturn($sku);
        $item->method('getName')->willReturn($name);
        $item->method('getOriginalPrice')->willReturn($originalPrice);
        $item->method('getIsVirtual')->willReturn($data['is_virtual'] ?? false);
        $item->method('getProductType')->willReturn($data['product_type'] ?? 'simple');
        $item->method('getProductOptions')->willReturn($data['product_options'] ?? []);
        $item->method('getQtyOrdered')->willReturn($data['qty_ordered'] ?? 1.0);
        $item->method('getQtyShipped')->willReturn($data['qty_shipped'] ?? 0.0);
        $item->method('getQtyCanceled')->willReturn(0.0);
        $item->method('getQtyRefunded')->willReturn(0.0);
        $item->method('getQtyReturned')->willReturn(0.0);

        return $item;
    }

    private function createVirtualOrderItem(int $itemId, int $orderId, int $productId): OrderItem
    {
        return $this->createOrderItem($itemId, $orderId, $productId, null, 'simple', 'Item', 10.0, [
            'is_virtual' => true,
            'product_type' => 'virtual',
        ]);
    }

    private function createInvoice(int $invoiceId, int $orderId, string $createdAt): Invoice
    {
        $invoice = $this->createStub(Invoice::class);
        $invoice->method('getEntityId')->willReturn($invoiceId);
        $invoice->method('getOrderId')->willReturn($orderId);
        $invoice->method('getCreatedAt')->willReturn($createdAt);

        return $invoice;
    }

    private function createInvoiceItem(int $invoiceId, int $orderItemId, float $qty = 1.0): InvoiceItem
    {
        $item = $this->createStub(InvoiceItem::class);
        $item->method('getParentId')->willReturn($invoiceId);
        $item->method('getOrderItemId')->willReturn($orderItemId);
        $item->method('getQty')->willReturn($qty);

        return $item;
    }

    private function createInvoiceCollectionFactory(): InvoiceCollectionFactory
    {
        $factory = $this->createStub(InvoiceCollectionFactory::class);
        $factory->method('create')->willReturnCallback(function () {
            $collection = $this->createStub(InvoiceCollection::class);
            $collection->method('addFieldToFilter')->willReturnCallback(
                function ($field, $condition) use ($collection) {
                    if ($field === 'order_id') {
                        $this->invoiceQueries[] = $condition['in'];
                    }

                    return $collection;
                }
            );
            $collection->method('getIterator')->willReturnCallback(function () {
                return new ArrayIterator($this->invoices);
            });

            return $collection;
        });

        return $factory;
    }

    private function createInvoiceItemCollectionFactory(): InvoiceItemCollectionFactory
    {
        $factory = $this->createStub(InvoiceItemCollectionFactory::class);
        $factory->method('create')->willReturnCallback(function () {
            $collection = $this->createStub(InvoiceItemCollection::class);
            $collection->method('addFieldToFilter')->willReturnSelf();
            $collection->method('getIterator')->willReturnCallback(function () {
                return new ArrayIterator($this->invoiceItems);
            });

            return $collection;
        });

        return $factory;
    }

    /**
     * Shipment double that keeps the items set on it, like the real model
     *
     * @param int $shipmentId
     * @param int $orderId
     * @param string $createdAt
     * @return Shipment
     */
    private function createShipment(int $shipmentId, int $orderId, string $createdAt): Shipment
    {
        $items = [];
        $shipment = $this->createStub(Shipment::class);
        $shipment->method('getEntityId')->willReturn($shipmentId);
        $shipment->method('getOrderId')->willReturn($orderId);
        $shipment->method('getCreatedAt')->willReturn($createdAt);
        $shipment->method('setItems')->willReturnCallback(function ($newItems) use (&$items, $shipment) {
            $items = $newItems;

            return $shipment;
        });
        $shipment->method('getItems')->willReturnCallback(function () use (&$items) {
            return $items;
        });

        return $shipment;
    }

    private function createShipmentItem(int $shipmentId, int $orderItemId): ShipmentItem
    {
        $item = $this->createStub(ShipmentItem::class);
        $item->method('getParentId')->willReturn($shipmentId);
        $item->method('getOrderItemId')->willReturn($orderItemId);

        return $item;
    }

    /**
     * @param int $productId
     * @return CatalogProduct
     */
    private function createProduct(int $productId, ?string $sku = null): CatalogProduct
    {
        $product = $this->createStub(CatalogProduct::class);
        $product->method('getId')->willReturn($productId);
        if ($sku !== null) {
            $product->method('getSku')->willReturn($sku);
        }

        return $product;
    }

    private function createProductCollection(): ProductCollection
    {
        $storeId = null;
        $collection = $this->createStub(ProductCollection::class);
        $collection->method('setStoreId')->willReturnCallback(function ($id) use (&$storeId, $collection) {
            $storeId = $id;

            return $collection;
        });
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addIdFilter')->willReturnCallback(function ($ids) use (&$storeId, $collection) {
            $this->productQueries[] = [$storeId, $ids];

            return $collection;
        });
        $collection->method('getIterator')->willReturnCallback(function () {
            return new ArrayIterator($this->products);
        });

        return $collection;
    }

    private function createShipmentItemCollection(): ShipmentItemCollection
    {
        $collection = $this->createStub(ShipmentItemCollection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(function ($field, $condition) use ($collection) {
            $this->shipmentItemQueries[] = $condition['in'];

            return $collection;
        });
        $collection->method('getIterator')->willReturnCallback(function () {
            return new ArrayIterator($this->shipmentItems);
        });

        return $collection;
    }

    private function createOrderItemRepository(): OrderItemRepositoryInterface
    {
        $repository = $this->createStub(OrderItemRepositoryInterface::class);
        $repository->method('getList')->willReturnCallback(function () {
            $this->orderItemQueries++;
            $result = $this->createStub(OrderItemSearchResultInterface::class);
            $result->method('getItems')->willReturn($this->orderItems);

            return $result;
        });

        return $repository;
    }

    private function createShipmentRepository(): ShipmentRepositoryInterface
    {
        $repository = $this->createStub(ShipmentRepositoryInterface::class);
        $repository->method('getList')->willReturnCallback(function () {
            $this->shipmentQueries++;
            $result = $this->createStub(ShipmentSearchResultInterface::class);
            $result->method('getItems')->willReturn($this->shipments);
            $result->method('getTotalCount')->willReturn(count($this->shipments));

            return $result;
        });

        return $repository;
    }

    private function createSearchCriteriaBuilderFactory(): SearchCriteriaBuilderFactory
    {
        $factory = $this->createStub(SearchCriteriaBuilderFactory::class);
        $factory->method('create')->willReturnCallback(function () {
            $builder = $this->createStub(SearchCriteriaBuilder::class);
            $builder->method('addFilter')->willReturnCallback(
                function ($field, $value, $condition = 'eq') use ($builder) {
                    $this->searchFilters[] = [$field, $value, $condition];

                    return $builder;
                }
            );
            $builder->method('addFilters')->willReturnSelf();
            $builder->method('setPageSize')->willReturnSelf();
            $builder->method('setCurrentPage')->willReturnSelf();
            $builder->method('addSortOrder')->willReturnSelf();
            $builder->method('create')->willReturn($this->createStub(SearchCriteria::class));

            return $builder;
        });

        return $factory;
    }

    private function createFilterBuilderFactory(): FilterBuilderFactory
    {
        $builder = $this->createStub(FilterBuilder::class);
        $builder->method('setField')->willReturnSelf();
        $builder->method('setValue')->willReturnSelf();
        $builder->method('setConditionType')->willReturnSelf();
        $builder->method('create')->willReturn($this->createStub(Filter::class));
        $factory = $this->createStub(FilterBuilderFactory::class);
        $factory->method('create')->willReturn($builder);

        return $factory;
    }

    private function createSortOrderBuilderFactory(): SortOrderBuilderFactory
    {
        $builder = $this->createStub(SortOrderBuilder::class);
        $builder->method('setField')->willReturnSelf();
        $builder->method('setDirection')->willReturnSelf();
        $builder->method('create')->willReturn($this->createStub(SortOrder::class));
        $factory = $this->createStub(SortOrderBuilderFactory::class);
        $factory->method('create')->willReturn($builder);

        return $factory;
    }
}
