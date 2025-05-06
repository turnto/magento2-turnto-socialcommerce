<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Controller\SSO;

use Magento\Customer\Model\SessionFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use TurnTo\SocialCommerce\Model\Config;

class RedirectToLogin extends Action
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
     * @param Context $context
     * @param SessionFactory $customerSessionFactory
     * @param Config $config
     */
    public function __construct(
        Context $context,
        SessionFactory $customerSessionFactory,
        Config $config,
    ) {
        parent::__construct($context);
        $this->customerSessionFactory = $customerSessionFactory;
        $this->config = $config;
    }

    public function execute()
    {
        $url = $this->_redirect->getRefererUrl();
        $login_url = $this->_url->getUrl(
            'customer/account/login',
            ['referer' => base64_encode($url)]
        );
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath($login_url);
        if (!empty($message = $this->getMessage())) {
            $this->messageManager->addNoticeMessage($message);
        }

        //store the PDP in session
        $customerSession = $this->customerSessionFactory->create();
        $customerSession->setPdpUrl($url);

        return $resultRedirect;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        $action = $this->getRequest()->getParam('action');
        switch ($action) {
            case "QUESTION_CREATE":
                if($this->getRequest()->getParam('authSetting') === 'ANONYMOUS'){
                    return $this->config->getConfigValue(Config::SSO_QUESTION_MSG_ANON);
                }
                return $this->config->getConfigValue(Config::SSO_QUESTION_MSG);
            case "ANSWER_CREATE":
                return $this->config->getConfigValue(Config::SSO_ANSWER_MSG);
            case "REVIEW_CREATE":
                if ($this->getRequest()->getParam('authSetting') === 'PURCHASE_REQUIRED') {
                    return $this->config->getConfigValue(Config::SSO_REVIEW_MSG_PUR_REQ);
                }
                return $this->config->getConfigValue(Config::SSO_REVIEW_MSG);
            case "REPLY_CREATE":
                return $this->config->getConfigValue(Config::SSO_REPLY_MSG);
            default:
                return "";
        }
    }
}

