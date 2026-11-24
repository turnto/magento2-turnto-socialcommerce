<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use Magento\Framework\Filesystem\File\WriteInterface;
use TurnTo\SocialCommerce\Model\Export\Orders;

/**
 * Exposes the protected batch-loading and feed-writing routines for unit tests.
 */
class TestableOrders extends Orders
{
    public function callWriteOrdersToFeed(WriteInterface $outputFile, $orders, $forceIncludeAllItems)
    {
        return $this->writeOrdersToFeed($outputFile, $orders, $forceIncludeAllItems);
    }

    public function callPreloadShippingAddresses(iterable $orders): void
    {
        $this->preloadShippingAddresses($orders);
    }

    public function callGetProductsIndexedById(array $productIds, int $storeId): array
    {
        return $this->getProductsIndexedById($productIds, $storeId);
    }
}
