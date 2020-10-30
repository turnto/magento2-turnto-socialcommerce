<?php
/**
 * TurnTo_SocialCommerce
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright  Copyright (c) 2018 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Plugin\Catalog\Model\Layer\Filter\Item;

use TurnTo\SocialCommerce\Setup\InstallHelper;
use TurnTo\SocialCommerce\Plugin\Review\Block\Product\ReviewRenderer;

class DataBuilder
{
    /**
     * Used to append & Up to rendered star rating label
     */
    const RATING_APPEND_AND_UP = '& Up';

    /**
     * @var null|\TurnTo\SocialCommerce\Helper\Config
     */
    protected $turnToConfigHelper = null;

    /**
     * DataBuilder constructor.
     * @param \TurnTo\SocialCommerce\Helper\Config $turnToConfigHelper
     */
    public function __construct(\TurnTo\SocialCommerce\Helper\Config $turnToConfigHelper)
    {
        $this->turnToConfigHelper = $turnToConfigHelper;
    }

    /**
     * Converts the label to a rating summary label if it corresponds to a TurnTo Rating Filter Value
     *
     * @param $label
     * @return string
     */
    protected function getRatingLabel($label)
    {
        $idx = array_search($label, InstallHelper::RATING_FILTER_VALUES);
        if ($idx === false) {
            return $label;
        }
        $rating = ($idx + 1) * ReviewRenderer::RATING_TO_PERCENTILE_MULTIPLIER;
        $andUp = $rating < 100 ? __(self::RATING_APPEND_AND_UP) : '';
        $label = "
            <span class='rating-summary'>
                <span class='rating-result' title='$rating%'>
                    <span style='width:$rating%;'>
                        <span>$rating%</span>
                    </span>
                </span>&nbsp;$andUp&nbsp;
            </span>";

        return $label;
    }

    /**
     * @param \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $subject
     * @param \Closure $proceed
     * @param $label
     * @param $value
     * @param $count
     */
    public function aroundAddItemData(
        \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $subject,
        \Closure $proceed,
        $label,
        $value,
        $count
    ) {
        if ($this->turnToConfigHelper->getIsEnabled() && $this->turnToConfigHelper->getReviewsEnabled()) {
            $label = $this->getRatingLabel($label);
        }
        $proceed($label, $value, $count);
    }
}
