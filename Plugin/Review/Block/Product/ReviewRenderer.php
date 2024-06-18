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
use TurnTo\SocialCommerce\Setup\InstallHelper;

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
     * @param string | null $result
     * @return string
     * @throws NoSuchEntityException
     */
    public function afterGetRatingSummary(ReviewRendererInterface $subject, ?string $result)
    {
        if ($this->isDisabled()) {
            return $result;
        }

        $rating =  $subject->getProduct()->getData(InstallHelper::RATING_ATTRIBUTE_CODE);

        return (string)round(
            $rating * self::RATING_TO_PERCENTILE_MULTIPLIER
        );
    }

    /**
     * @param ReviewRendererInterface $subject
     * @param int | null $result
     * @return int
     * @throws NoSuchEntityException
     */
    public function afterGetReviewsCount(ReviewRendererInterface $subject, ?int $result)
    {
        if ($this->isDisabled()) {
            return $result;
        }

        return $subject->getProduct()->getData(InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE);
    }

    /**
     * trigger generation of the block contents but avoid using the
     * standard checks for magento based product reviews
     *
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
        if ($this->isDisabled()) {
            return $proceed($product, $templateType, $displayIfNoReviews, false);
        }
        try {
            $subject->setTemplate($this->_availableTemplates[$templateType]);
            $subject->setDisplayIfEmpty($displayIfNoReviews);
            $subject->setProduct($product);

            return $subject->toHtml();
        } catch (Exception $e) {
            return $proceed($product, $templateType, $displayIfNoReviews, false);
        }
    }

    /**
     * @return bool
     */
    public function isDisabled()
    {
        return !($this->turnToConfigHelper->getIsEnabled() && $this->turnToConfigHelper->getReviewsEnabled());
    }
}
