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
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Framework\Filesystem\File\WriteInterface;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Export\CanceledOrders;
use TurnTo\SocialCommerce\Model\Export\Orders;
use TurnTo\SocialCommerce\Model\Export\TemporaryFeedFile;
use TurnTo\SocialCommerce\Model\Product;

class CanceledOrdersTest extends TestCase
{
    /**
     * @var bool
     */
    protected $useChildSku = false;
    /**
     * @var Orders
     */
    protected $ordersExport;
    /**
     * @var Monolog
     */
    protected $logger;
    /**
     * @var TemporaryFeedFile
     */
    protected $temporaryFeedFile;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var OrderCollectionFactory
     */
    protected $orderCollectionFactory;
    /**
     * @var array[]
     */
    private $writtenRows = [];

    protected function setUp(): void
    {
        $this->useChildSku = false;
        $this->writtenRows = [];
        $this->ordersExport = $this->createStub(Orders::class);
        $this->logger = $this->createStub(Monolog::class);
        $this->temporaryFeedFile = $this->createStub(TemporaryFeedFile::class);
        $this->storeManager = $this->createStub(StoreManagerInterface::class);
        $this->orderCollectionFactory = $this->createStub(OrderCollectionFactory::class);
    }

    public function testWriteOrdersToFeedLoadsLineItemsForThePageOnceAndWritesOneRowPerItem(): void
    {
        $orderA = $this->createOrder(1, '100000001');
        $orderB = $this->createOrder(2, '100000002');
        $orders = new FakeOrderCollection([$orderA, $orderB]);

        $this->ordersExport = $this->createMock(Orders::class);
        $this->ordersExport->expects($this->once())
            ->method('getItemDataForOrders')
            ->with($orders, false)
            ->willReturn([
                1 => [$this->createItemData('child-a', 'parent-a')],
                2 => [$this->createItemData('child-b', 'parent-b'), $this->createItemData('child-c', 'parent-c')],
            ]);

        $this->createCanceledOrders()->callWriteOrdersToFeed($this->createOutputFile(), $orders, false);

        $this->assertSame(
            [
                ['100000001', 'parent-a'],
                ['100000002', 'parent-b'],
                ['100000002', 'parent-c'],
            ],
            $this->writtenRows
        );
    }

    public function testWriteOrdersToFeedUsesTheChildSkuWhenConfigured(): void
    {
        $this->useChildSku = true;
        $this->ordersExport->method('getItemDataForOrders')
            ->willReturn([1 => [$this->createItemData('child-a', 'parent-a')]]);

        $this->createCanceledOrders()->callWriteOrdersToFeed(
            $this->createOutputFile(),
            new FakeOrderCollection([$this->createOrder(1, '100000001')]),
            false
        );

        $this->assertSame([['100000001', 'child-a']], $this->writtenRows);
    }

    public function testWriteOrdersToFeedLogsTheOrderAndContinuesWhenAnOrderFails(): void
    {
        $failure = new Exception('write failure');
        $this->ordersExport->method('getItemDataForOrders')->willReturn([
            1 => [$this->createItemData('child-a', 'parent-a')],
            2 => [$this->createItemData('child-b', 'parent-b')],
        ]);
        $outputFile = $this->createStub(WriteInterface::class);
        $outputFile->method('writeCsv')->willReturnCallback(function (array $row) use ($failure) {
            if ($row[0] === '100000001') {
                throw $failure;
            }
            $this->writtenRows[] = $row;

            return 1;
        });

        $this->logger = $this->createMock(Monolog::class);
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->isString(), ['order_id' => 1, 'store_id' => 1, 'exception' => $failure]);

        $this->createCanceledOrders()->callWriteOrdersToFeed(
            $outputFile,
            new FakeOrderCollection([$this->createOrder(1, '100000001'), $this->createOrder(2, '100000002')]),
            false
        );

