<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Integration\Stub;

use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Api\FeedFileClientInterface;

/**
 * Records feed transmissions instead of sending them to TurnTo.
 */
class FeedClientStub implements FeedClient, FeedFileClientInterface
{
    /**
     * @var array[] Each entry has contents, file_name, feed_style and store_code
     */
    public $transmissions = [];

    /**
     * @inheritdoc
     */
    public function transmitFeedFile($feedData, string $fileName, string $feedStyle, string $storeCode)
    {
        $this->record((string) $feedData, $fileName, $feedStyle, $storeCode);
    }

    /**
     * @inheritdoc
     */
    public function transmitFeedFromPath(string $filePath, string $fileName, string $feedStyle, string $storeCode)
    {
        $this->record((string) file_get_contents($filePath), $fileName, $feedStyle, $storeCode);
    }

    protected function record(string $contents, string $fileName, string $feedStyle, string $storeCode): void
    {
        $this->transmissions[] = [
            'contents' => $contents,
            'file_name' => $fileName,
            'feed_style' => $feedStyle,
            'store_code' => $storeCode,
        ];
    }
}
