<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Api;

use Exception;

interface FeedClient
{
    /**
     * @param resource|string|\SimpleXMLElement $feedData Feed body, or absolute filesystem path when $feedDataIsLocalPath is true
     * @param string $fileName
     * @param string $feedStyle
     * @param string $storeCode
     * @param bool $feedDataIsLocalPath When true, $feedData is a readable local file path (streamed for upload; avoids loading entire file into memory)
     * @return void
     * @throws Exception
     */
    public function transmitFeedFile(
        $feedData,
        string $fileName,
        string $feedStyle,
        string $storeCode,
        bool $feedDataIsLocalPath = false
    );
}
