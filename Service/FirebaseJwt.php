<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Service;

use TurnTo\SocialCommerce\Api\JwtInterface;
use TurnTo\SocialCommerce\Helper\Config;
use TurnTo\SocialCommerce\Service\FirebaseJwt\JWT;

class FirebaseJwt implements JwtInterface
{
    protected $configHelper;
    /**
     * @var JWT
     */
    protected $jwt;

    public function __construct(
        Config $configHelper,
        JWT $jwt
    ) {
        $this->configHelper = $configHelper;
        $this->jwt = $jwt;
    }

    /**
     * Generate JWT using TurnTo authKey
     * @inheritdoc
     */
    public function getJwt($payload)
    {
        if(empty($payload)) {
            return "Invalid payload";
        }
        $key = $this->getKey();

        return $this->jwt->encode($payload, $key, 'HS256');
    }

    /**
     * @return string
     */
    protected function getKey()
    {
        return $this->configHelper->getAuthorizationKey();
    }
}
