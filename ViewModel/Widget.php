<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\ViewModel;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use TurnTo\SocialCommerce\Model\Config as ConfigModel;
use TurnTo\SocialCommerce\Model\Product;

class Widget implements ArgumentInterface
{
    /**
     * @var ConfigModel
     */
    protected $config;
    /**
     * @var Product
     */
    protected $product;
    /**
     * @var UrlInterface
     */
    protected $backendUrl;

    public function __construct(
        ConfigModel $config,
        Product $product,
        UrlInterface $backendUrl
    ) {
        $this->config = $config;
        $this->product = $product;
        $this->backendUrl = $backendUrl;
    }

    /**
     * @return bool|null
     */
    public function getIsEnabled()
    {
        return $this->config->getIsEnabled();
    }

    /**
     * @param $path
     * @return mixed|null
     */
    public function getConfigValue($path)
    {
        return $this->config->getConfigValue($path);
    }

    /**
     * @param $path
     * @return bool
     */
    public function getConfigBool($path)
    {
        return $this->config->getConfigBool($path);
    }

    /**
     * @param $path
     * @return string "true" or "false"
     */
    public function getConfigBoolString($path)
    {
        return $this->config->getConfigBool($path) ? 'true' : 'false';
    }

    /**
     * @return string
     */
    public function getSiteKey()
    {
        return (string) $this->config->getSiteKey();
    }

    /**
     * @return string|null
     */
    public function getWidgetUrl()
    {
        $baseUrl = $this->config->getConfigValue(ConfigModel::PRODUCT_WIDGET_URL);
        $siteKey = $this->config->getSiteKey();

        if (!$baseUrl || !$siteKey) {
            return null;
        }

        return rtrim($baseUrl, '/') . '/' . ConfigModel::SOCIALCOMMERCE_VERSION . '/widgets/' . $siteKey . '/js/turnto.js';
    }

    /**
     * @return string
     */
    public function getAuthorizationKey()
    {
        return (string) $this->config->getAuthorizationKey();
    }

    /**
     * @return string
     */
    public function storeConfigUrl()
    {
        return $this->backendUrl->getUrl("adminhtml/system_config/edit/section/turnto_socialcommerce_configuration", []);
    }

    /**
     * @param $string
     * @return string
     */
    public function turnToSafeEncoding($string)
    {
        return $this->product->turnToSafeEncoding($string);
    }

    /**
     * @return string
     */
    public function getProductSku()
    {
        return $this->product->getProductSku();
    }
}
