<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Api;

use Exception;

interface JwtInterface
{
    /**
     * @param array|object $payload
     * @return string
     * @throws Exception
     */
    public function getJwt($payload);
}
