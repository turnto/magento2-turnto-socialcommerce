<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Import;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Product;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Setup\InstallHelper;
use UnexpectedValueException;

class Ratings
{
    /**#@+
     *  TurnTo Aggregate Rating Feed constants
     */
    const TURNTO_EXPORT_BASE_URI = 'https://export.turnto.com/';

    const TURNTO_AVERAGE_RATING_BY_SKU_NAME = 'turnto-skuaveragerating.xml';

    const TURNTO_FEED_KEY_SKU = 'sku';

    const TURNTO_FEED_KEY_REVIEW_COUNT = 'review_count';

    const TURNTO_FEED_KEY_RELATED_REVIEW_COUNT = 'related_review_count';

    public const WEBSITE_IDS = 'website_ids';

    /**
     * @var Product
     */
    protected $product;
    /**#@-*/

    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var Monolog
     */
    protected $logger;
    /**
     * @var ProductFactory
     */
    protected $productFactory;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var ProductResource
     */
    protected $productResource;

    /**
     * @param Config $config
     * @param Monolog $logger
     * @param ProductFactory $productFactory
     * @param ProductResource $productResource
     * @param StoreManagerInterface $storeManager
     * @param CollectionFactory $productCollectionFactory
     * @param Product $product
     */
    public function __construct(
        Config                $config,
        Monolog               $logger,
        ProductFactory        $productFactory,
        ProductResource       $productResource,
        StoreManagerInterface $storeManager,
        CollectionFactory     $productCollectionFactory,
        Product               $product
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->productFactory = $productFactory;
        $this->productResource = $productResource;
        $this->storeManager = $storeManager;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->product = $product;
    }

    /**
     * Builds the store specific address to obtain aggregated product ratings by sku
     *
     * @param StoreInterface $store
     * @return string
     */
    public function getAggregateRatingsFeedAddress(StoreInterface $store)
    {
        return self::TURNTO_EXPORT_BASE_URI
            . $this->config->getSiteKey($store->getCode())
            . '/' . $this->config->getAuthorizationKey($store->getCode())
            . '/' . self::TURNTO_AVERAGE_RATING_BY_SKU_NAME;
    }

