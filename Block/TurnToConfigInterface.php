<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2018 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Block;

interface TurnToConfigInterface
{
    /**
     * JavaScript config for TurnTo global variable
     * @return string
     */
    public function getJavaScriptConfig();

    /**
     * Returns custom configuration from admin that will be merged into the standard config
     * @return string
     */
    public function getCustomJavaScriptConfiguration();
}
