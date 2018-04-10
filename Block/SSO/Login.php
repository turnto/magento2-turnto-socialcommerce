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

namespace TurnTo\SocialCommerce\Block\SSO;

class Login extends \Magento\Customer\Block\Form\Login
{
    /**
     * @var \Magento\Customer\Block\Form\Login\Info
     */
    protected $loginInfo;

    /**
     * Login constructor.
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Customer\Model\Url $customerUrl
     * @param \Magento\Customer\Block\Form\Login\Info $loginInfo
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Customer\Model\Url $customerUrl,
        \Magento\Customer\Block\Form\Login\Info $loginInfo,
        array $data = []
    ){
        $this->loginInfo = $loginInfo;
        parent::__construct($context, $customerSession, $customerUrl, $data);
    }

    /**
     * @return string
     */
    public function getCreateAccountUrl()
    {
        return $this->loginInfo->getCreateAccountUrl();
    }
}