    /**
     * Updates the magento product's turnto ratings related values
     *
     * @param StoreInterface $store
     * @param $sku
     * @param $reviewCount
     * @param $averageRating
     * @param ProductInterface|null $product
     * @return bool
     * @throws Exception
     */
    public function updateProduct(
        StoreInterface $store,
        $sku,
        $reviewCount,
        $averageRating,
        $product = null
    ) {
        if (!$product) {
            $product = $this->productFactory->create()
                ->setStoreId($store->getId())
                ->loadByAttribute(
                    ProductInterface::SKU,
                    $sku,
                    [
                        InstallHelper::RATING_ATTRIBUTE_CODE,
                        InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE,
                        InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE
                    ]
                );
        }

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
        $this->productResource->saveAttribute($product, InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE);
        $product->setData(InstallHelper::RATING_ATTRIBUTE_CODE, $averageRating);
        $this->productResource->saveAttribute($product, InstallHelper::RATING_ATTRIBUTE_CODE);

        // Set "3 stars and above" tags
        $filterValues = [];
        if ($averageRating == 0) {
            $product->setData(InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE, "0");
        } else {
            foreach ($this->getRatingFilterAttributeValuesFromAverage($averageRating) as $optionText) {
                $filterValues[] = $this->productResource->getAttribute(InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE)->getSource()->getOptionId($optionText);
            }
            $product->setData(InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE, implode(',', $filterValues));
        }
        $this->productResource->saveAttribute($product, InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE);

        //Set website_ids in OrigData to fix issue with ProductProcessUrlRewriteSavingObserver
        if (!$product->getOrigData(self::WEBSITE_IDS)) {
            $websiteIds = $product->getWebsiteIds();
            $product->setOrigData(self::WEBSITE_IDS, $websiteIds);
        }

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
                if (!$this->config->getIsEnabled($store->getCode()) || !$this->config->getConfigBool(Config::AVERAGE_RATING_IMPORT_ENABLED, $store->getCode())) {
                    continue;
                }
                // Create an array for each store
                $feedProducts[$store->getId()] = [];

                try {
                    $feedAddress = $this->getAggregateRatingsFeedAddress($store);
                    $previousLibxmlUseInternalErrors = libxml_use_internal_errors(true);
                    try {
                        $xmlFeed = simplexml_load_file($feedAddress);
                    } finally {
                        libxml_clear_errors();
                        libxml_use_internal_errors($previousLibxmlUseInternalErrors);
                    }
                    if (!$xmlFeed) {
                        throw new UnexpectedValueException('Unable to parse TurnTo aggregate rating feed');
                    }
                    if (!isset($xmlFeed->products) || !isset($xmlFeed->products->product)) {
                        throw new UnexpectedValueException('Aggregate rating feed is missing product data');
                    }

                    $turnToProductsBySku = [];
                    // Take each product in the feed and update its info
                    foreach ($xmlFeed->products->product as $turnToProduct) {
                        try {
                            if (!isset($turnToProduct[self::TURNTO_FEED_KEY_SKU])
                                || !isset($turnToProduct[self::TURNTO_FEED_KEY_REVIEW_COUNT])
                            ) {
                                continue;
                            }

                            $sku = $this->product->turnToSafeDecoding(
                                (string)$turnToProduct[self::TURNTO_FEED_KEY_SKU]
                            );
                            if (empty($sku)) {
                                continue;
                            }

                            // Save a record of the product
                            $feedProducts[$store->getId()][$sku] = true;
                            $reviewCount = (int)$turnToProduct[self::TURNTO_FEED_KEY_REVIEW_COUNT];
                            if ($this->config->getConfigBool(Config::AVERAGE_RATING_IMPORT_AGGREGATE_DATA, $store->getCode())
                                && isset($turnToProduct[self::TURNTO_FEED_KEY_RELATED_REVIEW_COUNT])
                            ) {
                                $reviewCount += (int)$turnToProduct[self::TURNTO_FEED_KEY_RELATED_REVIEW_COUNT];
                            }

                            $turnToProductsBySku[$sku] = [
                                'reviewCount' => $reviewCount,
                                'averageRating' => (float)$turnToProduct
                            ];
                        } catch (Exception $e) {
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

                    $productsToUpdate = $this->getProductsBySkus(
                        $store,
                        array_keys($turnToProductsBySku)
                    );

                    foreach ($turnToProductsBySku as $productSku => $productData) {
                        try {
                            $reviewCount = (int)$productData['reviewCount'];
                            $averageRating = (float)$productData['averageRating'];
                            if ($reviewCount <= 0) {
                                continue;
                            }
                            if ($averageRating <= 0.0) {
                                throw new UnexpectedValueException('Average rating is a non-positive '
                                    . 'number despite product having reviews');
                            }

                            $this->updateProduct(
                                $store,
                                $productSku,
                                $reviewCount,
                                $averageRating,
                                $productsToUpdate[$productSku] ?? null
                            );
                        } catch (Exception $productFeedItemException) {
                            $this->logger->error(
                                'Failed to apply aggregate rating data for product',
                                [
                                    'exception' => $productFeedItemException,
                                    'storeCode' => $store->getCode(),
                                    'sku' => $productSku
                                ]
                            );
                        }
                    }

                    // Now reset all products not in the feed
                    $this->resetProducts($feedProducts, $store);
                } catch (Exception $feedRetrievalException) {
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
        } catch (Exception $exception) {
            $this->logger->error(
                'Failed to download ratings feed',
                [
                    'exception' => $exception
                ]
            );
        }
    }

    /**
     * @param StoreInterface $store
     * @param array $skus
     *
     * @return array
     */
    protected function getProductsBySkus(StoreInterface $store, array $skus): array
    {
        if (empty($skus)) {
            return [];
        }

        $collection = $this->productCollectionFactory->create()
            ->setStoreId($store->getId())
            ->addAttributeToSelect('sku')
            ->addAttributeToSelect(InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE)
            ->addAttributeToSelect(InstallHelper::RATING_ATTRIBUTE_CODE)
            ->addAttributeToSelect(InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE)
            ->addAttributeToFilter(ProductInterface::SKU, ['in' => array_values(array_unique($skus))]);

        $products = [];
        foreach ($collection as $product) {
            $products[$product->getSku()] = $product;
        }

        return $products;
    }

    /**
     * After updating ratings/review counts, we want to reset any products that might have had reviews removed
     *
     * @param $feedProducts
     * @param $store
     * @throws Exception
     */
    protected function resetProducts($feedProducts, $store)
    {
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
            ->addAttributeToSelect(InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE)
            ->addAttributeToSelect(InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE)
            ->addAttributeToFilter(
                [
                    [
                        'attribute' => InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE,
                        'notnull' => true,
                        'left'
                    ],
                    [
                        'attribute' => InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE,
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
                $this->updateProduct($store, $item->getSku(), 0, 0, $item);
            }
        }
    }
}
