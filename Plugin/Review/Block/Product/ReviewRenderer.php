<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 6/15/16
 * Time: 10:09 AM
 */

namespace TurnTo\SocialCommerce\Plugin\Review\Block\Product;

use Magento\Catalog\Block\Product\ReviewRendererInterface;
use TurnTo\SocialCommerce\Setup\InstallData;

class ReviewRenderer
{
    /**
     * TurntoAverageRating is from 0.0 to 5.0, some uses need a number between 0 and 100 so multiply by 20
     */
    const RATING_TO_PERCENTILE_MULTIPLIER = 20;

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
     * @param \Magento\Catalog\Block\Product\ReviewRendererInterface $subject
     * @param $proceed
     * @return string
     */
    public function aroundGetRatingSummary(\Magento\Catalog\Block\Product\ReviewRendererInterface $subject, $proceed)
    {
        if ($this->turnToConfigHelper->getIsEnabled() && $this->turnToConfigHelper->getReviewsEnabled()) {
            $result = (string)round(
                $subject->getProduct()->getTurntoRating() * self::RATING_TO_PERCENTILE_MULTIPLIER
            );
        } else {
            $result = $proceed();
        }

        return $result;
    }

    /**
     * @param \Magento\Catalog\Block\Product\ReviewRendererInterface $subject
     * @param $proceed
     * @return string
     */
    public function aroundGetReviewSummary(\Magento\Catalog\Block\Product\ReviewRendererInterface $subject, $proceed)
    {
        if ($this->turnToConfigHelper->getIsEnabled() && $this->turnToConfigHelper->getReviewsEnabled()) {
            $result = (string)round(
                $subject->getProduct()->getTurntoRating() * self::RATING_TO_PERCENTILE_MULTIPLIER
            );
        } else {
            $result = $proceed();
        }

        return $result;
    }

    /**
     * @param \Magento\Catalog\Block\Product\ReviewRendererInterface $subject
     * @param $proceed
     * @return int
     */
    public function aroundGetReviewsCount(\Magento\Catalog\Block\Product\ReviewRendererInterface $subject, $proceed)
    {
        if ($this->turnToConfigHelper->getIsEnabled() && $this->turnToConfigHelper->getReviewsEnabled()) {
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
        /*
         * if turnto module and reviews are enabled trigger generation of the block contents but avoid using the
         * standard checks for magento based product reviews otherwise resolve as usual
         */

        if ($this->turnToConfigHelper->getIsEnabled() && $this->turnToConfigHelper->getReviewsEnabled()) {
            try {
                $subject->setTemplate($this->_availableTemplates[$templateType]);
                $subject->setDisplayIfEmpty($displayIfNoReviews);
                $subject->setProduct($product);
                $result = $subject->toHtml();
            } catch (\Exception $e) {
                $result = $proceed($product, $templateType, $displayIfNoReviews, false);
            }
        } else {
            $result = $proceed($product, $templateType, $displayIfNoReviews, false);
        }

        return $result;
    }
}
