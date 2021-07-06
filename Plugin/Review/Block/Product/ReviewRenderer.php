<?php
/**
 * TurnTo_SocialCommerce
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright  Copyright (c) 2018 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
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
     * Array of available template name
     *
     * This array is a copy of the @see \Magento\Review\Block\Product\ReviewRenderer::$_availableTemplates
     * array. Copied here so that the aroundGetReviewsSummaryHtml method below can access it
     *
     * @var array
     */
    protected $_availableTemplates = [
        \Magento\Catalog\Block\Product\ReviewRendererInterface::FULL_VIEW => 'Magento_Review::helper/summary.phtml',
        \Magento\Catalog\Block\Product\ReviewRendererInterface::SHORT_VIEW => 'Magento_Review::helper/summary_short.phtml',
    ];

    /**
     * Plugin constructor.
     * @param \TurnTo\SocialCommerce\Helper\Config $turnToConfigHelper
     */
    public function __construct(\TurnTo\SocialCommerce\Helper\Config $turnToConfigHelper)
    {
        $this->turnToConfigHelper = $turnToConfigHelper;
    }

    /**
     * @param \Magento\Catalog\Block\Product\ReviewRendererInterface $subject
     * @param $proceed
     * @return string
     */
    public function aroundGetRatingSummary(\Magento\Catalog\Block\Product\ReviewRendererInterface $subject, $proceed)
    {

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/turnto-temp.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);

        $logger->info("Plugin Path 1 - TurnTo Enabled: ". $this->turnToConfigHelper->getIsEnabled());
        $logger->info("Plugin Path 1 - TurnTo Reviews Enabled: ". $this->turnToConfigHelper->getReviewsEnabled());

        if ($this->turnToConfigHelper->getIsEnabled() && $this->turnToConfigHelper->getReviewsEnabled()) {
            $logger->info("Plugin Path 1 - Logic Path");

            $result = (string)round(
                $subject->getProduct()->getTurntoRating() * self::RATING_TO_PERCENTILE_MULTIPLIER
            );
            $logger->info("Plugin Path 1 - Rating: ".$result);
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
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/turnto-temp.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);

        $logger->info("Plugin Path 2 - TurnTo Enabled: ". $this->turnToConfigHelper->getIsEnabled());
        $logger->info("Plugin Path 2 - TurnTo Reviews Enabled: ". $this->turnToConfigHelper->getReviewsEnabled());

        if ($this->turnToConfigHelper->getIsEnabled() && $this->turnToConfigHelper->getReviewsEnabled()) {
            $logger->info("Plugin Path 2 - Logic Path");

            $result = (string)round(
                $subject->getProduct()->getTurntoRating() * self::RATING_TO_PERCENTILE_MULTIPLIER
            );
            $logger->info("Plugin Path 2 - Rating: ".$result);
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
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/turnto-temp.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);

        $logger->info("Plugin Path 3 - TurnTo Enabled: ". $this->turnToConfigHelper->getIsEnabled());
        $logger->info("Plugin Path 3 - TurnTo Reviews Enabled: ". $this->turnToConfigHelper->getReviewsEnabled());

        if ($this->turnToConfigHelper->getIsEnabled() && $this->turnToConfigHelper->getReviewsEnabled()) {
            $logger->info("Plugin Path 3 - Logic Path");

            $result = $subject->getProduct()->getTurntoReviewCount();
            $logger->info("Plugin Path 3 - Review Count: ".$result);
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

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/turnto-temp.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);

        $logger->info("Plugin Path 4 - TurnTo Enabled: ". $this->turnToConfigHelper->getIsEnabled());
        $logger->info("Plugin Path 4 - TurnTo Reviews Enabled: ". $this->turnToConfigHelper->getReviewsEnabled());
        $logger->info("Plugin Path 4 - Template: ". $templateType . " = ".$this->_availableTemplates[$templateType]);

        if ($this->turnToConfigHelper->getIsEnabled() && $this->turnToConfigHelper->getReviewsEnabled()) {

            $logger->info("Plugin Path 4 - Logic Path");

            try {
                $subject->setTemplate($this->_availableTemplates[$templateType]);
                $subject->setDisplayIfEmpty($displayIfNoReviews);
                $subject->setProduct($product);
                $result = $subject->toHtml();
            } catch (\Exception $e) {
                $logger->info("Plugin Path 4 - Exception");
                $result = $proceed($product, $templateType, $displayIfNoReviews, false);
            }
        } else {
            $result = $proceed($product, $templateType, $displayIfNoReviews, false);
        }

        return $result;
    }
}
