<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Plugin\Block\Product\View\Type;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Block\Product\View\Type\Configurable;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Product as TurnToProduct;
use TurnTo\SocialCommerce\Plugin\Block\Product\View\Type\ConfigurablePlugin;

class ConfigurablePluginTest extends TestCase
{
    protected const JSON_CONFIG = '{"productId":"12","sku":{"13":"child-sku"}}';

    public function testAddsTheTurnToSkusWhenTheExtensionIsEnabled(): void
    {
        $result = json_decode(
            $this->createPlugin(true)->afterGetJsonConfig($this->createSubject(), self::JSON_CONFIG),
            true
        );

        $this->assertTrue($result['useChild']);
        $this->assertSame('parent-sku', $result['parentSku']);
        $this->assertSame(['13' => 'child-sku'], $result['childSkuMap']);
    }

    public function testLeavesTheJsonConfigUntouchedWhenTheExtensionIsDisabled(): void
    {
        $this->assertSame(
            self::JSON_CONFIG,
            $this->createPlugin(false)->afterGetJsonConfig($this->createSubject(), self::JSON_CONFIG)
        );
    }

    protected function createPlugin(bool $enabled): ConfigurablePlugin
    {
        $config = $this->createStub(Config::class);
        $config->method('getIsEnabled')->willReturn($enabled);
        $config->method('getUseChildSku')->willReturn(true);

        $turnToProduct = $this->createStub(TurnToProduct::class);
        $turnToProduct->method('turnToSafeEncoding')->willReturnArgument(0);

        return new ConfigurablePlugin($config, new Json(), $this->createStub(Monolog::class), $turnToProduct);
    }

    protected function createSubject(): Configurable
    {
        $parent = $this->createStub(Product::class);
        $parent->method('getId')->willReturn('12');
        $parent->method('getSku')->willReturn('parent-sku');

        $subject = $this->createStub(Configurable::class);
        $subject->method('getProduct')->willReturn($parent);

        return $subject;
    }
}
