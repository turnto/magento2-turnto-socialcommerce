<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 5/31/16
 * Time: 1:40 PM
 */

namespace TurnTo\SocialCommerce\Model\Config\Source;

/**
 * Class SetupTypeSelect
 * @package TurnTo\SocialCommerce\Model\Config\Source
 */
class SetupTypeSelect implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray ()
    {
        return [
            ['value' => 2, 'label' => __('Static Embed')],
            ['value' => 1, 'label' => __('Dynamic Embed')],
            ['value' => 0, 'label' => __('Overlay')]
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray ()
    {
        return [
            0 => __('Overlay'),
            1 => __('Dynamic Embed'),
            2 => __('Static Embed')
        ];
    }
}
