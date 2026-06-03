<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\File\WriteInterface;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Export\Orders;
use TurnTo\SocialCommerce\Model\Product;

class CanceledOrdersTest extends TestCase
{
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var Orders
     */
    protected $ordersExport;
    /**
     * @var Product
     */
    protected $product;
    /**
     * @var int
     */
    protected $productPreloadCallCount = 0;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('getUseChildSku')->willReturn(false);

        $this->product = $this->createMock(Product::class);
        $this->product->method('turnToSafeEncoding')->willReturnArgument(0);

        $this->ordersExport = $this->createMock(Orders::class);
        $this->ordersExport->method('getShipmentsIndexedByOrderId')->willReturn([]);

        $this->productPreloadCallCount = 0;
        $this->ordersExport->method('getProductsIndexedById')->willReturnCallback(
            function () {
                $this->productPreloadCallCount++;

                return [];
            }
        );
    }

    protected function createCanceledOrders(): TestableCanceledOrders
    {
        return new TestableCanceledOrders(
            $this->config,
            $this->createMock(Monolog::class),
            $this->createMock(DateTimeFactory::class),
            $this->createMock(StoreManagerInterface::class),
            $this->product,
            $this->createMock(FeedClient::class),
            $this->createMock(Filesystem::class),
            $this->ordersExport,
            $this->createMock(OrderCollectionFactory::class)
        );
    }

    protected function createOrder(int $orderId, string $incrementId): OrderStub
    {
        $item = $this->createMock(OrderItemStub::class);
        $item->method('isDeleted')->willReturn(false);
        $item->method('getParentItemId')->willReturn(null);
        $item->method('getItemId')->willReturn($orderId);
        $item->method('getProductId')->willReturn($orderId + 100);

        $order = $this->createMock(OrderStub::class);
        $order->method('getEntityId')->willReturn($orderId);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getIncrementId')->willReturn($incrementId);
        $order->method('getItems')->willReturn([$item]);

        return $order;
    }

    public function testWriteOrdersToFeedPreloadsProductsOnceAndStreamsRowsViaWriteInterface(): void
    {
        $orderA = $this->createOrder(1, '100000001');
        $orderB = $this->createOrder(2, '100000002');
        $collection = new FakeOrderCollection([$orderA, $orderB]);

        $lineItem = $this->createMock(OrderItemStub::class);
        $product = $this->createMock(ProductInterface::class);
        $product->method('getSku')->willReturn('simple');

        // Each order yields one feed line item.
        $this->ordersExport->method('getItemData')->willReturnCallback(
            function () use ($lineItem, $product) {
                return [
                    [
                        Orders::LINE_ITEM_FIELD_ID => $lineItem,
                        Orders::PRODUCT_FIELD_ID => $product,
                        Orders::SHIP_DATE_FIELD_ID => '',
                    ],
                ];
            }
        );

        $writtenRows = [];
        $outputFile = $this->createMock(WriteInterface::class);
        $outputFile->method('writeCsv')->willReturnCallback(
            function ($row) use (&$writtenRows) {
                $writtenRows[] = $row;

                return strlen(implode("\t", $row));
            }
        );

        $this->createCanceledOrders()->callWriteOrdersToFeed($outputFile, $collection, false);

        // Products are loaded once for the whole batch, not once per order.
        $this->assertSame(1, $this->productPreloadCallCount);
        $this->assertSame(
            [
                ['100000001', 'simple'],
                ['100000002', 'simple'],
            ],
            $writtenRows
        );
    }

    public function testWriteOrdersToFeedDoesNothingForEmptyCollection(): void
    {
        $collection = new FakeOrderCollection([]);

        $this->ordersExport->expects($this->never())->method('getProductsIndexedById');

        $outputFile = $this->createMock(WriteInterface::class);
        $outputFile->expects($this->never())->method('writeCsv');

        $this->createCanceledOrders()->callWriteOrdersToFeed($outputFile, $collection, false);
    }
}