        $this->assertSame([['100000002', 'parent-b']], $this->writtenRows);
    }

    public function testWriteOrdersToFeedWritesNothingForAnEmptyPage(): void
    {
        $this->ordersExport->method('getItemDataForOrders')->willReturn([]);
        $outputFile = $this->createMock(WriteInterface::class);
        $outputFile->expects($this->never())->method('writeCsv');

        $this->createCanceledOrders()->callWriteOrdersToFeed($outputFile, new FakeOrderCollection([]), false);
    }

    public function testGetCanceledOrdersFeedReturnsTheFeedContents(): void
    {
        $this->stubCanceledOrderCollection();
        $this->temporaryFeedFile = $this->createMock(TemporaryFeedFile::class);
        $this->temporaryFeedFile->method('create')->willReturn('/tmp/canceled.tsv');
        $this->temporaryFeedFile->expects($this->once())
            ->method('readAndDelete')
            ->with('/tmp/canceled.tsv')
            ->willReturn("ORDERID\tSKU\n");

        $this->assertSame(
            "ORDERID\tSKU\n",
            $this->createCanceledOrders()->getCanceledOrdersFeed(1, new DateTime('-1 day'), new DateTime())
        );
    }

    public function testCreateCanceledOrdersFeedFileWritesTheHeaderAndCanceledOrders(): void
    {
        $order = $this->createOrder(1, '100000001');
        $this->stubCanceledOrderCollection([$order]);
        $this->ordersExport->method('getItemDataForOrders')
            ->willReturn([1 => [$this->createItemData('child-a', 'parent-a')]]);
        $this->temporaryFeedFile->method('create')->willReturnCallback(
            function (string $prefix, callable $writeRows) {
                $writeRows($this->createOutputFile());

                return '/tmp/' . $prefix . 'x.tsv';
            }
        );

        $path = $this->createCanceledOrders()->createCanceledOrdersFeedFile(
            1,
            new DateTime('-1 day'),
            new DateTime()
        );

        $this->assertSame('/tmp/turnto_canceled_orders_feed_x.tsv', $path);
        $this->assertSame([['ORDERID', 'SKU'], ['100000001', 'parent-a']], $this->writtenRows);
    }

    public function testCronUploadFeedTransmitsAndDeletesTheFeedForEnabledStores(): void
    {
        $this->stubStore();
        $this->stubCanceledOrderCollection();
        $this->temporaryFeedFile = $this->createMock(TemporaryFeedFile::class);
        $this->temporaryFeedFile->method('create')->willReturn('/tmp/canceled.tsv');
        $this->temporaryFeedFile->expects($this->once())
            ->method('transmitAndDelete')
            ->with('/tmp/canceled.tsv', CanceledOrders::FEED_NAME, CanceledOrders::FEED_STYLE, 'default');

        $this->createCanceledOrders(true)->cronUploadFeed();
    }

    public function testCronUploadFeedIncludesItemsWithoutADeliveryDate(): void
    {
        $this->stubStore();
        $this->stubCanceledOrderCollection([$this->createOrder(1, '100000001')]);
        $this->ordersExport = $this->createMock(Orders::class);
        $this->ordersExport->expects($this->once())
            ->method('getItemDataForOrders')
            ->with($this->anything(), true)
            ->willReturn([1 => [$this->createItemData('child-a', 'parent-a')]]);
        $this->temporaryFeedFile->method('create')->willReturnCallback(
            function (string $prefix, callable $writeRows) {
                $writeRows($this->createOutputFile());

                return '/tmp/' . $prefix . 'x.tsv';
            }
        );

        $this->createCanceledOrders(true)->cronUploadFeed();

        $this->assertSame([['ORDERID', 'SKU'], ['100000001', 'parent-a']], $this->writtenRows);
    }

    public function testWriteOrdersToFeedPassesTheOrderedProductToTheSkuResolver(): void
    {
        $item = $this->createItemData('child-a', 'parent-a');
        $orderedProduct = $this->createStub(CatalogProduct::class);
        $item[Orders::ORDERED_PRODUCT_FIELD_ID] = $orderedProduct;
        $this->ordersExport = $this->createMock(Orders::class);
        $this->ordersExport->method('getItemDataForOrders')->willReturn([1 => [$item]]);
        $this->ordersExport->expects($this->once())
            ->method('getFeedSku')
            ->with($item[Orders::LINE_ITEM_FIELD_ID], $item[Orders::PRODUCT_FIELD_ID], 1, $orderedProduct)
            ->willReturn('grouped-child');

        $this->createCanceledOrders()->callWriteOrdersToFeed(
            $this->createOutputFile(),
            new FakeOrderCollection([$this->createOrder(1, '100000001')]),
            false
        );

        $this->assertSame([['100000001', 'grouped-child']], $this->writtenRows);
    }

    public function testCronUploadFeedLogsTheStoreWhenTheFeedFails(): void
    {
        $this->stubStore();
        $this->stubCanceledOrderCollection();
        $failure = new Exception('disk full');
        $this->temporaryFeedFile->method('create')->willThrowException($failure);
        $this->logger = $this->createMock(Monolog::class);
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->isString(), ['storeId' => 1, 'exception' => $failure]);

        $this->createCanceledOrders(true)->cronUploadFeed();
    }

    public function testCronUploadFeedSkipsStoresWithTheCanceledFeedDisabled(): void
    {
        $this->stubStore();
        $this->temporaryFeedFile = $this->createMock(TemporaryFeedFile::class);
        $this->temporaryFeedFile->expects($this->never())->method('create');

        $this->createCanceledOrders(false)->cronUploadFeed();
    }

    protected function createCanceledOrders(bool $feedEnabled = false): TestableCanceledOrders
    {
        $config = $this->createStub(Config::class);
        $config->method('getIsEnabled')->willReturn(true);
        $config->method('getConfigBool')->willReturn($feedEnabled);
        $config->method('getUseChildSku')->willReturnCallback(function () {
            return $this->useChildSku;
        });

        $product = $this->createStub(Product::class);
        $product->method('turnToSafeEncoding')->willReturnArgument(0);

        $this->ordersExport->method('getFeedSku')->willReturnCallback(
            function ($lineItem, $catalogProduct) {
                return $this->useChildSku ? $lineItem->getSku() : $catalogProduct->getSku();
            }
        );

        $dateTimeFactory = $this->createStub(DateTimeFactory::class);
        $dateTimeFactory->method('create')->willReturnCallback(function () {
            return new DateTime();
        });

        return new TestableCanceledOrders(
            $config,
            $this->logger,
            $dateTimeFactory,
            $this->storeManager,
            $product,
            $this->ordersExport,
            $this->orderCollectionFactory,
            $this->temporaryFeedFile
        );
    }

    protected function createOrder(int $orderId, string $incrementId): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getEntityId')->willReturn($orderId);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getIncrementId')->willReturn($incrementId);

        return $order;
    }

    protected function createItemData(string $childSku, string $parentSku): array
    {
        $lineItem = $this->createStub(OrderItem::class);
        $lineItem->method('getSku')->willReturn($childSku);
        $product = $this->createStub(CatalogProduct::class);
        $product->method('getSku')->willReturn($parentSku);

        return [
            Orders::LINE_ITEM_FIELD_ID => $lineItem,
            Orders::PRODUCT_FIELD_ID => $product,
            Orders::SHIP_DATE_FIELD_ID => '',
        ];
    }

    protected function createOutputFile(): WriteInterface
    {
        $outputFile = $this->createStub(WriteInterface::class);
        $outputFile->method('writeCsv')->willReturnCallback(function (array $row) {
            $this->writtenRows[] = $row;

            return 1;
        });

        return $outputFile;
    }

    protected function stubStore(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getCode')->willReturn('default');
        $this->storeManager->method('getStores')->willReturn([$store]);
    }

    /**
     * @param Order[] $orders
     * @return void
     */
    protected function stubCanceledOrderCollection(array $orders = []): void
    {
        $collection = $this->createStub(OrderCollection::class);
        $collection->method('addAttributeToFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new ArrayIterator($orders));
        $this->orderCollectionFactory->method('create')->willReturn($collection);
    }
}
