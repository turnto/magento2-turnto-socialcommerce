<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Plugin\Review\Block\Product;

use Closure;
use Exception;
use Magento\Catalog\Block\Product\ReviewRendererInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Store\Model\StoreManagerInterface;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Config;
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
    protected $config;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var Monolog
     */
    protected $logger;

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
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param ProductRepository $productRepository
     * @param Monolog $logger
     */
    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager,
        ProductRepository $productRepository,
        Monolog $logger
    ) {
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

    /**
     * @param ReviewRendererInterface $subject
     * @param string|null $result
     * @return string|null
     */
    public function afterGetRatingSummary(ReviewRendererInterface $subject, ?string $result)
    {
        try {
            if ($this->isEnabled()) {
                $rating =  $subject->getProduct()->getData(InstallHelper::RATING_ATTRIBUTE_CODE);

                return (string)round(
                    $rating * self::RATING_TO_PERCENTILE_MULTIPLIER
                );
            }
        } catch (Exception $e) {
            $this->logger->error($e, ['exception' => $e]);
        }

        return $result;
    }

    /**
     * @param ReviewRendererInterface $subject
     * @param int|null $result
     * @return int|null
     */
    public function afterGetReviewsCount(ReviewRendererInterface $subject, ?int $result)
    {
        try {
            if ($this->isEnabled()) {
                return $subject->getProduct()->getData(InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE);
            }
        } catch (Exception $e) {
            $this->logger->error($e, ['exception' => $e]);
        }

        return $result;
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
        try {
            if ($this->isEnabled()) {
                $subject->setTemplate($this->_availableTemplates[$templateType]);
                $subject->setDisplayIfEmpty($displayIfNoReviews);
                $subject->setProduct($product);

                return $subject->toHtml();
            }
        } catch (Exception $e) {
            $this->logger->error($e, ['exception' => $e]);
        }

        return $proceed($product, $templateType, $displayIfNoReviews, false);
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return ($this->config->getIsEnabled() && $this->config->getConfigBool(Config::REVIEWS_ENABLE));
    }
}
