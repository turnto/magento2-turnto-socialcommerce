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
 * @copyright  Copyright (c) 2016 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Helper;

use Magento\Store\Model\ScopeInterface;

/**
 * Class Config - Assists with retrieval of TurnTo configuration settings
 *
 * @package TurnTo\SocialCommerce\Helper
 */
class Config extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * General Settings
     */
    const XML_PATH_SOCIALCOMMERCE_ENABLED = 'turnto_socialcommerce_configuration/general/enabled';

    const XML_PATH_SOCIALCOMMERCE_SITE_KEY = 'turnto_socialcommerce_configuration/general/site_key';

    const XML_PATH_SOCIALCOMMERCE_AUTHORIZATION_KEY = 'turnto_socialcommerce_configuration/general/authorization_key';

    const XML_PATH_SOCIALCOMMERCE_VERSION = 'turnto_socialcommerce_configuration/general/version';

    const XML_PATH_SOCIALCOMMERCE_STATIC_URL = 'turnto_socialcommerce_configuration/general/static_url';

    const XML_PATH_SOCIALCOMMERCE_URL = 'turnto_socialcommerce_configuration/general/url';

    const XML_PATH_SOCIALCOMMERCE_IMAGE_STORE_BASE = 'turnto_socialcommerce_configuration/general/image_store_base';

    const XML_PATH_SOCIALCOMMERCE_STATIC_CONTENT_CACHE_TIME = 'turnto_socialcommerce_configuration/general/static_content_cache_time';

    /**
     * Product Groups
     */
    const XML_PATH_SOCIALCOMMERCE_PRODUCT_GROUP = 'turnto_socialcommerce_configuration/product_attribute_mappings/';

    const UPC_ATTRIBUTE = 'upc_attribute';

    const MPN_ATTRIBUTE = 'mpn_attribute';

    const ISBN_ATTRIBUTE = 'isbn_attribute';

    const EAN_ATTRIBUTE = 'ean_attribute';

    const JAN_ATTRIBUTE = 'jan_attribute';

    const ASIN_ATTRIBUTE = 'asin_attribute';

    const BRAND_ATTRIBUTE = 'brand_attribute';

    const PRODUCT_ATTRIBUTE_MAPPING_KEYS = [
        self::UPC_ATTRIBUTE,
        self::MPN_ATTRIBUTE,
        self::ISBN_ATTRIBUTE,
        self::EAN_ATTRIBUTE,
        self::JAN_ATTRIBUTE,
        self::ASIN_ATTRIBUTE,
        self::BRAND_ATTRIBUTE
    ];

    /**
     * Checkout Comments
     */
    const XML_PATH_ENABLE_CHECKOUT_COMMENTS_SUCCESS = 'turnto_socialcommerce_configuration/checkout_comments/enable_checkout_success';

    const XML_PATH_ENABLE_CHECKOUT_COMMENTS_PRODUCT_DETAIL = 'turnto_socialcommerce_configuration/checkout_comments/enable_product_detail';
    
    const XML_PATH_COLUMNS = 'turnto_socialcommerce_configuration/checkout_comments/columns';

    /**
     * Questions and Answers
     */
    const XML_PATH_ENABLED = 'turnto_socialcommerce_configuration/general/enabled';

    const XML_PATH_SITE_KEY = 'turnto_socialcommerce_configuration/general/site_key';

    const XML_PATH_VERSION = 'turnto_socialcommerce_configuration/general/version';

    const XML_PATH_AUTHORIZATION_KEY = 'turnto_socialcommerce_configuration/general/authorization_key';

    const XML_PATH_SOCIALCOMMERCE_ENABLE_QA = 'turnto_socialcommerce_configuration/qa/enable_qa';

    const XML_PATH_SOCIALCOMMERCE_ENABLE_QA_TEASER = 'turnto_socialcommerce_configuration/qa/enable_qa_teaser';

    const XML_PATH_SOCIALCOMMERCE_SETUP_TYPE = 'turnto_socialcommerce_configuration/qa/setup_type';

    /**
     * Reviews
     */
    const XML_PATH_SOCIALCOMMERCE_ENABLE_REVIEWS = 'turnto_socialcommerce_configuration/reviews/enable_reviews';

    const XML_PATH_SOCIALCOMMERCE_ENABLE_REVIEWS_TEASER = 'turnto_socialcommerce_configuration/reviews/enable_reviews_teaser';

    const XML_PATH_SOCIALCOMMERCE_REVIEWS_SETUP_TYPE = 'turnto_socialcommerce_configuration/reviews/reviews_setup_type';

    const XML_PATH_SOCIALCOMMERCE_MOBILE_PAGE_TITLE = 'turnto_socialcommerce_configuration/mobile/mobile_page_title';

    const XML_PATH_SOCIALCOMMERCE_ENABLE_PRODUCT_FEED_SUBMISSION = 'turnto_socialcommerce_configuration/product_feed/enable_automatic_submission';

    const XML_PATH_SOCIALCOMMERCE_FEED_SUBMISSION_URL = 'turnto_socialcommerce_configuration/product_feed/feed_submission_url';

    const XML_PATH_SOCIALCOMMERCE_HISTORICAL_FEED_ENABLED = 'turnto_socialcommerce_configuration/historical_orders_feed/enable_historical_feed';
    
    const XML_PATH_EXPORT_FEED_URL = 'turnto_socialcommerce_configuration/product_feed/product_feed_url';

    const XML_PATH_PRODUCT_GROUP = 'turnto_socialcommerce_configuration/product_attribute_mappings/';

    /**
     * Gallery
     */
    const XML_PATH_SOCIALCOMMERCE_ENABLE_GALLERY = 'turnto_socialcommerce_configuration/gallery/enable_gallery';

    /**
     * Setup Types
     */
    const SETUP_TYPE_DYNAMIC_EMBED = 'dynamicEmbed';

    const SETUP_TYPE_STATIC_EMBED = 'staticEmbed';

    const SETUP_TYPE_OVERLAY = 'overlay';

    /**
     * @var \Magento\Store\Model\StoreManagerInterface|null
     */
    protected $storeManager = null;

    /**
     * @var \Magento\Config\Model\ResourceModel\Config|null
     */
    protected $resourceModel = null;

    /**
     * Config constructor.
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Config\Model\ResourceModel\Config $resourceModel
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Config\Model\ResourceModel\Config $resourceModel
    ) {
        $this->storeManager = $storeManager;
        $this->resourceModel = $resourceModel;

        parent::__construct($context);
    }

    /**
     * Gets the store code from the currently set/scoped store
     * @return string
     */
    public function getCurrentStoreCode()
    {
        return $this->storeManager->getStore()->getCode();
    }

    /**
     * Gets the value of the setting that determines if TurnTo's configuration is enabled
     *
     * @param null $scopeCode
     * @return mixed
     */
    public function getIsEnabled($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $scopeCode ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the value of the setting that determines if automated Product Feed Submission is enabled
     *
     * @param $store = null
     * @return mixed
     */
    public function getIsProductFeedSubmissionEnabled($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_ENABLE_PRODUCT_FEED_SUBMISSION,
            ScopeInterface::SCOPE_STORE,
            $store ? $store : $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the TurnTo Site Key
     *
     * @param $scopeCode = null
     * @return mixed
     */
    public function getSiteKey($scopeCode = null)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_SOCIALCOMMERCE_SITE_KEY,
            ScopeInterface::SCOPE_STORE,
            $scopeCode ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the TurnTo API Version
     *
     * @param null $scopeCode
     * @return mixed
     */
    public function getTurnToVersion($scopeCode = null)
    {
        return str_replace(
            '.',
            '_',
            $this->scopeConfig->getValue(
                self::XML_PATH_SOCIALCOMMERCE_VERSION,
                ScopeInterface::SCOPE_STORE,
                $scopeCode ?: $this->getCurrentStoreCode()
            )
        );
    }

    /**
     * Gets the Static URL configuration value
     *
     * @param null $store
     * @return mixed
     */
    public function getStaticUrl($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_STATIC_URL,
            ScopeInterface::SCOPE_STORE,
            isset($store) ? $store : $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the URL configuration value
     *
     * @param null $store
     * @return mixed
     */
    public function getUrl($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_URL,
            ScopeInterface::SCOPE_STORE,
            isset($store) ? $store : $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Static URL configuration value with the protocol removed
     *
     * @param null $store
     * @return mixed
     */
    public function getStaticUrlWithoutProtocol($store = null)
    {
        return preg_replace("(^https?://)", "", $this->getStaticUrl($store));
    }

    /**
     * Gets the URL configuration value with the protocol removed
     *
     * @param null $store
     * @return mixed
     */
    public function getUrlWithoutProtocol($store = null)
    {
        return preg_replace("(^https?://)", "", $this->getUrl($store));
    }

    /**
     * Gets the Static Content Cache Time configuration value
     *
     * @param null $store
     * @return mixed
     */
    public function getStaticContentCacheTime($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_STATIC_CONTENT_CACHE_TIME,
            ScopeInterface::SCOPE_STORE,
            isset($store) ? $store : $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the TurnTo API Authorization Key
     *
     * @param $store = null
     * @return mixed
     */
    public function getAuthorizationKey($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_AUTHORIZATION_KEY,
            ScopeInterface::SCOPE_STORE,
            $store ? $store : $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the TurnTo URL to send a feed to
     *
     * @param $store = null
     * @return mixed
     */
    public function getFeedUploadAddress($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_FEED_SUBMISSION_URL,
            ScopeInterface::SCOPE_STORE,
            $store ? $store : $this->getCurrentStoreCode()
        );
    }

    /**
     * @param null $store
     * @return mixed
     */
    public function getExportFeedAddress($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_EXPORT_FEED_URL,
            ScopeInterface::SCOPE_STORE,
            isset($store) ? $store : $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Product Attribute Code that corresponds to the mapping key (see constants on this class)
     *
     * @param $mappingKey
     * @param $scopeType
     * @param $scopeCode
     * @return mixed
     */
    public function getProductAttributeMapping($mappingKey, $scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_PRODUCT_GROUP . $mappingKey,
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }

    /**
     * Gets an associative array of any set product attributes related to GTIN, key => mappingKey, value => attr_code
     *
     * @param $store = null
     * @return array
     */
    public function getGtinAttributesMap($store = null)
    {
        $gtinMap = [];
        foreach (self::PRODUCT_ATTRIBUTE_MAPPING_KEYS as $mappingKey) {
            $tempResult = null;
            $tempResult = $this->getProductAttributeMapping(
                $mappingKey,
                isset($store) ? $store : $this->getCurrentStoreCode()
            );
            if (!empty($tempResult)) {
                $gtinMap[$mappingKey] = $tempResult;
            }
        }
        return $gtinMap;
    }


    public function getCheckoutCommentsEnabledCheckoutSuccess($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_ENABLE_CHECKOUT_COMMENTS_SUCCESS,
            ScopeInterface::SCOPE_STORE,
            isset($store) ? $store : $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Checkout Comments Enabled Product Detail configuration value
     *
     * @param null $store
     * @return mixed
     */
    public function getCheckoutCommentsEnabledProductDetail($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_ENABLE_CHECKOUT_COMMENTS_PRODUCT_DETAIL,
            ScopeInterface::SCOPE_STORE,
            isset($store) ? $store : $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Checkout Comments Columns configuration value
     *
     * @param null $store
     * @return mixed
     */
    public function getColumns($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_COLUMNS,
            ScopeInterface::SCOPE_STORE,
            isset($store) ? $store : $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Question and Answer Enabled configuration value
     *
     * @param null $store
     * @return mixed
     */
    public function getQaEnabled($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_ENABLE_QA,
            ScopeInterface::SCOPE_STORE,
            isset($store) ? $store : $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Gallery Enabled configuration value
     *
     * @param null $store
     * @return mixed
     */
    public function getGalleryEnabled($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_ENABLE_GALLERY,
            ScopeInterface::SCOPE_STORE,
            isset($store) ? $store : $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Question and Answer Teaser Enabled configuration value
     *
     * @param null $store
     * @return mixed
     */
    public function getQaTeaserEnabled($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_ENABLE_QA_TEASER,
            ScopeInterface::SCOPE_STORE,
            isset($store) ? $store : $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Question and Answer Setup Type configuration value
     *
     * @return mixed
     */
    public function getSetupType($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_SETUP_TYPE,
            ScopeInterface::SCOPE_STORE,
            isset($store) ? $store : $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Reviews Enabled configuration value
     *
     * @param null $store
     * @return mixed
     */
    public function getReviewsEnabled($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_ENABLE_REVIEWS,
            ScopeInterface::SCOPE_STORE,
            isset($store) ? $store : $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Reviews Teaser Enabled configuration value
     *
     * @param null $store
     * @return mixed
     */
    public function getReviewsTeaserEnabled($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_ENABLE_REVIEWS_TEASER,
            ScopeInterface::SCOPE_STORE,
            isset($store) ? $store : $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Reviews Setup Type configuration value
     *
     * @param null $store
     * @return mixed
     */
    public function getReviewsSetupType($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_REVIEWS_SETUP_TYPE,
            ScopeInterface::SCOPE_STORE,
            isset($store) ? $store : $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Reviews Setup Type configuration value
     *
     * @param null $store
     * @return mixed
     */
    public function getMobilePageTitle($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_MOBILE_PAGE_TITLE,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * @param null $store
     * @return mixed
     */
    public function getIsHistoricalOrdersFeedEnabled($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_HISTORICAL_FEED_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store ? $store : $this->getCurrentStoreCode()
        );
    }
}
