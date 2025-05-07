<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Data;

use TurnTo\SocialCommerce\Api\TurnToConfigDataSourceInterface;
use TurnTo\SocialCommerce\Model\Config;

class MobileLandingConfig implements TurnToConfigDataSourceInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function getData()
    {
        return [
            'siteKey' => $this->config->getSiteKey(),
            'host' => $this->config->getUrlWithoutProtocol(Config::PRODUCT_API_URL),
            'staticHost' => $this->config->getUrlWithoutProtocol(Config::PRODUCT_STATIC_API_URL),
            'skipCssLoad' => false,
            'setupType' => 'mobileTT'
        ];
    }
}
