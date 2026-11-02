<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Controller\Adminhtml\System\HistoricalOrders;

use DateTime;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Controller\Adminhtml\System\HistoricalOrders\Export;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Export\Orders;
use TurnTo\SocialCommerce\Model\Export\TemporaryFeedFile;

class ExportTest extends TestCase
{
    protected const PARAMS = ['from_date' => '2026-01-01', 'to_date' => '2026-01-31', 'store_ids' => '1'];

    /**
     * @var Orders
     */
    protected $ordersExport;
    /**
     * @var TemporaryFeedFile
     */
    protected $temporaryFeedFile;
    /**
     * @var ManagerInterface
     */
    protected $messageManager;
    /**
     * @var Monolog
     */
    protected $logger;
    /**
     * @var Redirect
     */
    protected $redirect;

    protected function setUp(): void
    {
        $this->ordersExport = $this->createStub(Orders::class);
        $this->temporaryFeedFile = $this->createStub(TemporaryFeedFile::class);
        $this->messageManager = $this->createStub(ManagerInterface::class);
        $this->logger = $this->createStub(Monolog::class);
        $this->redirect = $this->createStub(Redirect::class);
        $this->redirect->method('setPath')->willReturnSelf();
    }

    public function testIsAPostOnlyAdminActionProtectedByTheHistoricalOrdersMenuAcl(): void
    {
        $controller = $this->createController();

        $this->assertInstanceOf(Action::class, $controller);
        $this->assertInstanceOf(HttpPostActionInterface::class, $controller);
        $this->assertNotInstanceOf(HttpGetActionInterface::class, $controller);
        $this->assertSame('Magento_Backend::content', Export::ADMIN_RESOURCE);
    }

    public function testExecuteExportsTheWholeDateRangeTransmitsTheFileAndRedirectsBack(): void
    {
        $this->ordersExport = $this->createMock(Orders::class);
        $this->ordersExport->expects($this->once())
            ->method('createOrdersFeedFile')
            ->with(
                '1',
                $this->callback(function (DateTime $from) {
                    return $from->format('Y-m-d H:i:s e') === '2026-01-01 00:00:00 UTC';
                }),
                $this->callback(function (DateTime $to) {
                    return $to->format('Y-m-d H:i:s e') === '2026-01-31 23:59:59 UTC';
                }),
                true
            )
            ->willReturn('/var/www/html/var/tmp/orders.tsv');
        $this->temporaryFeedFile = $this->createMock(TemporaryFeedFile::class);
        $this->temporaryFeedFile->expects($this->once())
            ->method('transmitAndDelete')
            ->with('/var/www/html/var/tmp/orders.tsv', Orders::FEED_NAME, Orders::FEED_STYLE, 'default');
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->messageManager->expects($this->once())->method('addSuccessMessage');
        $this->messageManager->expects($this->never())->method('addErrorMessage');
        $this->redirect = $this->createMock(Redirect::class);
        $this->redirect->expects($this->once())->method('setPath')->with('*/*/')->willReturnSelf();

        $this->assertSame($this->redirect, $this->createController()->execute());
    }

    public function testExecuteLogsTheRequestAndShowsAnErrorWhenTheExportFails(): void
    {
        $failure = new Exception('export failure');
        $this->ordersExport->method('createOrdersFeedFile')->willThrowException($failure);
        $this->temporaryFeedFile = $this->createMock(TemporaryFeedFile::class);
        $this->temporaryFeedFile->expects($this->never())->method('transmitAndDelete');
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->messageManager->expects($this->once())->method('addErrorMessage');
        $this->messageManager->expects($this->never())->method('addSuccessMessage');
        $this->logger = $this->createMock(Monolog::class);
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->isString(), [
                'store_id' => '1',
                'from_date' => '2026-01-01',
                'to_date' => '2026-01-31',
                'exception' => $failure,
            ]);

        $this->assertSame($this->redirect, $this->createController()->execute());
    }

    protected function createController(): Export
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(function ($key) {
            return self::PARAMS[$key] ?? null;
        });
        $resultFactory = $this->createStub(ResultFactory::class);
        $resultFactory->method('create')->willReturnCallback(function ($type) {
            $this->assertSame(ResultFactory::TYPE_REDIRECT, $type);

            return $this->redirect;
        });
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getMessageManager')->willReturn($this->messageManager);
        $context->method('getResultFactory')->willReturn($resultFactory);

        $store = $this->createStub(Store::class);
        $store->method('getCode')->willReturn('default');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new Export(
            $context,
            $this->ordersExport,
            $this->logger,
            new DateTimeFactory(),
            $storeManager,
            $this->temporaryFeedFile
        );
    }
}
