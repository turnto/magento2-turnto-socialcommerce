<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class FeedFormat implements OptionSourceInterface
{
    public const GOOGLE_PRODUCT = 'google-product.xml';
    public const COMMERCE = 'tab-style.commerce';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::GOOGLE_PRODUCT, 'label' => __('Google Products Atom 1.0')],
            ['value' => self::COMMERCE, 'label' => __('Commerce Product Catalog Feed')]
        ];
    }
}
