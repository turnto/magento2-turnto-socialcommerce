<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Plugin\Controller\Account;

use Magento\Customer\Controller\Account\CreatePost;
use Magento\Customer\Model\Session;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use TurnTo\SocialCommerce\Helper\Config;

class CreatePostPlugin
{
    /**
     * @var Session
     */
    private $customerSession;
    /**
     * @var RedirectFactory
     */
    private $redirectFactory;
    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var Config
     */
    protected $config;

    /**
     * CreatePostPlugin constructor.
     *
     * @param Session                      $customerSession
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface          $messageManager
     * @param Config                  $config
     */
    public function __construct(
        Session $customerSession,
        RedirectFactory $redirectFactory,
        ManagerInterface $messageManager,
        Config $config
    ) {
        $this->customerSession = $customerSession;
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
        $this->config = $config;
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
