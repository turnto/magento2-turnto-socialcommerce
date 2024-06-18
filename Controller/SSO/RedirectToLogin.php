<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Controller\SSO;

use Magento\Customer\Model\SessionFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use TurnTo\SocialCommerce\Model\Config\Sso;

class RedirectToLogin extends Action
{
    /**
     * @var SessionFactory
     */
    protected $customerSessionFactory;
    /**
     * @var Sso
     */
    protected $ssoConfig;

    /**
     * @param Context $context
     * @param Sso $ssoConfig
     * @param SessionFactory $customerSessionFactory
     */
    public function __construct(
        Context $context,
        SessionFactory $customerSessionFactory,
        Sso $ssoConfig,
    ) {
        parent::__construct($context);
        $this->customerSessionFactory = $customerSessionFactory;
        $this->ssoConfig = $ssoConfig;
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
                    return $this->ssoConfig->getQuestionMsgAnon();
                }
                return $this->ssoConfig->getQuestionMsg();
            case "ANSWER_CREATE":
                return $this->ssoConfig->getAnswerMessage();
            case "REVIEW_CREATE":
                if ($this->getRequest()->getParam('authSetting') === 'PURCHASE_REQUIRED') {
                    return $this->ssoConfig->getReviewMsgPurchaseReq();
                }
                return $this->ssoConfig->getReviewMsg();
            case "REPLY_CREATE":
                return $this->ssoConfig->getReplyMsg();
            default:
                return "";
        }
    }
}

