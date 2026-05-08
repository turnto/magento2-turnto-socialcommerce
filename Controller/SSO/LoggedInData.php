<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Controller\SSO;

use Exception;
use InvalidArgumentException;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use TurnTo\SocialCommerce\Logger\Monolog;
use RuntimeException;
use TurnTo\SocialCommerce\Api\SsoResponseCodeInterface;
use TurnTo\SocialCommerce\Api\JwtInterface;

class LoggedInData  extends Action implements SsoResponseCodeInterface
{
    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * @var ResultFactory
     */
    protected $resultFactory;

    /**
     * @var JwtInterface
     */
    protected $jwt;
    /**
     * @var Monolog
     */
    protected $logger;

    /**
     * GetUserStatus constructor.
     * @param Context $context
     * @param ResultFactory $resultFactory
     * @param Session $customerSession
     * @param JwtInterface $jwt
     * @param Monolog $logger
     */
    public function __construct(
        Context $context,
        ResultFactory $resultFactory,
        Session $customerSession,
        JwtInterface $jwt,
        Monolog $logger
    ) {
        $this->customerSession = $customerSession;
        $this->resultFactory = $resultFactory;
        $this->jwt = $jwt;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * Return loggedInData
     * https://docs.turnto.com/en/speedflex-widget-implementation/authentication/speedflex-single-sign-on--sso--integration.html
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $response = [
            'success' => true,
            'logged_in' => false,
            'jwt' => null
        ];
        $customer = $this->customerSession->getCustomer();

        if ($customer->getId()) {
            $response['logged_in'] = true;
            $customerData = [
                'payload' => [
                    'ua' => $customer->getId(),
                    'iss' => 'TurnTo',
                    'exp' => time() + 86400 // current Unix timestamp plus 24 hrs
                ]
            ];

            try {
                $response['jwt'] = $this->jwt->getJwt($customerData['payload']);
            } catch (InvalidArgumentException $e) {
                $this->logger->error('Failed to build SSO JWT payload for logged-in data response', [
                    'customer_id' => $customer->getId(),
                    'error_code' => self::ERROR_CODE_INVALID_PAYLOAD,
                    'exception' => $e
                ]);
                $response['success'] = false;
                $response['error'] = [
                    'code' => self::ERROR_CODE_INVALID_PAYLOAD,
                    'message' => 'Unable to build SSO payload'
                ];
            } catch (RuntimeException $e) {
                $this->logger->error('Failed to generate SSO JWT for logged-in data response', [
                    'customer_id' => $customer->getId(),
                    'error_code' => self::ERROR_CODE_MISSING_AUTH_KEY,
                    'exception' => $e
                ]);
                $response['success'] = false;
                $response['error'] = [
                    'code' => self::ERROR_CODE_MISSING_AUTH_KEY,
                    'message' => 'Missing TurnTo authorization key'
                ];
            } catch (Exception $e) {
                $this->logger->error('Failed to generate SSO JWT for logged-in data response', [
                    'customer_id' => $customer->getId(),
                    'error_code' => self::ERROR_CODE_GENERATION_FAILED,
                    'exception' => $e
                ]);
                $response['success'] = false;
                $response['error'] = [
                    'code' => self::ERROR_CODE_GENERATION_FAILED,
                    'message' => 'Unable to generate SSO token'
                ];
            }
        }

        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData($response);

        return $resultJson;
    }
}
