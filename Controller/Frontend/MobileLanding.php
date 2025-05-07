<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Controller\Frontend;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use TurnTo\SocialCommerce\Model\Config;

class MobileLanding extends Action
{
    public const MOBILE_PAGE_TITLE = 'Emplifi (TurnTo) Ratings & Reviews';
    /**
     * Return mobile landing page using custom result class
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->set(self::MOBILE_PAGE_TITLE);

        return $resultPage;
    }
}
