<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 6/22/16
 * Time: 1:27 PM
 */

namespace TurnTo\SocialCommerce\Plugin\Catalog\Model\Layer\Filter\Item;

use TurnTo\SocialCommerce\Setup\InstallData;
use TurnTo\SocialCommerce\Plugin\Review\Block\Product\ReviewRenderer;

class DataBuilder
{
    const RATING_APPEND_AND_UP = 'and Up';

    /**
     * Converts the label to a rating summary label if it corresponds to a TurnTo Rating Filter Value
     *
     * @param $label
     * @return string
     */
    protected function getRatingLabel($label) {
        $idx = array_search($label, InstallData::RATING_FILTER_VALUES);
        if ($idx === false) {
            return $label;
        }
        $rating = ($idx + 1) * ReviewRenderer::RATING_TO_PERCENTILE_MULTIPLIER;
        $andUp = __(self::RATING_APPEND_AND_UP);
        $label = <<<EOD
<span class="rating-summary">
    <span class="rating-result" title="$rating%">
        <span style="width:$rating%;">
            <span>$rating%</span>
        </span>
    </span>&nbsp;$andUp&nbsp;
</span>
EOD;

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
        $label = $this->getRatingLabel($label);
        $proceed($label, $value, $count);
    }
}