<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Block\LayeredNavigation;

use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\Filter\Item as FilterItem;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\LayeredNavigation\ViewModel\Layer\Filter as FilterViewModel;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Block\LayeredNavigation\RatingFilter;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Layer\RatingFilterOption;
use TurnTo\SocialCommerce\Setup\InstallHelper;

class RatingFilterTest extends TestCase
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createStub(ScopeConfigInterface::class);
    }

    public function testUsesTheOverridableTurnToTemplate(): void
    {
        $this->assertSame('TurnTo_SocialCommerce::layer/filter/rating.phtml', $this->createBlock()->getTemplate());
    }

    public function testReturnsTheFilterItems(): void
    {
        $items = [$this->createItem('17'), $this->createItem('18')];
        $block = $this->createBlock()->setRatingFilter($this->createFilter([], $items));

        $this->assertSame($items, $block->getFilterItems());
        $this->assertSame([], $this->createBlock()->getFilterItems());
    }

    public function testResolvesEachItemsStarPercentageFromItsOptionLabel(): void
    {
        $block = $this->createBlock()->setRatingFilter($this->createFilter([
            '17' => InstallHelper::FOUR_STAR_LABEL,
            '18' => InstallHelper::FIVE_STAR_LABEL,
            '19' => 'Custom option',
        ]));

        $this->assertSame(80, $block->getRatingPercent($this->createItem('17')));
        $this->assertSame(100, $block->getRatingPercent($this->createItem('18')));
        $this->assertNull($block->getRatingPercent($this->createItem('19')), 'Unknown options fall back to the label.');
        $this->assertSame('& Up', $block->getRatingSuffix(80));
        $this->assertSame('', $block->getRatingSuffix(100));
    }

    public function testReturnsNoPercentageWithoutAFilter(): void
    {
        $this->assertNull($this->createBlock()->getRatingPercent($this->createItem('17')));
    }

    public function testProductCountSettingComesFromTheLayeredNavigationViewModel(): void
    {
        $viewModel = $this->createStub(FilterViewModel::class);
        $viewModel->method('shouldDisplayProductCountOnLayer')->willReturn(true);

        $block = $this->createBlock()->setData('product_layer_view_model', $viewModel);

        $this->assertTrue($block->shouldDisplayProductCount());
    }

    public function testProductCountSettingFallsBackToStoreConfigWithoutAViewModel(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with('catalog/layered_navigation/display_product_count', ScopeInterface::SCOPE_STORE)
            ->willReturn(true);

        $this->assertTrue($this->createBlock()->shouldDisplayProductCount());
    }

    protected function createBlock(): RatingFilter
    {
        $context = $this->createStub(Context::class);
        $context->method('getScopeConfig')->willReturn($this->scopeConfig);

        return new RatingFilter($context, new RatingFilterOption($this->createStub(Config::class)));
    }

    /**
     * @param array<string, string> $optionLabels Option labels keyed by option id
     * @param FilterItem[] $items
     * @return AbstractFilter
     */
    protected function createFilter(array $optionLabels, array $items = []): AbstractFilter
    {
        $source = $this->createStub(AbstractSource::class);
        $source->method('getOptionText')->willReturnCallback(function ($value) use ($optionLabels) {
            return $optionLabels[$value] ?? false;
        });
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getSource')->willReturn($source);

        $filter = $this->createStub(AbstractFilter::class);
        $filter->method('__call')->willReturnCallback(function ($method) {
            return $method === 'hasAttributeModel';
        });
        $filter->method('getAttributeModel')->willReturn($attribute);
        $filter->method('getItems')->willReturn($items);

        return $filter;
    }

    protected function createItem(string $value): FilterItem
    {
        $item = $this->createStub(FilterItem::class);
        $item->method('__call')->willReturnCallback(function ($method) use ($value) {
            return $method === 'getValue' ? $value : null;
        });

        return $item;
    }
}
