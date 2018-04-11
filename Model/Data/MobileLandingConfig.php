<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2018 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Model\Data;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface as BlockArgumentInterface;
use TurnTo\SocialCommerce\Api\TurnToConfigDataProviderInterface;
use TurnTo\SocialCommerce\Helper\Config as TurnToConfigHelper;

class MobileLandingConfig implements TurnToConfigDataProviderInterface, BlockArgumentInterface
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
     * {@inheritdoc}
     */
    public function getData(): array
    {
        $config = [
            'siteKey' => $this->configHelper->getSiteKey(),
            'host' => $this->configHelper->getUrlWithoutProtocol(),
            'staticHost' => $this->configHelper->getStaticUrlWithoutProtocol(),
            'skipCssLoad' => false,
            'setupType' => 'mobileTT'
        ];

        if ($this->configHelper->getSingleSignOn()) {
            $config['registration'] = [
                'localGetLoginStatusFunction' => new \Zend_Json_Expr('localGetLoginStatusFunction'),
                'localRegistrationUrl' => $this->urlBuilder->getBaseUrl() . 'turnto/sso/login',
                'localGetUserInfoFunction' => new \Zend_Json_Expr('localGetUserInfoFunction'),
                'localLogoutFunction' => new \Zend_Json_Expr('localLogoutFunction')
            ];
        }

        return $config;
    }
}
