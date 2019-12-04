<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2019 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Plugin\Controller\Account;


class CreatePostPlugin
{

    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;
    /**
     * @var \Magento\Customer\Model\SessionFactory
     */
    private $customerSessionFactory;
    /**
     * @var \Magento\Customer\Model\Session
     */
    private $customerSession;
    /**
     * @var \Magento\Framework\Controller\Result\RedirectFactory
     */
    private $redirectFactory;
    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    private $messageManager;

    public function __construct(\Magento\Customer\Model\Session $customerSession,
                                \Magento\Framework\Controller\Result\RedirectFactory $redirectFactory,
                                \Magento\Framework\Message\ManagerInterface $messageManager


    )
    {

        $this->customerSession = $customerSession;
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
    }

    public function afterExecute(\Magento\Customer\Controller\Account\CreatePost $subject, $result)
    {

        //check for error message on account creation
        $collection = $this->messageManager->getMessages(false);
        $resultRedirectUrl = $this->customerSession->getPdpUrl();
        if (count($collection->getErrors()) > 0 && $resultRedirectUrl) {
            return $result;
        }

        //if no errors get PDP from session and redirect
        $resultRedirect = $this->redirectFactory->create();
        $resultRedirect->setUrl();
        $this->customerSession->setPdpUrl(null);
        return $resultRedirect;


    }
}