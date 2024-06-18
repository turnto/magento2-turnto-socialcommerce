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
class AddressFallback implements \Magento\Framework\Data\OptionSourceInterface
{

    CONST BILLING_ADDRESS_VALUE = '0';
    CONST SHIPPING_ADDRESS_VALUE = '1';

    /**
     * Options getter
     * @return array
     */
    public function toOptionArray()
    {
        $optionArray = [
            [
                'value' => self::BILLING_ADDRESS_VALUE,
                'label' => __('Billing Address')
            ],
            [
                'value' => self::SHIPPING_ADDRESS_VALUE,
                'label' => __('Shipping Address')
            ]
        ];

        return $optionArray;
    }
}
