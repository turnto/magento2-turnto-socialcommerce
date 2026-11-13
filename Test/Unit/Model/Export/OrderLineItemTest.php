<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Export\OrderLineItem;

class OrderLineItemTest extends TestCase
{
    /**
     * @dataProvider feedProductIdProvider
     */
    #[DataProvider('feedProductIdProvider')]
    public function testGetFeedProductId(array $productOptions, int $expectedProductId): void
    {
        $lineItem = $this->createOrderItem('simple', 'child-sku', $productOptions);

        $this->assertSame($expectedProductId, $this->createOrderLineItem(false)->getFeedProductId($lineItem));
    }

    public static function feedProductIdProvider(): array
    {
        return [
            'grouped line from the buy request is attributed to the grouped parent' => [
                ['info_buyRequest' => ['super_product_config' => ['product_type' => 'grouped', 'product_id' => 200]]],
                200,
            ],
            'grouped line from the top-level options is attributed to the grouped parent' => [
                ['super_product_config' => ['product_type' => 'grouped', 'product_id' => '200']],
                200,
            ],
            'other parent types keep the ordered product' => [
                ['info_buyRequest' => ['super_product_config' => ['product_type' => 'bundle', 'product_id' => 200]]],
                101,
            ],
            'lines without a parent keep the ordered product' => [[], 101],
        ];
    }

    /**
     * @dataProvider feedSkuProvider
     */
    #[DataProvider('feedSkuProvider')]
    public function testGetFeedSku(
        bool $useChildSku,
        string $productType,
        array $productOptions,
        string $lineSku,
        bool $withOrderedProduct,
        string $expectedSku
    ): void {
        $lineItem = $this->createOrderItem($productType, $lineSku, $productOptions);
        $orderedProduct = $withOrderedProduct ? $this->createProduct('ordered-sku') : null;

        $this->assertSame(
            $expectedSku,
            $this->createOrderLineItem($useChildSku)
                ->getFeedSku($lineItem, $this->createProduct('parent-sku'), 1, $orderedProduct)
        );
    }

    public static function feedSkuProvider(): array
    {
        return [
            'parent SKU mode always sends the product SKU' => [
                false, 'configurable', ['simple_sku' => 'child-sku'], 'child-sku', true, 'parent-sku',
            ],
            'configurable sends the selected simple SKU' => [
                true, 'configurable', ['simple_sku' => 'child-sku'], 'child-sku-opt', true, 'child-sku',
            ],
            'configurable without a recorded simple SKU falls back to the line SKU' => [
                true, 'configurable', [], 'child-sku', true, 'child-sku',
            ],
            'dynamic SKU bundle sends the bundle SKU' => [
                true, 'bundle', [], 'parent-sku-sel1-sel2', false, 'parent-sku',
            ],
            'simple with option SKUs sends the product SKU' => [
                true, 'simple', [], 'parent-sku-opt', false, 'parent-sku',
            ],
            'grouped sends the ordered child SKU' => [
                true, 'simple', [], 'ordered-sku-opt', true, 'ordered-sku',
            ],
        ];
    }

    private function createOrderLineItem(bool $useChildSku): OrderLineItem
    {
        $config = $this->createStub(Config::class);
        $config->method('getUseChildSku')->willReturn($useChildSku);

        return new OrderLineItem($config);
    }

    private function createOrderItem(string $productType, string $sku, array $productOptions): OrderItem
    {
        $item = $this->createStub(OrderItem::class);
        $item->method('getProductId')->willReturn(101);
        $item->method('getProductType')->willReturn($productType);
        $item->method('getSku')->willReturn($sku);
        $item->method('getProductOptions')->willReturn($productOptions);

        return $item;
    }

    private function createProduct(string $sku): CatalogProduct
    {
        $product = $this->createStub(CatalogProduct::class);
        $product->method('getSku')->willReturn($sku);

        return $product;
    }
}
