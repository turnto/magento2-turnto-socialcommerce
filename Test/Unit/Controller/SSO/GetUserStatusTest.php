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
use TurnTo\SocialCommerce\Controller\SSO\GetUserStatus;
use TurnTo\SocialCommerce\Logger\Monolog;

class GetUserStatusTest extends TestCase
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
        $this->resultFactory = $this->createStub(ResultFactory::class);
        $this->customerSession = $this->createStub(Session::class);
        $this->jwt = $this->createStub(JwtInterface::class);
        $this->logger = $this->createStub(Monolog::class);
        $this->capturedResponse = null;

        $json = $this->createStub(Json::class);
        $json->method('setData')->willReturnCallback(
            function ($data) use ($json) {
                $this->capturedResponse = $data;

                return $json;
            }
        );
        $this->resultFactory->method('create')->willReturn($json);
    }

    protected function createController(): GetUserStatus
    {
        return new GetUserStatus($this->resultFactory, $this->customerSession, $this->jwt, $this->logger);
    }

    public function testControllerNoLongerExtendsDeprecatedActionButIsAGetAction(): void
    {
        $controller = $this->createController();

        $this->assertInstanceOf(HttpGetActionInterface::class, $controller);
        $this->assertNotInstanceOf(Action::class, $controller);
    }

    public function testExecuteReturnsLoggedOutResponseForGuest(): void
    {
        $customer = $this->createStub(Customer::class);
        $customer->method('getId')->willReturn(null);
        $this->customerSession->method('getCustomer')->willReturn($customer);

        $this->jwt = $this->createMock(JwtInterface::class);
        $this->jwt->expects($this->never())->method('getJwt');

        $this->createController()->execute();

        $this->assertTrue($this->capturedResponse['success']);
        $this->assertFalse($this->capturedResponse['logged_in']);
        $this->assertNull($this->capturedResponse['jwt']);
    }

    public function testExecuteReturnsJwtForLoggedInCustomer(): void
    {
        $customer = $this->createStub(Customer::class);
        $customer->method('getId')->willReturn(5);
        $customer->method('__call')->willReturnCallback(function ($method) {
            $data = ['getFirstname' => 'Jane', 'getLastname' => 'Doe', 'getEmail' => 'jane@example.com'];

            return $data[$method] ?? null;
        });
        $this->customerSession->method('getCustomer')->willReturn($customer);

        $this->jwt = $this->createMock(JwtInterface::class);
        $this->jwt->expects($this->once())
            ->method('getJwt')
            ->with($this->callback(function (array $payload) {
                return $payload['ua'] === 5
                    && $payload['fn'] === 'Jane'
                    && $payload['ln'] === 'Doe'
                    && $payload['e'] === 'jane@example.com';
            }))
            ->willReturn('signed-token');

        $this->createController()->execute();

        $this->assertTrue($this->capturedResponse['success']);
        $this->assertTrue($this->capturedResponse['logged_in']);
        $this->assertSame('signed-token', $this->capturedResponse['jwt']);
    }
}
