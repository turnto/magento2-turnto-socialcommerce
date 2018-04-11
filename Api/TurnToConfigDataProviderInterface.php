<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2018 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Api;

interface TurnToConfigDataProviderInterface
{
    /**
     * Returns TurnTo configuration data used in the JavaScript snippet
     *
     * @api
     * @return array
     */
    public function getData();
}
