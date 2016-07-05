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
    public function toOptionArray()
    {
        return [
            ['value' => 'staticEmbed', 'label' => __('Static Embed')],
            ['value' => 'dynamicEmbed', 'label' => __('Dynamic Embed')]
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'dynamicEmbed' => __('Dynamic Embed'),
            'staticEmbed' => __('Static Embed')
        ];
    }
}
