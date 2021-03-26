<?php
/**
 * TurnTo_SocialCommerce
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * @copyright  Copyright (c) 2018 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Block\Widget;

use Magento\Framework\Exception\LocalizedException;
use TurnTo\SocialCommerce\Helper\Config as TurnToConfigHelper;

class LandingPage extends \Magento\Framework\View\Element\AbstractBlock
{

    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        array $data = []
    )
    {
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
        /** @var \TurnTo\SocialCommerce\Block\TurnToConfig $landingPageBlock */
        try {
            $landingPageBlock = $this->getLayout()->createBlock(
                \TurnTo\SocialCommerce\Block\TurnToConfig::class,
                'turnto.config.landingPage'
            );
        } catch (LocalizedException $e) {
            return '';
        }

        $landingPageBlock->setConfigData(['pageId' => 'email-landing-page']);
        $configHtml = $landingPageBlock->toHtml();
        $landingPageDiv = "<div id=\"tt-embedded-submission\"></div>";

        return $configHtml . "\n" . $landingPageDiv;
    }

}
