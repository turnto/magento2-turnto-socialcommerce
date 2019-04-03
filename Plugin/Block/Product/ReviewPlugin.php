<?php
/**
 * @category    ClassyLlama
 * @copyright   Copyright (c) 2019 Classy Llama Studios, LLC
 * @author      sean.templeton
 */
namespace TurnTo\SocialCommerce\Plugin\Block\Product;

class ReviewPlugin
{
    /**
     * @var \TurnTo\SocialCommerce\Helper\Config
     */
    protected $config;

    /**
     * Description constructor.
     * @param \TurnTo\SocialCommerce\Helper\Config $config
     */
    public function __construct(\TurnTo\SocialCommerce\Helper\Config $config)
    {
        $this->config = $config;
    }

    public function aroundGetTemplate(\Magento\Review\Block\Product\Review $subject, callable $proceed)
    {
        if (!$this->config->getIsEnabled() || !$this->config->getReviewsEnabled()) {
            return $proceed();
        }

        return 'TurnTo_SocialCommerce::product/view/reviews-tab.phtml';
    }
}
