<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Plugin\Block\Product;

use Magento\Review\Block\Product\Review;
use TurnTo\SocialCommerce\Model\Config;

class ReviewPlugin
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * Description constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param Review $subject
     * @param callable $proceed
     * @return string
     */
    public function aroundGetTemplate(Review $subject, callable $proceed)
    {
        if (!$this->config->getIsEnabled() || !$this->config->getConfigBool(Config::REVIEWS_ENABLE)) {
            return $proceed();
        }

        return 'TurnTo_SocialCommerce::product/view/reviews-tab.phtml';
    }

    /**
     * Used to insert the TurnTo review count
     * rather than the native Magento review count
     *
     * @param Review $subject
     * @param $result
     */
    public function afterSetTabTitle(Review $subject, $result)
    {
        if ($this->config->getIsEnabled()) {
            $subject->setTitle(__('Reviews '));
        }
    }
}
