<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Plugin\LayeredNavigation\Block\Navigation;

use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\View\LayoutInterface;
use Magento\LayeredNavigation\Block\Navigation\FilterRenderer as MagentoFilterRenderer;
use Magento\LayeredNavigation\ViewModel\Layer\Filter as FilterViewModel;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Block\LayeredNavigation\RatingFilter;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Layer\RatingFilterOption;
use TurnTo\SocialCommerce\Plugin\LayeredNavigation\Block\Navigation\FilterRenderer;
use TurnTo\SocialCommerce\Setup\InstallHelper;

class FilterRendererTest extends TestCase
{
    public function testRendersTheRatingFilterWithTheTurnToTemplate(): void
    {
        $filter = $this->createFilter(InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE);
        $viewModel = $this->createStub(FilterViewModel::class);
        $subject = $this->createStub(MagentoFilterRenderer::class);
        $subject->method('getData')->willReturnCallback(function ($key) use ($viewModel) {
            return $key === 'product_layer_view_model' ? $viewModel : null;
        });

        $block = $this->createMock(RatingFilter::class);
        $block->expects($this->once())
            ->method('__call')
            ->with('setRatingFilter', [$filter])
            ->willReturnSelf();
        $block->expects($this->once())
            ->method('setData')
            ->with('product_layer_view_model', $viewModel)
            ->willReturnSelf();
        $block->method('toHtml')->willReturn('<ol class="items">stars</ol>');

        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects($this->once())->method('createBlock')->with(RatingFilter::class)->willReturn($block);

        $html = $this->createPlugin($layout, true)->aroundRender(
            $subject,
            function () {
                $this->fail('The default renderer must not run for the rating filter.');
            },
            $filter
        );

        $this->assertSame('<ol class="items">stars</ol>', $html);
    }

    public function testUsesTheDefaultRendererForOtherAttributes(): void
    {
        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects($this->never())->method('createBlock');

        $html = $this->createPlugin($layout, true)->aroundRender(
            $this->createStub(MagentoFilterRenderer::class),
            function () {
                return 'default';
            },
            $this->createFilter('color')
        );

        $this->assertSame('default', $html);
    }

    public function testUsesTheDefaultRendererForFiltersWithoutAnAttribute(): void
    {
        $filter = $this->createStub(AbstractFilter::class);
        $filter->method('__call')->willReturn(false);
        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects($this->never())->method('createBlock');

        $html = $this->createPlugin($layout, true)->aroundRender(
            $this->createStub(MagentoFilterRenderer::class),
            function () {
                return 'default';
            },
            $filter
        );

        $this->assertSame('default', $html);
    }

    public function testUsesTheDefaultRendererWhenTurnToReviewsAreDisabled(): void
    {
        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects($this->never())->method('createBlock');

        $html = $this->createPlugin($layout, false)->aroundRender(
            $this->createStub(MagentoFilterRenderer::class),
            function () {
                return 'default';
            },
            $this->createFilter(InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE)
        );

        $this->assertSame('default', $html);
    }

    protected function createPlugin(LayoutInterface $layout, bool $enabled): FilterRenderer
    {
        $config = $this->createStub(Config::class);
        $config->method('getIsEnabled')->willReturn($enabled);
        $config->method('getConfigBool')->willReturn($enabled);

        return new FilterRenderer($layout, new RatingFilterOption($config));
    }

    protected function createFilter(string $attributeCode): AbstractFilter
    {
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getAttributeCode')->willReturn($attributeCode);

        $filter = $this->createStub(AbstractFilter::class);
        $filter->method('__call')->willReturnCallback(function ($method) {
            return $method === 'hasAttributeModel';
        });
        $filter->method('getAttributeModel')->willReturn($attribute);

        return $filter;
    }
}
