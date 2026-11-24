<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Controller\Adminhtml\System\ExportCatalog;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Controller\Adminhtml\System\ExportCatalog\Export;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Export\Catalog;

class ExportTest extends TestCase
{
    /**
     * @var Catalog
     */
    protected $catalogExport;
    /**
     * @var Monolog
     */
    protected $logger;
    /**
     * @var array|null
     */
    protected $responseData;

    protected function setUp(): void
    {
        $this->catalogExport = $this->createStub(Catalog::class);
        $this->logger = $this->createStub(Monolog::class);
        $this->responseData = null;
    }

    public function testIsAPostOnlyAdminActionProtectedByTheTurnToConfigurationAcl(): void
    {
        $controller = $this->createController();

        $this->assertInstanceOf(Action::class, $controller);
        $this->assertInstanceOf(HttpPostActionInterface::class, $controller);
        $this->assertNotInstanceOf(HttpGetActionInterface::class, $controller);
        $this->assertSame('TurnTo_SocialCommerce::socialcommerce_general_config', Export::ADMIN_RESOURCE);
    }

    public function testExecuteTransmitsTheCatalogFeedAndReportsSuccess(): void
    {
        $this->catalogExport = $this->createMock(Catalog::class);
        $this->catalogExport->expects($this->once())->method('cronUploadFeed');

        $this->createController()->execute();

        $this->assertSame(['success' => true], $this->responseData);
    }

    public function testExecuteLogsAndReportsFailure(): void
    {
        $failure = new Exception('transmission failure');
        $this->catalogExport->method('cronUploadFeed')->willThrowException($failure);
        $this->logger = $this->createMock(Monolog::class);
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->isString(), ['exception' => $failure, 'message' => 'transmission failure']);

        $this->createController()->execute();

        $this->assertSame(['success' => false], $this->responseData);
    }

    protected function createController(): Export
    {
        $json = $this->createStub(Json::class);
        $json->method('setData')->willReturnCallback(function ($data) use ($json) {
            $this->responseData = $data;

            return $json;
        });
        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($json);

        return new Export($this->createStub(Context::class), $jsonFactory, $this->catalogExport, $this->logger);
    }
}
