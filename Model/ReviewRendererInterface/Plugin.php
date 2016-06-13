<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 6/13/16
 * Time: 10:00 AM
 */

namespace TurnTo\SocialCommerce\Model\ReviewRendererInterface;

class Plugin
{
    /**
     * @var null|\TurnTo\SocialCommerce\Helper\Config
     */
    protected $turnToConfigHelper = null;

    /**
     * Plugin constructor.
     * @param \TurnTo\SocialCommerce\Helper\Config $turnToConfigHelper
     */
    public function __construct(\TurnTo\SocialCommerce\Helper\Config $turnToConfigHelper) {
        $this->turnToConfigHelper = $turnToConfigHelper;
    }

    /**
     * @param $subject
     * @param $proceed
     * @return string
     */
    public function aroundGetRatingSummary($subject, $proceed)
    {
        $result = '';
        if ($this->turnToConfigHelper->getIsEnabled($this->turnToConfigHelper->getCurrentStoreCode())) {
            $result = (string)round($subject->getProduct()->getTurntoAverageRating() * 20);
        } else {
            $result = $proceed();
        }
        return $result;
    }

    /**
     * @param $subject
     * @param $proceed
     * @return string
     */
    public function aroundGetReviewSummary($subject, $proceed)
    {
        $result = '';
        if ($this->turnToConfigHelper->getIsEnabled($this->turnToConfigHelper->getCurrentStoreCode())) {
            $result = (string)round($subject->getProduct()->getTurntoAverageRating() * 20);
        } else {
            $result = $proceed();
        }
        return $result;
    }

    /**
     * @param $subject
     * @param $proceed
     * @return int
     */
    public function aroundGetReviewsCount($subject, $proceed)
    {
        $result = 0;
        if ($this->turnToConfigHelper->getIsEnabled($this->turnToConfigHelper->getCurrentStoreCode())) {
            $result = $subject->getProduct()->getTurntoReviewCount();
        } else {
            $result = $proceed();
        }
        return $result;
    }

    /**
     * @param \Magento\Catalog\Block\Product\ReviewRendererInterface $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Model\Product $product
     * @param bool $templateType
     * @param bool $displayIfNoReviews
     * @return string
     */
    public function aroundGetReviewsSummaryHtml(
        \Magento\Catalog\Block\Product\ReviewRendererInterface $subject,
        \Closure $proceed,
        \Magento\Catalog\Model\Product $product,
        $templateType = false,
        $displayIfNoReviews = false
    ) {
        $result = '';

        if ($this->turnToConfigHelper->getIsEnabled($this->turnToConfigHelper->getCurrentStoreCode())) {
            $result = $proceed($product, $templateType, $displayIfNoReviews, true);
        } else {
            $result = $proceed($product, $templateType, $displayIfNoReviews, false);
        }

        return $result;
    }
}
