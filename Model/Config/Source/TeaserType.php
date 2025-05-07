<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class TeaserType implements OptionSourceInterface
{
    public const TEASER_LOCAL = 1;
    public const TEASER_WIDGET = 2;

    /**
     * Options getter
     * @return array
     */
    public function toOptionArray()
    {
        // Options are 1 and 2 because the ifconfig values for the teaser template in catalog_product_view
        // wasn't responding to 0 and 1
        return [
            [
                'value' => self::TEASER_WIDGET,
                'label' => __('Use Teaser Widget')
            ],
            [
                'value' => self::TEASER_LOCAL,
                'label' => __('Use Local Teaser Code')
            ]
        ];
    }
}
