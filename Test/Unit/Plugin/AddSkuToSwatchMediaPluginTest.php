<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Plugin;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Framework\App\RequestInterface;
use Magento\Swatches\Helper\Data;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Product;
use TurnTo\SocialCommerce\Plugin\AddSkuToSwatchMediaPlugin;

class AddSkuToSwatchMediaPluginTest extends TestCase
{
    protected const MEDIA = ['large' => 'a.jpg'];

    public function testAddsTheProductSkuWhenTheExtensionIsEnabled(): void
    {
        $this->assertSame(
            ['large' => 'a.jpg', 'sku' => 'parent-sku'],
            $this->createPlugin(true)->afterGetProductMediaGallery($this->createStub(Data::class), self::MEDIA)
        );
    }

    public function testLeavesTheSwatchMediaUntouchedWhenTheExtensionIsDisabled(): void
    {
        $this->assertSame(
            self::MEDIA,
            $this->createPlugin(false)->afterGetProductMediaGallery($this->createStub(Data::class), self::MEDIA)
        );
    }

    protected function createPlugin(bool $enabled): AddSkuToSwatchMediaPlugin
    {
        $config = $this->createStub(Config::class);
        $config->method('getIsEnabled')->willReturn($enabled);
        $config->method('getUseChildSku')->willReturn(true);

        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn('12');

        $product = $this->createStub(CatalogProduct::class);
        $product->method('getSku')->willReturn('parent-sku');
        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('getById')->willReturn($product);

        $turnToProduct = $this->createStub(Product::class);
        $turnToProduct->method('turnToSafeEncoding')->willReturnArgument(0);

        return new AddSkuToSwatchMediaPlugin(
            $request,
            $productRepository,
            $this->createStub(Data::class),
            $config,
            $turnToProduct,
            $this->createStub(Monolog::class)
        );
    }
}
