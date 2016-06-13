<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 6/10/16
 * Time: 11:13 AM
 */

namespace TurnTo\SocialCommerce\Block\Product;

class ReviewRenderer extends \Magento\Review\Block\Product\ReviewRenderer
{
    /**
     * @param bool $turnToIsEnabled
     * @return int
     */
    public function getReviewsCount($turnToIsEnabled = false)
    {
        if ($turnToIsEnabled) {
            return $this->getProduct()->getTurntoReviewCount();
        } else {
            return parent::getReviewsCount();
        }
    }

    /**
     * @param bool $turnToIsEnabled
     * @return string
     */
    public function getRatingSummary($turnToIsEnabled = false)
    {
        if ($turnToIsEnabled) {
            return (string)round($this->getProduct()->getTurntoAverageRating() * 20);
        } else {
            return parent::getRatingSummary();
        }
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param string $templateType
     * @param bool $displayIfNoReviews
     * @param bool $turnToIsEnabled
     * @return string
     */
    public function getReviewsSummaryHtml(
        \Magento\Catalog\Model\Product $product,
        $templateType = self::DEFAULT_VIEW,
        $displayIfNoReviews = false,
        $turnToIsEnabled = false
    ) {
        if (!$turnToIsEnabled) {
            return parent::getReviewsSummaryHtml($product, $templateType, $displayIfNoReviews);
        } else {
            // pick template among available
            if (empty($this->_availableTemplates[$templateType])) {
                $templateType = self::DEFAULT_VIEW;
            }
            $this->setTemplate($this->_availableTemplates[$templateType]);

            $this->setDisplayIfEmpty($displayIfNoReviews);

            $this->setProduct($product);

            if (!$this->getRatingSummary($turnToIsEnabled) && !$displayIfNoReviews) {
                return '';
            } else {
                return $this->toHtml();
            }
        }
    }
}
