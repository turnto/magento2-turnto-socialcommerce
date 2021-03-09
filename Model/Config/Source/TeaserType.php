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

namespace TurnTo\SocialCommerce\Model\Config\Source;

/**
 * Class ProductAttributeSelect
 * @package TurnTo\SocialCommerce\Model\Config\Source
 */
class TeaserType implements \Magento\Framework\Option\ArrayInterface
{

    /**
     * Options getter
     * @return array
     */
    public function toOptionArray()
    {
        // Options are 1 and 2 because the ifconfig values for the teaser template in catalog_product_view
        // wasn't responding to 0 and 1
        $optionArray = [
            [
                'value' => '2',
                'label' => __('Use Teaser Widget')
            ],
            [
                'value' => '1',
                'label' => __('Use Local Teaser Code')
            ]
        ];

        return $optionArray;
    }
}
