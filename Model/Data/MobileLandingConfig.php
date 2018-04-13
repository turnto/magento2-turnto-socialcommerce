<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2018 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Model\Data;

use TurnTo\SocialCommerce\Api\TurnToConfigDataSourceInterface;
use TurnTo\SocialCommerce\Helper\Config as TurnToConfigHelper;
use TurnTo\SocialCommerce\Helper\ConfigProviderHelper;

class MobileLandingConfig implements TurnToConfigDataSourceInterface
{
    /**
     * @var TurnToConfigHelper
     */
    protected $configHelper;

    /**
     * @var ConfigProviderHelper
     */
    protected $configProviderHelper;

    /**
     * @param TurnToConfigHelper   $configHelper
     * @param ConfigProviderHelper $configProviderHelper
     */
    public function __construct(TurnToConfigHelper $configHelper, ConfigProviderHelper $configProviderHelper)
    {
        $this->configHelper = $configHelper;
        $this->configProviderHelper = $configProviderHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function getData()
    {
        $config = [
            'siteKey' => $this->configHelper->getSiteKey(),
            'host' => $this->configHelper->getUrlWithoutProtocol(),
            'staticHost' => $this->configHelper->getStaticUrlWithoutProtocol(),
            'skipCssLoad' => false,
            'setupType' => 'mobileTT'
        ];

        $config = array_merge($config, $this->configProviderHelper->getSingleSignOnConfig());

        return $config;
    }
}
