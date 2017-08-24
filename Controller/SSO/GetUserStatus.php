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
 * @copyright  Copyright (c) 2017 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Controller\SSO;

use \Magento\Framework\Controller\ResultFactory;

class GetUserStatus extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Framework\Controller\ResultFactory
     */
    protected $resultFactory;

    /**
     * @var \TurnTo\SocialCommerce\Helper\Config
     */
    protected $configHelper;

    /**
     * GetUserStatus constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param ResultFactory $resultFactory
     * @param \TurnTo\SocialCommerce\Helper\Config $configHelper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        ResultFactory $resultFactory,
        \TurnTo\SocialCommerce\Helper\Config $configHelper
    ) {
        $this->customerSession = $customerSession;
        $this->resultFactory = $resultFactory;
        $this->configHelper = $configHelper;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $customer = $this->customerSession->getCustomer();

        if ($customer->getId()) {
            $customerData = [
                'user_auth_token' => $customer->getId(),
                'first_name' => $customer->getFirstname(),
                'last_name' => $customer->getLastname(),
                'email' => $customer->getEmail(),
                'email_confirmed' => true,
                'nickname' => null,
                'issued_at' => time()
            ];
        } else {
            $customerData = [
                'user_auth_token' => null
            ];
        }

        if (!is_null($customerData['user_auth_token'])) {
            $customerData['signature'] = $this->getSignature($customerData);
        }

        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData($customerData);

        return $resultJson;
    }

    public function getSignature($customerData) {
        // Sort the fields on user alphabetically
        ksort($customerData);

        // Build a parameter string to be hashed. Format: key1=value1&key2=value2...
        $params = '';
        foreach ($customerData as $key => $val) {
            // make sure to exclude parameters that are null or ''
            if ($val) {
                $params .= $key . '=' . $val . '&';
            }
        }

        // Remove the last &
        $params = substr($params, 0, -1);

        $authKey = $this->configHelper->getAuthorizationKey();

        // Hash the parameter string and base64 encode it because the hash result is binary
        $signature = base64_encode(hash_hmac('sha256', $params, $authKey, true));

        return $signature;
    }
}
