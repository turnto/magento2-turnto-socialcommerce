<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Block\Widget;

use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Block\Widget\LandingPage;
use TurnTo\SocialCommerce\Test\Unit\Block\RenderableContextTrait;
use TurnTo\SocialCommerce\ViewModel\Widget;

class LandingPageTest extends TestCase
{
    use RenderableContextTrait;

    public function testRendersNothingWhenTheExtensionIsDisabled(): void
    {
        $viewModel = $this->createStub(Widget::class);
        $viewModel->method('getIsEnabled')->willReturn(false);

        $block = new LandingPage($this->createRenderableContext(), $viewModel);

        $this->assertSame('', $block->toHtml());
    }
}
