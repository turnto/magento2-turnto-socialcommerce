<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Controller\SSO;

use Magento\Customer\Controller\Account\Logout;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;
use Magento\Framework\Stdlib\Cookie\PhpCookieManager;
use Magento\Framework\Stdlib\CookieManagerInterface;

class LogOutSSO extends Logout
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var CookieMetadataFactory
     */
    protected $cookieMetadataFactory;

    /**
     * @var CookieManagerInterface
     */
    protected $cookieManager;

    /**
     * @param Context $context
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param CookieManagerInterface $cookieManager
     * @param Session $customerSession
     */
    public function __construct(
        Context $context,
        CookieMetadataFactory $cookieMetadataFactory,
        CookieManagerInterface $cookieManager,
        Session $customerSession
    ) {
        $this->session = $customerSession;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->cookieManager = $cookieManager;
        parent::__construct($context, $customerSession);
    }

    /**
     * @return Redirect
     * @throws FailureToSendException
     * @throws InputException
     */
    public function execute()
    {
        $lastCustomerId = $this->session->getId();
        $refererUrl = $this->_redirect->getRefererUrl();
        $this->session->logout()->setBeforeAuthUrl($refererUrl)
            ->setLastCustomerId($lastCustomerId);
        if ($this->cookieManager->getCookie('mage-cache-sessid')) {
            $metadata = $this->cookieMetadataFactory->createCookieMetadata();
            $metadata->setPath('/');
            $this->cookieManager->deleteCookie('mage-cache-sessid', $metadata);
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setUrl($refererUrl);
        return $resultRedirect;
    }
}
