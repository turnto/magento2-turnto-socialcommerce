<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2018 Classy Llama Studios, LLC
 */

// In Magento 2.2.x, Magento introduced a required interface for block arguments, add this for backwards compatibility

namespace TurnTo\SocialCommerce\Api;

if (!interface_exists('Magento\Framework\View\Element\Block\ArgumentInterface')) {
    interface ArgumentInterface
    {
    }
} else {
    class_alias(
        'Magento\Framework\View\Element\Block\ArgumentInterface',
        'TurnTo\SocialCommerce\Api\ArgumentInterface'
    );
}

interface TurnToConfigDataProviderInterface extends ArgumentInterface
{
    /**
     * Returns TurnTo configuration data used in the JavaScript snippet
     * @api
     * @return array
     */
    public function getData();
}
