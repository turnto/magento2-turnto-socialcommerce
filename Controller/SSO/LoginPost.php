<?php
/**
 * TurnTo_SocialCommerce
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright  Copyright (c) 2016 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Controller\SSO;

use \Magento\Framework\Controller\ResultFactory;

class LoginPost extends \Magento\Customer\Controller\Account\LoginPost
{
    /**
     * @var ResultFactory
     */
    protected $resultFactory;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * LoginPost constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Customer\Api\AccountManagementInterface $customerAccountManagement
     * @param \Magento\Customer\Model\Url $customerHelperData
     * @param \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator
     * @param \Magento\Customer\Model\Account\Redirect $accountRedirect
     * @param ResultFactory $resultFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Customer\Api\AccountManagementInterface $customerAccountManagement,
        \Magento\Customer\Model\Url $customerHelperData,
        \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator,
        \Magento\Customer\Model\Account\Redirect $accountRedirect,
        ResultFactory $resultFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ){
        $this->resultFactory = $resultFactory;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManager;
        parent::__construct(
            $context,
            $customerSession,
            $customerAccountManagement,
            $customerHelperData,
            $formKeyValidator,
            $accountRedirect
        );
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        parent::execute();

        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData(['success' => $this->customerSession->isLoggedIn()]);

        // This allows the post request from the iframe
        $url = $this->_url->getBaseUrl([
            '_type' => \Magento\Framework\UrlInterface::URL_TYPE_LINK,
            '_secure' => $this->storeManager->getStore()->isCurrentlySecure()
        ]);
        $this->getResponse()->setHeader('Access-Control-Allow-Origin', $url);

        return $resultJson;
    }
}
