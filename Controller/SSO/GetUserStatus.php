<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Controller\SSO;

use Exception;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use TurnTo\SocialCommerce\Api\JwtInterface;

class GetUserStatus extends Action
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
     * GetUserStatus constructor.
     * @param Context $context
     * @param ResultFactory $resultFactory
     * @param Session $customerSession
     * @param JwtInterface $jwt
     */
    public function __construct(
        Context $context,
        ResultFactory $resultFactory,
        Session $customerSession,
        JwtInterface $jwt
    ) {
        $this->customerSession = $customerSession;
        $this->resultFactory = $resultFactory;
        $this->jwt = $jwt;
        parent::__construct($context);
    }

    /**
     * Return userDataToken
     * https://docs.turnto.com/en/speedflex-widget-implementation/authentication/speedflex-single-sign-on--sso--integration.html#step-2--confirm-a-user-s-logged-in-status-or-provide-a-registration-or-login-form
     *
     * @return ResultInterface
     * @throws Exception
     */
    public function execute()
    {
        $jwt = null;
        $customer = $this->customerSession->getCustomer();

        if ($customer->getId()) {
            $customerData = [
                'payload' => [
                    'ua' => $customer->getId(),
                    'fn' => $customer->getFirstname(),
                    'ln' => $customer->getLastname(),
                    'e' => $customer->getEmail(),
                    'iss' => 'TurnTo',
                    'exp' => time() + 86400 // current Unix timestamp plus 24 hrs
                ]
            ];

            $jwt = $this->jwt->getJwt($customerData['payload']);
        }

        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData(['jwt' => $jwt]);

        return $resultJson;
    }
}
