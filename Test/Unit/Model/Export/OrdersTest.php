<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\SearchResults;
use Magento\Framework\Api\FilterBuilderFactory;
use Magento\Framework\Api\SortOrderBuilderFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Export\Orders;
use TurnTo\SocialCommerce\Model\Export\Product as ExportProduct;
use TurnTo\SocialCommerce\Model\Product as ProductModel;

class OrdersTest extends TestCase
{
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;
    /**
     * @var SearchCriteriaBuilderFactory
     */
    protected $searchCriteriaBuilderFactory;
    /**
     * @var int
     */
    protected $getListCallCount = 0;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('getConfigBool')->willReturn(false);

        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->searchCriteriaBuilderFactory = $this->createMock(SearchCriteriaBuilderFactory::class);
        $this->getListCallCount = 0;
    }

    protected function createOrders(): Orders
    {
        return new Orders(
            $this->config,
            $this->createMock(Monolog::class),
            $this->createMock(DateTimeFactory::class),
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(ShipmentRepositoryInterface::class),
            $this->productRepository,
            $this->createMock(ProductHelper::class),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(ProductModel::class),
            $this->createMock(FeedClient::class),
            $this->createMock(Filesystem::class),
            $this->createMock(OrderCollectionFactory::class),
            $this->createMock(ExportProduct::class),
            $this->createMock(FilterBuilderFactory::class),
            $this->searchCriteriaBuilderFactory,
            $this->createMock(SortOrderBuilderFactory::class)
        );
    }

    /**
     * @param int $itemId
     * @param int $productId
     * @return OrderItemStub
     */
    protected function createOrderItem(int $itemId, int $productId): OrderItemStub
    {
        $item = $this->createMock(OrderItemStub::class);
        $item->method('isDeleted')->willReturn(false);
        $item->method('getParentItemId')->willReturn(null);
        $item->method('getItemId')->willReturn($itemId);
        $item->method('getProductId')->willReturn($productId);

        return $item;
    }

    /**
     * @param int $orderId
     * @param OrderItemStub[] $items
     * @return OrderStub
     */
    protected function createOrder(int $orderId, array $items): OrderStub
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getCode')->willReturn('default');

        $order = $this->createMock(OrderStub::class);
        $order->method('getEntityId')->willReturn($orderId);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getStore')->willReturn($store);
        $order->method('getItems')->willReturn($items);

        return $order;
    }

    /**
     * @param int $productId
     * @return ProductInterface
     */
    protected function createProduct(int $productId): ProductInterface
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn($productId);

        return $product;
    }

    /**
     * Configure the repository to return the given products and count how often it is queried.
     *
     * @param ProductInterface[] $products
     * @return void
     */
    protected function stubProductRepository(array $products): void
    {
        $builder = $this->createMock(SearchCriteriaBuilder::class);
        $builder->method('addFilter')->willReturnSelf();
        $builder->method('setPageSize')->willReturnSelf();
        $builder->method('create')->willReturn($this->createMock(SearchCriteria::class));
        $this->searchCriteriaBuilderFactory->method('create')->willReturn($builder);

        $searchResults = $this->createMock(SearchResults::class);
        $searchResults->method('getItems')->willReturn($products);

        $this->productRepository->method('getList')->willReturnCallback(
            function () use ($searchResults) {
                $this->getListCallCount++;

                return $searchResults;
            }
        );
    }

    public function testGetItemDataUsesPreloadedProductsAndDoesNotQueryRepository(): void
    {
        $order = $this->createOrder(100, [
            $this->createOrderItem(1, 10),
            $this->createOrderItem(2, 11),
        ]);

        // Repository must never be touched when products are preloaded for the batch.
        $this->productRepository->expects($this->never())->method('getList');
        $this->searchCriteriaBuilderFactory->expects($this->never())->method('create');

        $productsById = [
            10 => $this->createProduct(10),
            11 => $this->createProduct(11),
        ];

        $orders = $this->createOrders();
        $items = $orders->getItemData($order, false, [], $productsById);

        $this->assertCount(2, $items);
        $this->assertSame($productsById[10], $items['100.1'][Orders::PRODUCT_FIELD_ID]);
        $this->assertSame($productsById[11], $items['100.2'][Orders::PRODUCT_FIELD_ID]);
    }

    public function testGetItemDataLoadsAllProductsInASingleQueryWhenNotPreloaded(): void
    {
        $order = $this->createOrder(100, [
            $this->createOrderItem(1, 10),
            $this->createOrderItem(2, 11),
        ]);

        $this->stubProductRepository([
            $this->createProduct(10),
            $this->createProduct(11),
        ]);

        $orders = $this->createOrders();
        $items = $orders->getItemData($order, false);

        // A single bulk query, not one per line item.
        $this->assertSame(1, $this->getListCallCount);
        $this->assertCount(2, $items);
    }

    public function testGetProductsIndexedByIdReturnsProductsKeyedByEntityId(): void
    {
        $this->stubProductRepository([
            $this->createProduct(10),
            $this->createProduct(11),
        ]);

        $orders = $this->createOrders();
        $productsById = $orders->getProductsIndexedById([10, 11, 10]);

        $this->assertSame(1, $this->getListCallCount);
        $this->assertArrayHasKey(10, $productsById);
        $this->assertArrayHasKey(11, $productsById);
        $this->assertSame(10, (int) $productsById[10]->getId());
    }
}
