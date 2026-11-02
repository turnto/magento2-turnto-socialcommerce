<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Api;

use Exception;

interface FeedClient
{
    /**
     * Transmit feed file
     *
     * @param $feedData
     * @param string $fileName
     * @param string $feedStyle
     * @param string $storeCode
     * @return void
     * @throws Exception
     */
    public function transmitFeedFile($feedData, string $fileName, string $feedStyle, string $storeCode);
}
