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

    /**
     * @param \Magento\Review\Block\Product\Review $subject
     * @param callable                             $proceed
     *
     * @return string
     */
    public function aroundGetTemplate(\Magento\Review\Block\Product\Review $subject, callable $proceed)
    {
        return $proceed();
    }

    /**
     * Used to insert the TurnTo review count
     * rather then the native magento review count
     *
     * @param \Magento\Review\Block\Product\Review $subject
     * @param                                      $result
     */
    public function afterSetTabTitle(\Magento\Review\Block\Product\Review $subject, $result)
    {
        if ($this->config->getIsEnabled()) {
            $subject->setTitle(__('Reviews '));
        }
    }
}
