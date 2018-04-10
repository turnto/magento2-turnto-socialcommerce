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

namespace TurnTo\SocialCommerce\Controller\Frontend;

class MobileLanding extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \TurnTo\SocialCommerce\Helper\Config
     */
    protected $config;

    /**
     * MobileLanding constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \TurnTo\SocialCommerce\Helper\Config $config
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \TurnTo\SocialCommerce\Helper\Config $config
    ){
        $this->config = $config;
        parent::__construct($context);
    }

    /**
     * Return mobile landing page using custom result class
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultPage = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->set($this->config->getMobilePageTitle());

        return $resultPage;
    }
}
