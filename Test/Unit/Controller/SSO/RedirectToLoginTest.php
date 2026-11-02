<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Controller\SSO;

use Magento\Customer\Model\Session;
use Magento\Customer\Model\SessionFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Controller\SSO\RedirectToLogin;
use TurnTo\SocialCommerce\Model\Config;

class RedirectToLoginTest extends TestCase
{
    /**
     * @var SessionFactory
     */
    protected $customerSessionFactory;
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var RequestInterface
     */
    protected $request;
    /**
     * @var RedirectInterface
     */
    protected $redirect;
    /**
     * @var UrlInterface
     */
    protected $url;
    /**
     * @var RedirectFactory
     */
    protected $resultRedirectFactory;
    /**
     * @var ManagerInterface
     */
    protected $messageManager;
    /**
     * @var Redirect
     */
    protected $resultRedirect;

    protected function setUp(): void
    {
        $this->customerSessionFactory = $this->createStub(SessionFactory::class);
        $this->config = $this->createStub(Config::class);
        $this->request = $this->createStub(RequestInterface::class);
        $this->redirect = $this->createStub(RedirectInterface::class);
        $this->url = $this->createStub(UrlInterface::class);
        $this->resultRedirectFactory = $this->createStub(RedirectFactory::class);
        $this->messageManager = $this->createStub(ManagerInterface::class);

        $this->resultRedirect = $this->createStub(Redirect::class);
        $this->resultRedirect->method('setUrl')->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturnCallback(function () {
            return $this->resultRedirect;
        });
    }

    protected function createController(): RedirectToLogin
    {
        return new RedirectToLogin(
            $this->customerSessionFactory,
            $this->config,
            $this->request,
            $this->redirect,
            $this->url,
            $this->resultRedirectFactory,
            $this->messageManager
        );
    }

    public function testControllerNoLongerExtendsDeprecatedActionButIsAGetAction(): void
    {
        $controller = $this->createController();

        $this->assertInstanceOf(HttpGetActionInterface::class, $controller);
        $this->assertNotInstanceOf(Action::class, $controller);
    }

    public function testExecuteBuildsLoginRedirectAndStoresPdpUrl(): void
    {
        $this->redirect->method('getRefererUrl')->willReturn('https://shop.test/product/1');
        $this->url = $this->createMock(UrlInterface::class);
        $this->url->expects($this->once())
            ->method('getUrl')
            ->with('customer/account/login', ['referer' => base64_encode('https://shop.test/product/1')])
            ->willReturn('https://shop.test/customer/account/login');

        $this->resultRedirect = $this->createMock(Redirect::class);
        $this->resultRedirect->expects($this->once())
            ->method('setUrl')
            ->with('https://shop.test/customer/account/login')
            ->willReturnSelf();

        $this->request->method('getParam')->willReturn(null);
        // No action param => no notice message added.
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->messageManager->expects($this->never())->method('addNoticeMessage');

        $session = $this->createCustomerSession();
        $session->expects($this->once())
            ->method('__call')
            ->with('setPdpUrl', ['https://shop.test/product/1']);
        $this->customerSessionFactory->method('create')->willReturn($session);

        $result = $this->createController()->execute();

        $this->assertSame($this->resultRedirect, $result);
    }

    public function testExecuteAddsNoticeMessageForReviewCreateAction(): void
    {
        $this->redirect->method('getRefererUrl')->willReturn('https://shop.test/product/1');
        $this->url->method('getUrl')->willReturn('https://shop.test/customer/account/login');

        $this->request->method('getParam')->willReturnCallback(
            function ($key) {
                if ($key === 'action') {
                    return 'REVIEW_CREATE';
                }
                if ($key === 'authSetting') {
                    return 'PURCHASE_REQUIRED';
                }

                return null;
            }
        );
        $this->config = $this->createMock(Config::class);
        $this->config->expects($this->once())
            ->method('getConfigValue')
            ->with(Config::SSO_REVIEW_MSG_PUR_REQ)
            ->willReturn('You must purchase before reviewing.');
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->messageManager->expects($this->once())
            ->method('addNoticeMessage')
            ->with('You must purchase before reviewing.');

        $this->customerSessionFactory->method('create')->willReturn($this->createStub(Session::class));

        $this->createController()->execute();
    }

    /**
     * setPdpUrl() is a magic data setter on the customer session, handled by SessionManager::__call().
     *
     * @return Session&MockObject
     */
    protected function createCustomerSession(): Session
    {
        return $this->createPartialMock(Session::class, ['__call']);
    }
}
