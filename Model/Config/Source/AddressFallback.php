<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class ProductAttributeSelect
 */
class AddressFallback implements OptionSourceInterface
{
    public const BILLING_ADDRESS_VALUE = '0';
    public const SHIPPING_ADDRESS_VALUE = '1';

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
