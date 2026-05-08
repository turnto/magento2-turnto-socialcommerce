<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Api;

interface SsoResponseCodeInterface
{
    /**
     * Payload could not be parsed or was missing required fields.
     */
    public const ERROR_CODE_INVALID_PAYLOAD = 'INVALID_PAYLOAD';

    /**
     * TurnTo authorization key is missing or invalid in config.
     */
    public const ERROR_CODE_MISSING_AUTH_KEY = 'MISSING_AUTH_KEY';

    /**
     * JWT encode failed for unexpected reasons.
     */
    public const ERROR_CODE_GENERATION_FAILED = 'JWT_GENERATION_FAILED';

    /**
     * SSO endpoint returned a malformed response.
     */
    public const ERROR_CODE_INVALID_RESPONSE = 'INVALID_RESPONSE';
}
