<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Api;

use Exception;

/**
 * Optional capability for feed clients that can upload a feed straight from a local file.
 *
 * Kept separate from FeedClient so existing FeedClient implementations remain valid.
 */
interface FeedFileClientInterface
{
    /**
     * Transmit a feed that has already been written to disk, streaming it instead of loading it into memory
     *
     * @param string $filePath Absolute path to a readable local file
     * @param string $fileName
     * @param string $feedStyle
     * @param string $storeCode
     * @return void
     * @throws Exception
     */
    public function transmitFeedFromPath(string $filePath, string $fileName, string $feedStyle, string $storeCode);
}
