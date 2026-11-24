<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Controller\SSO;

use Magento\Customer\Model\SessionFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use TurnTo\SocialCommerce\Model\Config;

class RedirectToLogin implements HttpGetActionInterface
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
     * @param SessionFactory $customerSessionFactory
     * @param Config $config
     * @param RequestInterface $request
     * @param RedirectInterface $redirect
     * @param UrlInterface $url
     * @param RedirectFactory $resultRedirectFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        SessionFactory $customerSessionFactory,
        Config $config,
        RequestInterface $request,
        RedirectInterface $redirect,
        UrlInterface $url,
        RedirectFactory $resultRedirectFactory,
        ManagerInterface $messageManager
    ) {
        $this->customerSessionFactory = $customerSessionFactory;
        $this->config = $config;
        $this->request = $request;
        $this->redirect = $redirect;
        $this->url = $url;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
    }

    /**
     * @return Redirect
     */
    public function execute()
    {
        $url = $this->redirect->getRefererUrl();
        $login_url = $this->url->getUrl(
            'customer/account/login',
            ['referer' => base64_encode($url)]
        );
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setUrl($login_url);
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
        $action = $this->request->getParam('action');
        switch ($action) {
            case "QUESTION_CREATE":
                if ($this->request->getParam('authSetting') === 'ANONYMOUS') {
                    return $this->config->getConfigValue(Config::SSO_QUESTION_MSG_ANON);
                }
                return $this->config->getConfigValue(Config::SSO_QUESTION_MSG);
            case "ANSWER_CREATE":
                return $this->config->getConfigValue(Config::SSO_ANSWER_MSG);
            case "REVIEW_CREATE":
                if ($this->request->getParam('authSetting') === 'PURCHASE_REQUIRED') {
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
