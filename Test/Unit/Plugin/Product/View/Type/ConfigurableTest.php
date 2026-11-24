<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Plugin\Product\View\Type;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Block\Product\View\Type\Configurable as ConfigurableBlock;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Product as TurnToProduct;
use TurnTo\SocialCommerce\Plugin\Product\View\Type\Configurable;

class ConfigurableTest extends TestCase
{
    protected const JSON_CONFIG = '{"images":{"13":[{"img":"a.jpg"}]}}';

    public function testAddsTheChildSkuToItsFirstImageWhenTheExtensionIsEnabled(): void
    {
        $this->assertSame(
            '{"images":{"13":[{"img":"a.jpg","sku":"child-sku"}]}}',
            $this->createPlugin(true)->afterGetJsonConfig($this->createBlock(), self::JSON_CONFIG)
        );
    }

    public function testLeavesTheImageConfigUntouchedWhenTheExtensionIsDisabled(): void
    {
        $this->assertSame(
            self::JSON_CONFIG,
            $this->createPlugin(false)->afterGetJsonConfig($this->createBlock(), self::JSON_CONFIG)
        );
    }

    protected function createPlugin(bool $enabled): Configurable
    {
        $config = $this->createStub(Config::class);
        $config->method('getIsEnabled')->willReturn($enabled);

        $turnToProduct = $this->createStub(TurnToProduct::class);
        $turnToProduct->method('turnToSafeEncoding')->willReturnArgument(0);

        return new Configurable($turnToProduct, new Json(), $config);
    }

    protected function createBlock(): ConfigurableBlock
    {
        $child = $this->createStub(Product::class);
        $child->method('getId')->willReturn('13');
        $child->method('getSku')->willReturn('child-sku');

        $block = $this->createStub(ConfigurableBlock::class);
        $block->method('getAllowProducts')->willReturn([$child]);

        return $block;
    }
}
