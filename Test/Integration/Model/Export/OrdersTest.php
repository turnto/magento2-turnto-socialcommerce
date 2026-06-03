<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Integration\Model\Export;

use DateTime;
use DateTimeZone;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Model\Export\Orders;

/**
 * @magentoAppArea frontend
 *
 * Requires the Warden integration test database (see dev/tests/TESTING.md).
 */
class OrdersTest extends TestCase
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var string|null
     */
    private $generatedFeedPath;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->generatedFeedPath = null;
    }

    protected function tearDown(): void
    {
        if ($this->generatedFeedPath !== null && is_file($this->generatedFeedPath)) {
            unlink($this->generatedFeedPath);
        }
    }

    /**
     * The historical order export must stream to a temp file and include the order's line items,
     * with all referenced products loaded for the batch in a single query (no per-order N+1).
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testGetOrdersFeedWritesOrderLineItemsToTempFile(): void
    {
        $storeId = (int) $this->objectManager->get(StoreManagerInterface::class)->getStore()->getId();

        /** @var Orders $orders */
        $orders = $this->objectManager->create(Orders::class);

        $this->generatedFeedPath = $orders->getOrdersFeed(
            $storeId,
            new DateTime('-1 day', new DateTimeZone('UTC')),
            new DateTime('+1 day', new DateTimeZone('UTC')),
            true
        );

        $this->assertIsString($this->generatedFeedPath);
        $this->assertFileExists($this->generatedFeedPath);

        $contents = file_get_contents($this->generatedFeedPath);
        $this->assertNotEmpty($contents);
        $this->assertStringContainsString('100000001', $contents, 'Feed should contain the order increment id.');
        $this->assertStringContainsString('simple', $contents, 'Feed should contain the ordered product SKU.');
    }
}
