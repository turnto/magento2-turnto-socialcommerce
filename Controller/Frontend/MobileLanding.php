<?php

namespace TurnTo\SocialCommerce\Controller\Frontend;

class MobileLanding extends \Magento\Framework\App\Action\Action
{
    /**
     * Return mobile landing page using custom result class
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute() {
        return $this->resultFactory->create(\TurnTo\SocialCommerce\Framework\View\Result\PageBlank::TYPE_PAGE_BLANK);
    }
}
