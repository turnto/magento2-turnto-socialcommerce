<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Integration\Model\Export;

use DateTime;
use DateTimeZone;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Sales\Api\OrderItemRepositoryInterface;
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
    protected $objectManager;

    /**
     * @var string|null
     */
    protected $generatedFeedPath;

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
     * The historical order export streams to a temp file and includes the order's line items.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testCreateOrdersFeedFileWritesOrderLineItemsToTempFile(): void
    {
        $this->generatedFeedPath = $this->createOrders()->createOrdersFeedFile(
            $this->getStoreId(),
            new DateTime('-1 day', new DateTimeZone('UTC')),
            new DateTime('+1 day', new DateTimeZone('UTC')),
            true
        );

        $this->assertFileExists($this->generatedFeedPath);

        $rows = $this->parseFeed((string) file_get_contents($this->generatedFeedPath));
        $this->assertSame('ORDERID', $rows[0][0]);
        $this->assertSame('100000001', $rows[1][0], 'Feed should contain the order increment id.');
        $this->assertSame('simple', $rows[1][9], 'Feed should contain the ordered product SKU.');
        $this->assertSame('11111', $rows[1][6], 'Feed should contain the shipping postcode.');
    }

    /**
     * getOrdersFeed() keeps returning the feed contents and leaves no temp file behind.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testGetOrdersFeedReturnsTheFeedContents(): void
    {
        $tmpFeedsBefore = $this->getTmpOrderFeedFiles();

        $contents = $this->createOrders()->getOrdersFeed(
            $this->getStoreId(),
            new DateTime('-1 day', new DateTimeZone('UTC')),
            new DateTime('+1 day', new DateTimeZone('UTC')),
            true
        );

        $this->assertStringStartsWith("ORDERID\t", $contents);
        $this->assertStringContainsString('100000001', $contents);
        $this->assertSame($tmpFeedsBefore, $this->getTmpOrderFeedFiles());
    }

    /**
     * Virtual items never ship, so the invoice date is their delivery date and they survive the delivery date filter.
     *
     * @magentoDataFixture Magento/Sales/_files/invoice.php
     * @magentoConfigFixture current_store turnto_socialcommerce_configuration/historical_orders_feed/exclude_items_without_delivery_date 1
     */
    public function testCreateOrdersFeedFileUsesTheInvoiceDateAsTheDeliveryDateOfVirtualItems(): void
    {
        $order = $this->objectManager->get(OrderInterfaceFactory::class)->create()->loadByIncrementId('100000001');
        $orderItemRepository = $this->objectManager->get(OrderItemRepositoryInterface::class);
        foreach ($order->getAllItems() as $item) {
            $item->setIsVirtual(1);
            $orderItemRepository->save($item);
        }
        $invoice = $order->getInvoiceCollection()->getFirstItem();

        $this->generatedFeedPath = $this->createOrders()->createOrdersFeedFile(
            $this->getStoreId(),
            new DateTime('-1 day', new DateTimeZone('UTC')),
            new DateTime('+1 day', new DateTimeZone('UTC')),
            false
        );

        $rows = $this->parseFeed((string) file_get_contents($this->generatedFeedPath));
        $this->assertCount(2, $rows, 'The invoiced virtual item should be exported.');
        $this->assertSame('100000001', $rows[1][0]);
        $this->assertNotEmpty($invoice->getCreatedAt());
        $this->assertSame($invoice->getCreatedAt(), $rows[1][12], 'The delivery date should be the invoice date.');
    }

    protected function createOrders(): Orders
    {
        return $this->objectManager->create(Orders::class);
    }

    protected function getStoreId(): int
    {
        return (int) $this->objectManager->get(StoreManagerInterface::class)->getStore()->getId();
    }

    /**
     * @param string $contents
     * @return array[]
     */
    protected function parseFeed(string $contents): array
    {
        $rows = [];
        foreach (explode("\n", trim($contents)) as $line) {
            $rows[] = str_getcsv($line, "\t", '"', '');
        }

        return $rows;
    }

    /**
     * @return string[]
     */
    protected function getTmpOrderFeedFiles(): array
    {
        $tmpDirectory = $this->objectManager->get(Filesystem::class)->getDirectoryRead(DirectoryList::TMP);

        return glob($tmpDirectory->getAbsolutePath('turnto_orders_feed_*')) ?: [];
    }
}
