<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2018 Classy Llama Studios, LLC
 */

// In Magento 2.2.x, Magento introduced a required interface for block arguments, add this for backwards compatibility
namespace Magento\Framework\View\Element\Block {

    if (!interface_exists('\Magento\Framework\View\Element\Block\ArgumentInterface')) {
        interface ArgumentInterface
        {
        }
    }
}

namespace TurnTo\SocialCommerce\Api {

    interface TurnToConfigDataProviderInterface
    {
        /**
         * Returns TurnTo configuration data used in the JavaScript snippet
         * @api
         * @return array
         */
        public function getData();
    }
}
