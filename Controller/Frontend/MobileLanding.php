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
