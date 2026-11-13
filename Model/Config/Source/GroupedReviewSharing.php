<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class GroupedReviewSharing implements OptionSourceInterface
{
    public const MEMBERS = 'members';
    public const VIRTUAL_PARENT_CODE = 'virtual_parent_code';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::MEMBERS, 'label' => __('Kit: Members of the grouped product')],
            ['value' => self::VIRTUAL_PARENT_CODE, 'label' => __('Pool: Shared virtual parent code')]
        ];
    }
}
