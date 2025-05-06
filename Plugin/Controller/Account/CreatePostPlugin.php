<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Plugin\Controller\Account;

use Magento\Customer\Controller\Account\CreatePost;
use Magento\Customer\Model\Session;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;

class CreatePostPlugin
{
    /**
     * @var Session
     */
    protected $customerSession;
    /**
     * @var RedirectFactory
     */
    protected $redirectFactory;
    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * CreatePostPlugin constructor.
     *
     * @param Session $customerSession
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Session $customerSession,
        RedirectFactory $redirectFactory,
        ManagerInterface $messageManager,
    ) {
        $this->customerSession = $customerSession;
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
    }

    /**
     * @param CreatePost $subject
     * @param $result
     * @return Redirect
     */
    public function afterExecute(CreatePost $subject, $result)
    {
        //check for error message on account creation
        $collection = $this->messageManager->getMessages(false);
        $resultRedirectUrl = $this->customerSession->getPdpUrl();
        if (count($collection->getErrors()) > 0 || is_null($resultRedirectUrl)) {
            return $result;
        }

        //if no errors get PDP from session and redirect
        $resultRedirect = $this->redirectFactory->create();
        $resultRedirect->setUrl($resultRedirectUrl);
        $this->customerSession->setPdpUrl(null);
        return $resultRedirect;
    }
}
