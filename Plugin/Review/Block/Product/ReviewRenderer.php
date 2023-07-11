<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Plugin\Review\Block\Product;

use Closure;
use Exception;
use Magento\Catalog\Block\Product\ReviewRendererInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use TurnTo\SocialCommerce\Helper\Config;

class ReviewRenderer
{
    /**
     * TurntoAverageRating is from 0.0 to 5.0, some uses need a number between 0 and 100 so multiply by 20
     */
    const RATING_TO_PERCENTILE_MULTIPLIER = 20;

    /**
     * @var Config
     */
    protected $turnToConfigHelper;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Array of available template name
     *
     * This array is a copy of the @see \Magento\Review\Block\Product\ReviewRenderer::$_availableTemplates
     * array. Copied here so that the aroundGetReviewsSummaryHtml method below can access it
     *
     * @var array
     */
    protected $_availableTemplates = [
        ReviewRendererInterface::FULL_VIEW => 'Magento_Review::helper/summary.phtml',
        ReviewRendererInterface::SHORT_VIEW => 'Magento_Review::helper/summary_short.phtml',
    ];

    /**
     * Plugin constructor.
     * @param Config $turnToConfigHelper
     * @param StoreManagerInterface $storeManager
     * @param ProductRepository $productRepository
     */
    public function __construct(
        Config $turnToConfigHelper,
        StoreManagerInterface $storeManager,
        ProductRepository $productRepository
    ) {
        $this->turnToConfigHelper = $turnToConfigHelper;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
    }

    /**
     * @param ReviewRendererInterface $subject
     * @param $proceed
     * @return string
     * @throws NoSuchEntityException
     */
    public function aroundGetRatingSummary(ReviewRendererInterface $subject, $proceed)
    {
        if ($this->turnToConfigHelper->getIsEnabled() && $this->turnToConfigHelper->getReviewsEnabled()) {
            $storeId = $this->storeManager->getStore()->getId();
            $product = $this->productRepository->getById($subject->getProduct()->getId(), false, $storeId);
            $rating = $product->getTurntoRating();
            $result = (string)round(
                $rating * self::RATING_TO_PERCENTILE_MULTIPLIER
            );
        } else {
            $result = $proceed();
        }

        return $result;
    }

    /**
     * @param ReviewRendererInterface $subject
     * @param $proceed
     * @return string
     * @throws NoSuchEntityException
     */
    public function aroundGetReviewSummary(ReviewRendererInterface $subject, $proceed)
    {
        if ($this->turnToConfigHelper->getIsEnabled() && $this->turnToConfigHelper->getReviewsEnabled()) {
            $storeId = $this->storeManager->getStore()->getId();
            $product = $this->productRepository->getById($subject->getProduct()->getId(), false, $storeId);
            $rating = $product->getTurntoRating();
            $result = (string)round(
                $rating * self::RATING_TO_PERCENTILE_MULTIPLIER
            );
        } else {
            $result = $proceed();
        }

        return $result;
    }

    /**
     * @param ReviewRendererInterface $subject
     * @param $proceed
     * @return int
     * @throws NoSuchEntityException
     */
    public function aroundGetReviewsCount(ReviewRendererInterface $subject, $proceed)
    {
        if ($this->turnToConfigHelper->getIsEnabled() && $this->turnToConfigHelper->getReviewsEnabled()) {
            $storeId = $this->storeManager->getStore()->getId();
            $product = $this->productRepository->getById($subject->getProduct()->getId(), false, $storeId);
            $result = $product->getData("turnto_review_count");
        } else {
            $result = $proceed();
        }

        return $result;
    }

    /**
     * @param ReviewRendererInterface $subject
     * @param Closure $proceed
     * @param Product $product
     * @param bool $templateType
     * @param bool $displayIfNoReviews
     * @return string
     */
    public function aroundGetReviewsSummaryHtml(
        ReviewRendererInterface $subject,
        Closure $proceed,
        Product $product,
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
            } catch (Exception $e) {
                $result = $proceed($product, $templateType, $displayIfNoReviews, false);
            }
        } else {
            $result = $proceed($product, $templateType, $displayIfNoReviews, false);
        }

        return $result;
    }
}
