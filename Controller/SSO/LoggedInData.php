<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2020 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Controller\SSO;


use Magento\Framework\Controller\ResultFactory;
use TurnTo\SocialCommerce\Helper\firebase\JWT;

class LoggedInData  extends \Magento\Framework\App\Action\Action
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
     * @param \TurnTo\SocialCommerce\Helper\Config $configHelper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \TurnTo\SocialCommerce\Helper\Config $configHelper
    ) {
        $this->customerSession = $customerSession;
        $this->resultFactory = $context->getResultFactory();
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
                'payload' => [
                    //add unique key
                    'ua' => $customer->getId(),
                    'iss' => 'TurnTo',
                    'exp' => time() + 86400
                ]
            ];
        } else {
            $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $resultJson->setData(['jwt'=>null]);
            return $resultJson;

        }

        if (!is_null($customerData['payload']['user_auth_token'])) {
            $customerData['signature'] = $this->getSignature($customerData['payload']);
        }


        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData(['jwt' => $this->getUserJWTToken($customerData['payload'])]);

        return $resultJson;
    }

    /**
     * Creates a signature that authenticates the user information with TurnTo's servers
     * https://docs.google.com/document/d/1AEpganoUMVbDyqlYBctN9m9r3J8v8hug5haFkX81934/edit
     * Section: Computing the signature
     *
     * @param $customerData
     * @return mixed
     */
    public function getSignature($customerData) {
        // Sort the fields on user alphabetically
        ksort($customerData);
        $params = urldecode(http_build_query($customerData, null, '&', PHP_QUERY_RFC3986));
        $authKey = $this->configHelper->getAuthorizationKey();

        // Hash the parameter string and base64 encode it because the hash result is binary
        $signature = base64_encode(hash_hmac('sha256', $params, $authKey, true));

        return $signature;
    }

    /**
     * @param $customer
     * @return string
     */
    public function getUserJWTToken($customer){

        if(!$customer) {
            return "error";
        }

        return JWT::encode($customer, $this->configHelper->getAuthorizationKey(), 'HS256');
    }
}
