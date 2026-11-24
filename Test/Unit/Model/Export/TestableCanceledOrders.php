<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use Magento\Framework\Filesystem\File\WriteInterface;
use TurnTo\SocialCommerce\Model\Export\CanceledOrders;

/**
 * Exposes the protected feed-writing routine so the batch/streaming behavior can be tested.
 */
class TestableCanceledOrders extends CanceledOrders
{
    public function callWriteOrdersToFeed(WriteInterface $outputFile, $orders, $forceIncludeAllItems): void
    {
        $this->writeOrdersToFeed($outputFile, $orders, $forceIncludeAllItems);
    }
}
