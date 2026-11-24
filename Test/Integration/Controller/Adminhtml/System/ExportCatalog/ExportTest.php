<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Integration\Controller\Adminhtml\System\ExportCatalog;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\TestFramework\TestCase\AbstractBackendController;
use TurnTo\SocialCommerce\Model\Export\Catalog;
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
    protected $resource = 'TurnTo_SocialCommerce::socialcommerce_general_config';

    /**
     * @var string
     */
    protected $uri = 'backend/turnto/system_exportcatalog/export';

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
        $this->_objectManager->removeSharedInstance(Catalog::class);
        $this->_objectManager->addSharedInstance($this->feedClient, Client::class);
    }

    protected function tearDown(): void
    {
        $this->_objectManager->removeSharedInstance(Client::class);
        $this->_objectManager->removeSharedInstance(Catalog::class);

        parent::tearDown();
    }

    public function testGetRequestsAreRejected(): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->dispatch($this->uri);

        $this->assertSame(404, $this->getResponse()->getHttpResponseCode());
        $this->assertSame([], $this->feedClient->transmissions);
    }

    public function testPostReturnsJsonResult(): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->dispatch($this->uri);

        $this->assertSame(['success' => true], json_decode($this->getResponse()->getBody(), true));
    }
}
