<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2019 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Controller\SSO;


use Magento\Framework\App\Action\Context;

class RedirectToLogin extends  \Magento\Framework\App\Action\Action
{

    const REVIEW_MSG = "We need to know who you are before we post your review. Please login or register to continue.";
    const QUESTION_MSG = "Your question has been submitted. Please login or register to have answers emailed to you.";
    const ANSWER_MSG =  "Please login or register to complete your submission.";
    const REPLY_MSG =  "Please login or register to complete your submission.";

    /**
     * @var Magento\Framework\UrlInterface
     */
    private $uriInterface;

    public function __construct(Context $context, \Magento\Framework\Message\ManagerInterface $messageManager, \Magento\Framework\UrlInterface $uriInterface) {
        $this->messageManager = $messageManager;
        parent::__construct($context);
        $this->uriInterface = $uriInterface;
    }

    public function execute() {
        $url = $this->_redirect->getRefererUrl();

        $resultRedirect = $this->resultRedirectFactory->create();
        $login_url = $this->uriInterface
            ->getUrl('customer/account/login',
                array('referer' => base64_encode($url))
            );
        $resultRedirect->setPath($login_url);
        $this->messageManager->addErrorMessage($this->getMessage());
        return $resultRedirect;
    }

    public function getMessage(){
       $action =  $this->getRequest()->getParam('action');
       switch ($action){
           case "QUESTION_CREATE":
               return self::QUESTION_MSG;
           case "ANSWER_CREATE":
               return self::ANSWER_MSG;
           case "REVIEW_CREATE":
               return self::REVIEW_MSG;
           case "REPLY_CREATE":
               return self::REPLY_MSG;
           default:
               return "";
       }

    }

}

