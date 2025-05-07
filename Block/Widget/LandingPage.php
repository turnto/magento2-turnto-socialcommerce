<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Block\Widget;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;
use TurnTo\SocialCommerce\Block\TurnToConfig;

class LandingPage extends Template implements BlockInterface
{
    protected $_template = "TurnTo_SocialCommerce::widget/landing_page.phtml";

    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $data
        );
    }

    /**
     * Creates a TurnTo config block and outputs its html content
     * @return string
     */
    public function getTurnToConfigHtml()
    {
        /** @var TurnToConfig $landingPageBlock */
        try {
            $landingPageBlock = $this->getLayout()->createBlock(
                TurnToConfig::class,
                'turnto.config.landingPage'
            );
        } catch (LocalizedException $e) {
            return '';
        }

        $landingPageBlock->setConfigData(['pageId' => 'email-landing-page']);
        return $landingPageBlock->toHtml();
    }
}
