<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Service\GoogleFeed;

use TurnTo\SocialCommerce\Service\GoogleFeed\Client;

class TestableClient extends Client
{
    /**
     * @inheritdoc
     */
    protected function getRetryDelayMicroseconds(int $attempt): int
    {
        return 0;
    }
}
