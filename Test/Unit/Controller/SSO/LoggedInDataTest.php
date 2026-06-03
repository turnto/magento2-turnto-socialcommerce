<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Controller\SSO;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Api\JwtInterface;
use TurnTo\SocialCommerce\Controller\SSO\LoggedInData;
use TurnTo\SocialCommerce\Logger\Monolog;

class LoggedInDataTest extends TestCase
{
    /**
     * @var ResultFactory
     */
    protected $resultFactory;
    /**
     * @var Session
     */
    protected $customerSession;
    /**
     * @var JwtInterface
     */
    protected $jwt;
    /**
     * @var Monolog
     */
    protected $logger;
    /**
     * @var array|null
     */
    protected $capturedResponse;

    protected function setUp(): void
    {
        $this->resultFactory = $this->createMock(ResultFactory::class);
        $this->customerSession = $this->createMock(Session::class);
        $this->jwt = $this->createMock(JwtInterface::class);
        $this->logger = $this->createMock(Monolog::class);
        $this->capturedResponse = null;

        $json = $this->createMock(Json::class);
        $json->method('setData')->willReturnCallback(
            function ($data) use ($json) {
                $this->capturedResponse = $data;

                return $json;
            }
        );
        $this->resultFactory->method('create')->willReturn($json);
    }

    protected function createController(): LoggedInData
    {
        return new LoggedInData($this->resultFactory, $this->customerSession, $this->jwt, $this->logger);
    }

    public function testControllerNoLongerExtendsDeprecatedActionButIsAGetAction(): void
    {
        $controller = $this->createController();

        $this->assertInstanceOf(HttpGetActionInterface::class, $controller);
        $this->assertNotInstanceOf(Action::class, $controller);
    }

    public function testExecuteReturnsLoggedOutResponseForGuest(): void
    {
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn(null);
        $this->customerSession->method('getCustomer')->willReturn($customer);

        $this->jwt->expects($this->never())->method('getJwt');

        $this->createController()->execute();

        $this->assertFalse($this->capturedResponse['logged_in']);
        $this->assertNull($this->capturedResponse['jwt']);
    }

    public function testExecuteReturnsJwtForLoggedInCustomer(): void
    {
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn(9);
        $this->customerSession->method('getCustomer')->willReturn($customer);

        $this->jwt->expects($this->once())
            ->method('getJwt')
            ->willReturn('signed-token');

        $this->createController()->execute();

        $this->assertTrue($this->capturedResponse['success']);
        $this->assertTrue($this->capturedResponse['logged_in']);
        $this->assertSame('signed-token', $this->capturedResponse['jwt']);
    }
}
