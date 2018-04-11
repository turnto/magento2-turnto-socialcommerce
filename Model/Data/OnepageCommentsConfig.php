<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2018 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Model\Data;

use TurnTo\SocialCommerce\Api\TurnToConfigDataProviderInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface as BlockArgumentInterface;

class OnepageCommentsConfig implements TurnToConfigDataProviderInterface, BlockArgumentInterface
{
    /**
     * @var \TurnTo\SocialCommerce\Helper\Config
     */
    protected $turnToConfigHelper;

    public function __construct(\TurnTo\SocialCommerce\Helper\Config $turnToConfigHelper)
    {
        $this->turnToConfigHelper = $turnToConfigHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function getData(): array
    {
        $config = [
            'siteKey' => $this->turnToConfigHelper->getSiteKey(),
            'orderConfFlowPauseSeconds' => 0,
            'postPurchaseFlow' => true
        ];

        $checkoutCommentsSuccessEnabled = (bool)$this->turnToConfigHelper->getCheckoutCommentsEnabledCheckoutSuccess();

        if ($checkoutCommentsSuccessEnabled) {
            $config['commentCaptureShowUsername'] = true;
            $config['embedCommentCapture'] = true;
        }

        return $config;
    }
}
