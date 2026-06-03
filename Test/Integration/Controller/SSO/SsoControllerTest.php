<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Integration\Controller\SSO;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\TestFramework\TestCase\AbstractController;
use TurnTo\SocialCommerce\Controller\SSO\GetUserStatus;
use TurnTo\SocialCommerce\Controller\SSO\LoggedInData;
use TurnTo\SocialCommerce\Controller\SSO\RedirectToLogin;

/**
 * @magentoAppArea frontend
 *
 * Requires the Warden integration test database (see dev/tests/TESTING.md).
 */
class SsoControllerTest extends AbstractController
{
    public function testGetUserStatusReturnsLoggedOutJsonForGuest(): void
    {
        $this->dispatch('turnto/sso/getuserstatus');

        $payload = $this->decodeJsonResponse();
        $this->assertTrue($payload['success']);
        $this->assertFalse($payload['logged_in']);
        $this->assertNull($payload['jwt']);
    }

    public function testLoggedInDataReturnsLoggedOutJsonForGuest(): void
    {
        $this->dispatch('turnto/sso/loggedindata');

        $payload = $this->decodeJsonResponse();
        $this->assertTrue($payload['success']);
        $this->assertFalse($payload['logged_in']);
        $this->assertNull($payload['jwt']);
    }

    public function testRedirectToLoginRedirectsToCustomerLogin(): void
    {
        $this->dispatch('turnto/sso/redirecttologin');

        $this->assertRedirect($this->stringContains('customer/account/login'));
    }

    /**
     * The refactored controllers must remain valid GET actions without extending the deprecated base class.
     */
    public function testControllersAreGetActionsAndNotDeprecatedActionSubclasses(): void
    {
        foreach ([GetUserStatus::class, LoggedInData::class, RedirectToLogin::class] as $controllerClass) {
            $controller = $this->_objectManager->get($controllerClass);
            $this->assertInstanceOf(HttpGetActionInterface::class, $controller);
            $this->assertNotInstanceOf(Action::class, $controller);
        }
    }

    /**
     * @return array
     */
    private function decodeJsonResponse(): array
    {
        $body = $this->getResponse()->getBody();
        $decoded = json_decode($body, true);
        $this->assertIsArray($decoded, 'Controller response was not valid JSON: ' . $body);

        return $decoded;
    }
}
