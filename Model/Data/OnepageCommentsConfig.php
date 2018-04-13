<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2018 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Model\Data;

use TurnTo\SocialCommerce\Api\TurnToConfigDataSourceInterface;

class OnepageCommentsConfig implements TurnToConfigDataSourceInterface
{
    /**
     * @var \TurnTo\SocialCommerce\Helper\Config
     */
    protected $turnToConfigHelper;

    /**
     * @param \TurnTo\SocialCommerce\Helper\Config $turnToConfigHelper
     */
    public function __construct(\TurnTo\SocialCommerce\Helper\Config $turnToConfigHelper)
    {
        $this->turnToConfigHelper = $turnToConfigHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function getData()
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
