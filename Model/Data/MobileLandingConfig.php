<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2018 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Model\Data;

use TurnTo\SocialCommerce\Api\TurnToConfigDataSourceInterface;
use TurnTo\SocialCommerce\Helper\Config as TurnToConfigHelper;

class MobileLandingConfig implements TurnToConfigDataSourceInterface
{
    /**
     * @var TurnToConfigHelper
     */
    protected $configHelper;



    /**
     * @param TurnToConfigHelper   $configHelper
     * @param ConfigProviderHelper $configProviderHelper
     */
    public function __construct(TurnToConfigHelper $configHelper)
    {
        $this->configHelper = $configHelper;

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


        return $config;
    }
}
