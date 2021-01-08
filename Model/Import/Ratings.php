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

namespace TurnTo\SocialCommerce\Model\Import;

use TurnTo\SocialCommerce\Helper\Product;
use TurnTo\SocialCommerce\Setup\InstallHelper;

class Ratings extends AbstractImport
{
    /**#@+
     *  TurnTo Aggregate Rating Feed constants
     */
    const TURNTO_EXPORT_BASE_URI = 'http://www.turnto.com/static/export/';

    const TURNTO_AVERAGE_RATING_BY_SKU_NAME = 'turnto-skuaveragerating.xml';

    const TURNTO_FEED_KEY_SKU = 'sku';

    const TURNTO_FEED_KEY_REVIEW_COUNT = 'review_count';
    /**
     * @var Product
     */
    protected $productHelper;
    /**#@-*/

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @param \TurnTo\SocialCommerce\Helper\Config                 $config
     * @param \TurnTo\SocialCommerce\Logger\Monolog                $logger
     * @param \Magento\Catalog\Model\ProductFactory                $productFactory
     * @param \Magento\Store\Model\StoreManagerInterface           $storeManager
     * @param \Magento\Catalog\Model\Indexer\Product\Eav\Processor $productEavIndexProcessor
     * @param Product                                              $productHelper
     */
    public function __construct(
        \TurnTo\SocialCommerce\Helper\Config $config,
        \TurnTo\SocialCommerce\Logger\Monolog $logger,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Indexer\Product\Eav\Processor $productEavIndexProcessor,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        Product $productHelper
    )
    {
        parent::__construct($config, $logger, $productFactory, $storeManager, $productEavIndexProcessor);

        $this->productCollectionFactory = $productCollectionFactory;
        $this->productHelper = $productHelper;
    }

    /**
     * Builds the store specific address to obtain aggregated product ratings by sku
     *
     * @param \Magento\Store\Api\Data\StoreInterface $store
     *
     * @return string
     */
    public function getAggregateRatingsFeedAddress(\Magento\Store\Api\Data\StoreInterface $store)
    {
        return self::TURNTO_EXPORT_BASE_URI
            . $this->config->getSiteKey($store->getCode())
            . '/' . $this->config->getAuthorizationKey($store->getCode())
            . '/' . self::TURNTO_AVERAGE_RATING_BY_SKU_NAME;
    }

    /**
     * Updates the magento product's turnto ratings related values
     *
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @param                                        $sku
     * @param                                        $reviewCount
     * @param                                        $averageRating
     *
     * @return bool
     */
    public function updateProduct(
        \Magento\Store\Api\Data\StoreInterface $store,
        $sku,
        $reviewCount,
        $averageRating
    )
    {

        $product = $this->productFactory->create()
            ->setStoreId($store->getId())
            ->loadByAttribute(
                \Magento\Catalog\Model\Product::SKU,
                $sku,
                [
                    InstallHelper::RATING_ATTRIBUTE_CODE,
                    InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE,
                    InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE
                ]
            );

        if (!$product) {
            return false;
        }

        // Only proceed if product needs to be updated
        if (
            $product->getData(InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE) == $reviewCount
            && $product->getData(InstallHelper::RATING_ATTRIBUTE_CODE) == $averageRating
        ) {
            return false;
        }

        $product->setData(InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE, $reviewCount);
        $product->getResource()->saveAttribute($product, InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE);
        $product->setData(InstallHelper::RATING_ATTRIBUTE_CODE, $averageRating);
        $product->getResource()->saveAttribute($product, InstallHelper::RATING_ATTRIBUTE_CODE);

        // Set "3 stars and above" tags
        $filterValues = [];
        if ($averageRating == 0) {
            $product->setData(InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE, "0");
        } else {
            foreach ($this->getRatingFilterAttributeValuesFromAverage($averageRating) as $optionText) {
                $filterValues[] = $product->getResource()->getAttribute(InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE)->getSource()->getOptionId($optionText);
            }
            $product->setData(InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE, implode(',', $filterValues));
        }
        $product->getResource()->saveAttribute($product, InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE);

        // Ensure product gets reindexed
        $product->afterSave();

        return true;
    }

    /**
     * Gets an array of values equal to or less than the floor rounded average rating value.
     *
     * @param $averageRating
     *
     * @return array
     */
    public function getRatingFilterAttributeValuesFromAverage($averageRating)
    {
        $floorValue = floor($averageRating);
        $filterValues = [];
        for ($i = 0; $i < $floorValue; $i++) {
            $filterValues[] = InstallHelper::RATING_FILTER_VALUES[$i];
        }

        return $filterValues;
    }

