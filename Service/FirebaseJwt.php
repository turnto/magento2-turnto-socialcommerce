<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Service;

use TurnTo\SocialCommerce\Api\JwtInterface;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Service\FirebaseJwt\JWT;

class FirebaseJwt implements JwtInterface
{
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var JWT
     */
    protected $jwt;

    public function __construct(
        Config $config,
        JWT $jwt
    ) {
        $this->config = $config;
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
        $key = $this->config->getAuthorizationKey();

        return $this->jwt->encode($payload, $key, 'HS256');
    }
}
