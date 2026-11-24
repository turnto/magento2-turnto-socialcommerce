<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Block;

use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\View\Element\Template\Context;

/**
 * Builds a template context with the collaborators AbstractBlock::toHtml() uses
 */
trait RenderableContextTrait
{
    /**
     * @return Context
     */
    protected function createRenderableContext(): Context
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventManager')->willReturn($this->createStub(ManagerInterface::class));
        $context->method('getScopeConfig')->willReturn($this->createStub(ScopeConfigInterface::class));
        $context->method('getCacheState')->willReturn($this->createStub(StateInterface::class));

        return $context;
    }
}