    /**
     * Downloads the Aggregated Ratings Feed from TurnTo and applies that data to the corresponding Products
     */
    public function cronDownloadFeed()
    {
        try {
            // Use this to record all products found in the feed. Later, we'll reset all products' Avg Rating/Review Count
            //    if it _isn't_ found in the feed
            $feedProducts = [];
            foreach ($this->storeManager->getStores() as $store) {
                $feedAddress = 'UNK';
                if (!$this->config->getIsEnabled($store->getCode()) || !$this->config->getReviewsEnabled($store->getCode())) {
                    continue;
                }
                // Create an array for reach store
                $feedProducts[$store->getId()] = [];

                try {
                    $feedAddress = $this->getAggregateRatingsFeedAddress($store);
                    $xmlFeed = simplexml_load_file($feedAddress);
                    // Take each product in the feed and update it's info
                    foreach ($xmlFeed->products->product as $turnToProduct) {
                        try {
                            if (!isset($turnToProduct[self::TURNTO_FEED_KEY_SKU])
                                || !isset($turnToProduct[self::TURNTO_FEED_KEY_REVIEW_COUNT])
                            ) {
                                continue;
                            }
                            $sku = null;
                            $averageRating = null;
                            $reviewCount = null;

                            $sku = $this->productHelper->turnToSafeDecoding(
                                (string)$turnToProduct[self::TURNTO_FEED_KEY_SKU]
                            );
                            if (empty($sku)) {
                                continue;
                            }

                            // Save a record of the product
                            $feedProducts[$store->getId()][$sku] = true;

                            $reviewCount = (int)$turnToProduct[self::TURNTO_FEED_KEY_REVIEW_COUNT];
                            if ($reviewCount > 0) {
                                $averageRating = (float)$turnToProduct;
                                if ($averageRating > 0.0) {
                                    $this->updateProduct($store, $sku, $reviewCount, $averageRating);
                                } else {
                                    throw new \UnexpectedValueException('Average rating is a non-positive '
                                        . 'number despite product having reviews');
                                }
                            }
                        } catch (\Exception $e) {
                            $this->logger->error(
                                'Failed to read TurnTo aggregate rating data for product',
                                [
                                    'exception' => $e,
                                    'storeCode' => $store->getCode(),
                                    'sku' => empty($sku) ? 'UNKNOWN' : $sku
                                ]
                            );
                        }
                    }
                } catch (\Exception $feedRetrievalException) {
                    $this->logger->error(
                        'Failed to retrieve TurnTo aggregate rating feed for store from TurnTo',
                        [
                            'exception' => $feedRetrievalException,
                            'storeCode' => $store->getCode(),
                            'feedAddress' => $feedAddress
                        ]
                    );
                }
            }

            // Now reset all products not in the feed
            $this->resetProducts($feedProducts);
        } catch (\Exception $exception) {
            $this->logger->error(
                'Failed to download ratings feed',
                [
                    'exception' => $exception
                ]
            );
        }
    }

    /**
     * After updating ratings/review counts, we want to reset any products that might have had reviews removed
     *
     * @param $feedProducts
     */
    private function resetProducts($feedProducts) {

        // Go through each store and get all products with an average rating/review count
        foreach ($this->storeManager->getStores() as $store) {

            $collection = $this->productCollectionFactory->create()
                ->addAttributeToSelect('id')
                ->addAttributeToSelect('name')
                ->addAttributeToSelect('sku')
                ->addAttributeToSelect('url_path')
                ->addAttributeToSelect('url_key')
                ->addAttributeToSelect('url_in_store')
                ->addAttributeToSelect('image')
                ->addAttributeToSelect('quantity_and_stock_status')
                ->addAttributeToSelect('price')
                ->addAttributeToSelect('status')
                ->addAttributeToFilter(
                    [
                        [
                            'attribute' => \TurnTo\SocialCommerce\Setup\InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE,
                            'notnull' => true,
                            'left'
                        ],
                        [
                            'attribute' => \TurnTo\SocialCommerce\Setup\InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE,
                            'notnull' => true,
                            'left'
                        ],
                    ],
                    "",
                    "left"
                );
            $collection->addStoreFilter($store)->setFlag('has_stock_status_filter', false)->load();

            // Loop over products and reset data if not found in $feedProducts
            foreach ($collection as $item) {
                if (!isset($feedProducts[$store->getId()][$item->getSku()])) {
                    $this->updateProduct($store, $item->getSku(), 0, 0);
                }
            }
        }
    }
}
