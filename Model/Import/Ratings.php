<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 6/7/16
 * Time: 9:40 AM
 */

namespace TurnTo\SocialCommerce\Model\Import;

use TurnTo\SocialCommerce\Setup\InstallData;

class Ratings extends AbstractImport
{
    /**#@+
     *  TurnTo Aggregate Rating Feed constants
     */
    const TURNTO_EXPORT_BASE_URI = 'http://www.turnto.com/static/export/';

    const TURNTO_AVERAGE_RATING_BY_SKU_NAME = 'turnto-skuaveragerating.xml';

    const TURNTO_FEED_KEY_SKU = 'sku';
    
    const TURNTO_FEED_KEY_REVIEW_COUNT = 'review_count';
    /**#@-*/
    
    /**
     * Builds the store specific address to obtain aggregated product ratings by sku
     *
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @return string
     */
    public function getAggregateRatingsFeedAddress(\Magento\Store\Api\Data\StoreInterface $store)
    {
        return self::TURNTO_EXPORT_BASE_URI 
            . $this->config->getSiteKey($store->getCode())
            . '/' . $this->encryptor->decrypt($this->config->getAuthorizationKey($store->getCode()))
            . '/' . self::TURNTO_AVERAGE_RATING_BY_SKU_NAME;
    }

    /**
     * Updates the magento product's turnto ratings related values
     *
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @param $sku
     * @param $reviewCount
     * @param $averageRating
     */
    public function updateProduct(
        \Magento\Store\Api\Data\StoreInterface $store,
        $sku,
        $reviewCount,
        $averageRating
    ) {
        $product = $this->productFactory->create()
            ->setStoreId($store->getId())
            ->loadByAttribute(
                \Magento\Catalog\Model\Product::SKU, 
                $sku,
                [
                    InstallData::REVIEW_COUNT_ATTRIBUTE_CODE,
                    InstallData::AVERAGE_RATING_ATTRIBUTE_CODE
                ]
            );

        if ($product) {
            $product->setData(InstallData::REVIEW_COUNT_ATTRIBUTE_CODE, $reviewCount);
            $product->getResource()->saveAttribute($product, InstallData::REVIEW_COUNT_ATTRIBUTE_CODE);
            $product->setData(InstallData::AVERAGE_RATING_ATTRIBUTE_CODE, $averageRating);
            $product->getResource()->saveAttribute($product, InstallData::AVERAGE_RATING_ATTRIBUTE_CODE);
        }
    }

    /**
     * Downloads the Aggregated Ratings Feed from TurnTo and applies that data to the related Products
     */
    public function cronDownloadFeed()
    {
        foreach ($this->config->getStores() as $store) {
            if ($this->config->getIsEnabled($store->getCode()) && $this->config->getReviewsEnabled($store->getCode())) {
                try {
                    $feedAddress = $this->getAggregateRatingsFeedAddress($store);
                    $xmlFeed = simplexml_load_file($feedAddress);
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

                            $sku = (string)$turnToProduct[self::TURNTO_FEED_KEY_SKU];
                            if (empty($sku)) {
                                continue;
                            }

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
                        'Failed to retrieve TurnTo aggregate rating feed for store',
                        [
                            'exception' => $feedRetrievalException,
                            'storeCode' => $store->getCode(),
                            'feedAddress' => $feedAddress
                        ]
                    );
                }
            }
        }
    }
}
