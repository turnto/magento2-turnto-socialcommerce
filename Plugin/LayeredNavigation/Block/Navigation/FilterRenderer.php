<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Plugin\LayeredNavigation\Block\Navigation;

use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\LayeredNavigation\Block\Navigation\FilterRenderer as MagentoFilterRenderer;
use TurnTo\SocialCommerce\Block\LayeredNavigation\RatingFilter;
use TurnTo\SocialCommerce\Model\Layer\RatingFilterOption;

/**
 * Renders the TurnTo average rating filter with TurnTo_SocialCommerce::layer/filter/rating.phtml
 */
class FilterRenderer
{
    /**
     * @var LayoutInterface
     */
    protected $layout;

    /**
     * @var RatingFilterOption
     */
    protected $ratingFilterOption;

    /**
     * @var string
     */
    protected $blockClass;

    /**
     * @param LayoutInterface $layout
     * @param RatingFilterOption $ratingFilterOption
     * @param string $blockClass
     */
    public function __construct(
        LayoutInterface $layout,
        RatingFilterOption $ratingFilterOption,
        string $blockClass = RatingFilter::class
    ) {
        $this->layout = $layout;
        $this->ratingFilterOption = $ratingFilterOption;
        $this->blockClass = $blockClass;
    }

    /**
     * Render the TurnTo average rating filter as star ratings; other filters use the default renderer
     *
     * @param MagentoFilterRenderer $subject
     * @param callable $proceed
     * @param FilterInterface $filter
     * @return string
     */
    public function aroundRender(MagentoFilterRenderer $subject, callable $proceed, FilterInterface $filter)
    {
        if (!$filter->hasAttributeModel()
            || !$this->ratingFilterOption->isRatingAttribute($filter->getAttributeModel()->getAttributeCode())
            || !$this->ratingFilterOption->isEnabled()
        ) {
            return $proceed($filter);
        }

        return $this->layout->createBlock($this->blockClass)
            ->setRatingFilter($filter)
            ->setData('product_layer_view_model', $subject->getData('product_layer_view_model'))
            ->toHtml();
    }
}
