<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Service\FirebaseJwt;

use Exception;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * JSON Web Token implementation, based on this spec:
 * https://tools.ietf.org/html/rfc7519
 *
 * PHP version 5
 *
 * @category Authentication
 * @package  Authentication_JWT
 * @author   Neuman Vong <neuman@twilio.com>
 * @author   Anant Narayanan <anant@php.net>
 * @license  http://opensource.org/licenses/BSD-3-Clause 3-clause BSD
 * @link     https://github.com/firebase/php-jwt
 */
class JWT
{
    /**
     * @var array
     */
    public $supported_algs = [
        'HS256' => ['hash_hmac', 'SHA256'],
        'HS384' => ['hash_hmac', 'SHA384'],
        'HS512' => ['hash_hmac', 'SHA512']
    ];
    /**
     * @var Json
     */
    protected $serializer;

    public function __construct(
        Json $serializer
    ) {
        $this->serializer = $serializer;
    }

    /**
     * Converts and signs a PHP array into a JWT string.
     *
     * @param array $payload PHP array
     * @param string $key The secret key.
     * @param string $alg Supported algorithms are 'HS256', 'HS384', 'HS512'
     * @param string|null $keyId
     * @param array|null $head An array with header elements to attach
     *
     * @return string A signed JWT
     * @throws Exception
     */
    public function encode(
        array $payload,
        string $key,
        string $alg,
        string $keyId = null,
        array $head = null
    ) {
        $header = ['typ' => 'JWT', 'alg' => $alg];
        if ($keyId !== null) {
            $header['kid'] = $keyId;
        }
        if (isset($head) && is_array($head)) {
            $header = array_merge($head, $header);
        }
        $segments = [];
        $segments[] = $this->urlsafeB64Encode($this->serializer->serialize($header));
        $segments[] = $this->urlsafeB64Encode($this->serializer->serialize($payload));
        $signing_input = implode('.', $segments);

        $signature = $this->sign($signing_input, $key, $alg);
        $segments[] = $this->urlsafeB64Encode($signature);

        return implode('.', $segments);
    }

    /**
     * Sign a string with a given key and algorithm.
     *
     * @param string $msg  The message to sign
     * @param string  $key  The secret key.
     * @param string $alg  Supported algorithms are 'HS256', 'HS384', 'HS512'
     *
     * @return string An encrypted message
     *
     * @throws Exception Unsupported algorithm or bad key was specified
     */
    public function sign(
        string $msg,
        string $key,
        string $alg
    ) {
        if (empty($this->supported_algs[$alg])) {
            throw new Exception('Algorithm not supported');
        }
        list($function, $algorithm) = $this->supported_algs[$alg];
        if ($function === 'hash_hmac') {
            return hash_hmac($algorithm, $msg, $key, true);
        }

        throw new Exception('Algorithm not supported');
    }

    /**
     * Encode a string with URL-safe Base64.
     *
     * @param string $input The string you want encoded
     *
     * @return string The base64 encode of what you passed in
     */
    public static function urlsafeB64Encode(string $input): string
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }
}
