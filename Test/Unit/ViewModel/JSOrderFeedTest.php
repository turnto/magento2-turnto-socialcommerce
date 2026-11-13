<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\ViewModel;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Export\OrderLineItem;
use TurnTo\SocialCommerce\Model\Product as ProductModel;
use TurnTo\SocialCommerce\ViewModel\JSOrderFeed;

class JSOrderFeedTest extends TestCase
{
    private const GROUPED_OPTIONS = [
        'info_buyRequest' => ['super_product_config' => ['product_type' => 'grouped', 'product_id' => 200]],
    ];

    /**
     * @var bool
     */
    private $useChildSku = false;
    /**
     * @var CatalogProduct[] Products the repository can load, keyed by id
     */
    private $repositoryProducts = [];

    protected function setUp(): void
    {
        $this->useChildSku = false;
        $this->repositoryProducts = [];
    }

    public function testGroupedLinesSendTheGroupedParentUrlImageAndSku(): void
    {
        $this->repositoryProducts = [200 => $this->createProduct(200, 'grouped-sku', 'Dining Set')];
        $child = $this->createProduct(101, 'chair-sku', 'Dining Chair');

        $items = $this->getFeedItems([$this->createOrderItem($child, 'simple', 'chair-sku', self::GROUPED_OPTIONS)]);

        $this->assertSame('Dining Chair', $items[0]['title']);
        $this->assertSame('https://example.test/200.html', $items[0]['url']);
        $this->assertSame('https://example.test/200.jpg', $items[0]['itemImageUrl']);
        $this->assertSame('grouped-sku', $items[0]['sku']);
    }

    public function testGroupedLinesSendTheChildSkuInChildSkuMode(): void
    {
        $this->useChildSku = true;
        $this->repositoryProducts = [200 => $this->createProduct(200, 'grouped-sku', 'Dining Set')];
        $child = $this->createProduct(101, 'chair-sku', 'Dining Chair');

        $items = $this->getFeedItems([$this->createOrderItem($child, 'simple', 'chair-sku', self::GROUPED_OPTIONS)]);

        $this->assertSame('https://example.test/200.html', $items[0]['url']);
        $this->assertSame('chair-sku', $items[0]['sku']);
    }

    public function testGroupedLinesFallBackToTheOrderedProductWhenTheGroupedParentNoLongerExists(): void
    {
        $child = $this->createProduct(101, 'chair-sku', 'Dining Chair');

        $items = $this->getFeedItems([$this->createOrderItem($child, 'simple', 'chair-sku', self::GROUPED_OPTIONS)]);

        $this->assertSame('https://example.test/101.html', $items[0]['url']);
        $this->assertSame('chair-sku', $items[0]['sku']);
    }

    public function testChildSkuModeSendsTheSimpleSkuOfConfigurableLinesWithoutOptionSuffixes(): void
    {
        $this->useChildSku = true;
        $configurable = $this->createProduct(300, 'tee', 'Tee');

        $items = $this->getFeedItems([
            $this->createOrderItem($configurable, 'configurable', 'tee-blue-monogram', ['simple_sku' => 'tee-blue']),
        ]);

        $this->assertSame('https://example.test/300.html', $items[0]['url']);
        $this->assertSame('tee-blue', $items[0]['sku']);
    }

    /**
     * @param OrderItem[] $items
     * @return array[]
     */
    private function getFeedItems(array $items): array
    {
        $order = $this->createStub(Order::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getCustomerFirstname')->willReturn('Jane');
        $order->method('getCustomerLastname')->willReturn('Doe');
        $order->method('getAllVisibleItems')->willReturn($items);
        $session = $this->createStub(Session::class);
        $session->method('getLastRealOrder')->willReturn($order);

        $config = $this->createStub(Config::class);
        $config->method('getUseChildSku')->willReturnCallback(function () {
            return $this->useChildSku;
        });
        $productModel = $this->createStub(ProductModel::class);
        $productModel->method('turnToSafeEncoding')->willReturnArgument(0);
        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('getById')->willReturnCallback(function ($productId) {
            if (!isset($this->repositoryProducts[$productId])) {
                throw new NoSuchEntityException();
            }

            return $this->repositoryProducts[$productId];
        });

        $viewModel = new JSOrderFeed(
            $config,
            $session,
            $this->createImageHelper(),
            $productModel,
            $this->createStub(Monolog::class),
            new OrderLineItem($config),
            $productRepository
        );

        return json_decode($viewModel->getFeedPurchaseOrderData(), true)['items'];
    }

    private function createImageHelper(): Image
    {
        $imageProduct = null;
        $imageHelper = $this->createStub(Image::class);
        $imageHelper->method('init')->willReturnCallback(function ($product) use (&$imageProduct, $imageHelper) {
            $imageProduct = $product;

            return $imageHelper;
        });
        $imageHelper->method('getUrl')->willReturnCallback(function () use (&$imageProduct) {
            return 'https://example.test/' . $imageProduct->getId() . '.jpg';
        });

        return $imageHelper;
    }

    private function createProduct(int $productId, string $sku, string $name): CatalogProduct
    {
        $product = $this->createStub(CatalogProduct::class);
        $product->method('getId')->willReturn($productId);
        $product->method('getSku')->willReturn($sku);
        $product->method('getName')->willReturn($name);
        $product->method('getProductUrl')->willReturn('https://example.test/' . $productId . '.html');

        return $product;
    }

    private function createOrderItem(
        CatalogProduct $product,
        string $productType,
        string $sku,
        array $productOptions
    ): OrderItem {
        $item = $this->createStub(OrderItem::class);
        $item->method('getProduct')->willReturn($product);
        $item->method('getProductId')->willReturn($product->getId());
        $item->method('getProductType')->willReturn($productType);
        $item->method('getSku')->willReturn($sku);
        $item->method('getProductOptions')->willReturn($productOptions);
        $item->method('getPrice')->willReturn(10.0);
        $item->method('getQtyOrdered')->willReturn(1.0);

        return $item;
    }
}
