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
use TurnTo\SocialCommerce\ViewModel\Widget;

class LandingPage extends Template implements BlockInterface
{
    /**
     * @var string
     */
    protected $_template = "TurnTo_SocialCommerce::widget/landing_page.phtml";

    /**
     * @var Widget
     */
    protected $viewModel;

    /**
     * @param Context $context
     * @param Widget $viewModel
     * @param array $data
     */
    public function __construct(
        Context $context,
        Widget $viewModel,
        array $data = []
    ) {
        $this->viewModel = $viewModel;
        parent::__construct($context, $data);
    }

    /**
     * Creates a TurnTo config block and outputs its html content
     *
     * @return string
     */
    public function getTurnToConfigHtml()
    {
        /** @var TurnToConfig $landingPageBlock */
        try {
            $landingPageBlock = $this->getLayout()->createBlock(
                TurnToConfig::class,
                'turnto.config.landingPage',
                [
                    'data' => [
                        'view_model' => $this->getViewModel(),
                    ],
                ]
            );
        } catch (LocalizedException $e) {
            return '';
        }

        $landingPageBlock->setConfigData(['pageId' => 'email-landing-page']);
        return $landingPageBlock->toHtml();
    }

    /**
     * Returns the view model
     *
     * @return Widget
     */
    public function getViewModel(): Widget
    {
        return $this->viewModel;
    }
}
