<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2018 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Helper;

use Magento\Framework\UrlInterface;
use TurnTo\SocialCommerce\Helper\Config as TurnToConfigHelper;

class ConfigProviderHelper
{
    /**
     * @var TurnToConfigHelper
     */
    protected $configHelper;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @param TurnToConfigHelper $configHelper
     * @param UrlInterface       $urlBuilder
     */
    public function __construct(TurnToConfigHelper $configHelper, UrlInterface $urlBuilder)
    {
        $this->configHelper = $configHelper;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Returns TurnTo configuration for single-sign on
     * @return array
     */
    public function getSingleSignOnConfig()
    {
        if (!$this->configHelper->getSingleSignOn()) {
            return [];
        }

        return [
            'registration' => [
                'localGetLoginStatusFunction' => new \Zend_Json_Expr('localGetLoginStatusFunction'),
                'localRegistrationUrl' => $this->urlBuilder->getBaseUrl() . 'turnto/sso/login',
                'localGetUserInfoFunction' => new \Zend_Json_Expr('localGetUserInfoFunction'),
                'localLogoutFunction' => new \Zend_Json_Expr('localLogoutFunction')
            ]
        ];
    }
}
