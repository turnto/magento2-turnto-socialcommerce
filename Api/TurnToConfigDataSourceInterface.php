<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2018 Classy Llama Studios, LLC
 */

// In Magento 2.2.x, Magento introduced a required interface for block arguments, add this for backwards compatibility

namespace TurnTo\SocialCommerce\Api;

use Magento\Framework\Data\CollectionDataSourceInterface;

interface TurnToConfigDataSourceInterface extends CollectionDataSourceInterface
{
    /**
     * Returns TurnTo configuration data used in the JavaScript snippet
     * @api
     * @return array
     */
    public function getData();
}
