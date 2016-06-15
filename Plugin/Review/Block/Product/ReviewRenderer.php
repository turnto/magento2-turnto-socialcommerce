<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 6/15/16
 * Time: 10:09 AM
 */

namespace TurnTo\SocialCommerce\Plugin\Review\Block\Product;

use Magento\Catalog\Block\Product\ReviewRendererInterface;

class ReviewRenderer
{
    /**
     * Array of available template name
     *
     * @var array
     */
    protected $_availableTemplates = [
        ReviewRendererInterface::FULL_VIEW => 'helper/summary.phtml',
        ReviewRendererInterface::SHORT_VIEW => 'helper/summary_short.phtml',
    ];

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

        if ($this->turnToConfigHelper->getIsEnabled() && $this->turnToConfigHelper->getReviewsEnabled()) {
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

        if ($this->turnToConfigHelper->getIsEnabled() && $this->turnToConfigHelper->getReviewsEnabled()) {
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
        $result = '';

        if ($this->turnToConfigHelper->getIsEnabled() && $this->turnToConfigHelper->getReviewsEnabled()) {
            $subject->setTemplate($this->_availableTemplates[$templateType]);
            $subject->setDisplayIfEmpty($displayIfNoReviews);
            $subject->setProduct($product);
            $result = $subject->toHtml();
        } else {
            $result = $proceed($product, $templateType, $displayIfNoReviews, false);
        }

        return $result;
    }
}
