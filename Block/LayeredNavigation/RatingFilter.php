<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Block\LayeredNavigation;

use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Magento\Catalog\Model\Layer\Filter\Item as FilterItem;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;
use TurnTo\SocialCommerce\Model\Layer\RatingFilterOption;

/**
 * Renders the TurnTo average rating layered navigation filter as star ratings
 *
 * @method FilterInterface|null getRatingFilter()
 * @method $this setRatingFilter(FilterInterface $filter)
 */
class RatingFilter extends Template
{
    protected const XML_PATH_DISPLAY_LAYER_COUNT = 'catalog/layered_navigation/display_product_count';

    /**
     * @var string
     */
    protected $_template = 'TurnTo_SocialCommerce::layer/filter/rating.phtml';

    /**
     * @var RatingFilterOption
     */
    protected $ratingFilterOption;

    /**
     * @param Context $context
     * @param RatingFilterOption $ratingFilterOption
     * @param array $data
     */
    public function __construct(
        Context $context,
        RatingFilterOption $ratingFilterOption,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->ratingFilterOption = $ratingFilterOption;
    }

    /**
     * Items of the rating filter being rendered
     *
     * @return FilterItem[]
     */
    public function getFilterItems(): array
    {
        $filter = $this->getRatingFilter();

        return $filter ? (array) $filter->getItems() : [];
    }

    /**
     * Star rating percentage (20-100) for a filter item, or null if the item is not a TurnTo rating option
     *
     * @param FilterItem $filterItem
     * @return int|null
     */
    public function getRatingPercent(FilterItem $filterItem): ?int
    {
        $filter = $this->getRatingFilter();
        if (!$filter || !$filter->hasAttributeModel()) {
            return null;
        }

        $optionLabel = $filter->getAttributeModel()->getSource()->getOptionText($filterItem->getValue());

        return is_scalar($optionLabel) ? $this->ratingFilterOption->getPercent($optionLabel) : null;
    }

    /**
     * Text shown after the stars, e.g. "& Up"
     *
     * @param int $percent
     * @return string
     */
    public function getRatingSuffix(int $percent): string
    {
        return $this->ratingFilterOption->getSuffix($percent);
    }

    /**
     * Whether product counts are shown next to layered navigation options
     *
     * @return bool
     */
    public function shouldDisplayProductCount(): bool
    {
        $viewModel = $this->getData('product_layer_view_model');
        if ($viewModel) {
            return (bool) $viewModel->shouldDisplayProductCountOnLayer();
        }

        return $this->_scopeConfig->isSetFlag(self::XML_PATH_DISPLAY_LAYER_COUNT, ScopeInterface::SCOPE_STORE);
    }
}
