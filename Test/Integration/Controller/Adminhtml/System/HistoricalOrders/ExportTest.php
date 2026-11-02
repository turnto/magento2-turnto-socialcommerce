<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Integration\Controller\Adminhtml\System\HistoricalOrders;

use DateTime;
use DateTimeZone;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Message\MessageInterface;
use Magento\TestFramework\TestCase\AbstractBackendController;
use TurnTo\SocialCommerce\Model\Export\Orders;
use TurnTo\SocialCommerce\Model\Export\TemporaryFeedFile;
use TurnTo\SocialCommerce\Service\GoogleFeed\Client;
use TurnTo\SocialCommerce\Test\Integration\Stub\FeedClientStub;

/**
 * @magentoAppArea adminhtml
 *
 * Requires the Warden integration test database (see dev/tests/TESTING.md).
 */
class ExportTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = 'Magento_Backend::content';

    /**
     * @var string
     */
    protected $uri = 'backend/turnto/system_historicalorders/export';

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    /**
     * @var FeedClientStub
     */
    protected $feedClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->feedClient = new FeedClientStub();
        $this->_objectManager->removeSharedInstance(TemporaryFeedFile::class);
        $this->_objectManager->addSharedInstance($this->feedClient, Client::class);
    }

    protected function tearDown(): void
    {
        $this->_objectManager->removeSharedInstance(Client::class);
        $this->_objectManager->removeSharedInstance(TemporaryFeedFile::class);

        parent::tearDown();
    }

    public function testGetRequestsAreRejected(): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->dispatch($this->uri);

        $this->assertSame(404, $this->getResponse()->getHttpResponseCode());
        $this->assertSame([], $this->feedClient->transmissions);
    }

    /**
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testPostExportsTheOrdersInTheDateRangeAndRedirectsBack(): void
    {
        $today = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d');
        $this->getRequest()
            ->setMethod(HttpRequest::METHOD_POST)
            ->setPostValue(['from_date' => $today, 'to_date' => $today, 'store_ids' => '1']);

        $this->dispatch($this->uri);

        $this->assertRedirect($this->stringContains('turnto/system_historicalorders'));
        $this->assertSessionMessages(
            $this->equalTo([(string) __('Orders exported successfully.')]),
            MessageInterface::TYPE_SUCCESS
        );
        $this->assertCount(1, $this->feedClient->transmissions);
        $transmission = $this->feedClient->transmissions[0];
        $this->assertSame(Orders::FEED_NAME, $transmission['file_name']);
        $this->assertSame(Orders::FEED_STYLE, $transmission['feed_style']);
        $this->assertSame('default', $transmission['store_code']);
        $this->assertStringStartsWith("ORDERID\t", $transmission['contents']);
        $this->assertStringContainsString('100000001', $transmission['contents']);
    }
}
