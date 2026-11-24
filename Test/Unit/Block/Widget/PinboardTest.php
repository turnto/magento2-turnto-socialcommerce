<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Block\Widget;

use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Block\Widget\Pinboard;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Data\PinboardConfigFactory;
use TurnTo\SocialCommerce\Model\Product;
use TurnTo\SocialCommerce\Test\Unit\Block\RenderableContextTrait;
use TurnTo\SocialCommerce\ViewModel\Widget;

class PinboardTest extends TestCase
{
    use RenderableContextTrait;

    public function testRendersNothingWhenTheExtensionIsDisabled(): void
    {
        $config = $this->createStub(Config::class);
        $config->method('getIsEnabled')->willReturn(false);

        $block = new Pinboard(
            $config,
            $this->createStub(PinboardConfigFactory::class),
            $this->createStub(Product::class),
            $this->createRenderableContext(),
            $this->createStub(Widget::class)
        );

        $this->assertSame('', $block->toHtml());
    }
}
