<?php
/**
 * TurnTo_SocialCommerce
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * @copyright  Copyright (c) 2018 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Helper;

use Magento\Store\Model\ScopeInterface;

/**
 * Class Config - Assists with retrieval of TurnTo configuration settings
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

    const XML_PATH_SOCIALCOMMERCE_USE_CHILD_SKU = 'turnto_socialcommerce_configuration/general/use_child_sku';

    const SOCIALCOMMERCE_VERSION = 'v5';

    const SOCIALCOMMERCE_URL = 'turnto_socialcommerce_configuration/product_feed/social_commerce_api_url';

    const SOCIALCOMMERCE_STATIC_URL = 'turnto_socialcommerce_configuration/product_feed/social_commerce_static_api_url';

    const SOCIALCOMMERCE_SINGLE_SIGN_ON = 'turnto_socialcommerce_configuration/general/single_sign_on';

    const WIDGET_URL = 'turnto_socialcommerce_configuration/product_feed/config_api_url';

    const TEASER_URL = 'turnto_socialcommerce_configuration/product_feed/teaser_api_url';


    const SOCIALCOMMERCE_MOBILE_TITLE_PAGE = 'TurnTo - Social Commerce';
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
     * Questions and Answers
     */
    const XML_PATH_SOCIALCOMMERCE_ENABLE_QA = 'turnto_socialcommerce_configuration/qa/enable_qa';

    const XML_PATH_SOCIALCOMMERCE_ENABLE_QA_TEASER = 'turnto_socialcommerce_configuration/qa/enable_qa_teaser';

    /**
     * Reviews
     */
    const XML_PATH_SOCIALCOMMERCE_ENABLE_REVIEWS = 'turnto_socialcommerce_configuration/reviews/enable_reviews';

    const XML_PATH_SOCIALCOMMERCE_ENABLE_REVIEWS_TEASER = 'turnto_socialcommerce_configuration/reviews/enable_reviews_teaser';

    const XML_PATH_SOCIALCOMMERCE_MOBILE_PAGE_TITLE = 'turnto_socialcommerce_configuration/mobile/mobile_page_title';

    const XML_PATH_SOCIALCOMMERCE_ENABLE_PRODUCT_FEED_SUBMISSION = 'turnto_socialcommerce_configuration/product_feed/enable_automatic_submission';

    const XML_PATH_SOCIALCOMMERCE_FEED_SUBMISSION_URL = 'turnto_socialcommerce_configuration/product_feed/feed_submission_url';

    const XML_PATH_SOCIALCOMMERCE_HISTORICAL_FEED_ENABLED = 'turnto_socialcommerce_configuration/historical_orders_feed/enable_historical_feed';

    const XML_PATH_SOCIALCOMMERCE_EXCLUDE_ITEMS_WITHOUT_DELIVERY_DATE = 'turnto_socialcommerce_configuration/historical_orders_feed/exclude_items_without_delivery_date';

    const XML_PATH_EXPORT_FEED_URL = 'turnto_socialcommerce_configuration/product_feed/product_feed_url';

    const XML_PATH_PRODUCT_GROUP = 'turnto_socialcommerce_configuration/product_attribute_mappings/';

    /**
     * Checkout Comments
     */
    const XML_PATH_SOCIALCOMMERCE_ENABLE_CHECKOUT_COMMENTS = 'turnto_socialcommerce_configuration/checkout_comments/enable_checkout_comments';

    const XML_PATH_SOCIALCOMMERCE_ENABLE_COMMENTS_PDP = 'turnto_socialcommerce_configuration/checkout_comments/enable_comments_pdp';

    const XML_PATH_SOCIALCOMMERCE_ENABLE_TOP_COMMENTS = 'turnto_socialcommerce_configuration/checkout_comments/enable_top_comments';

    const XML_PATH_SOCIALCOMMERCE_ENABLE_COMMENTS_TEASER = 'turnto_socialcommerce_configuration/checkout_comments/enable_comments_teaser';

    /**
     * Visual Content
     */
    CONST XML_PATH_SOCIALCOMMERCE_VISUAL_CONTENT_GALLERY_ROW_WIDGET = 'turnto_socialcommerce_configuration/visual_content/visual_content_gallery_row';

    /**
     * SSO
     */
    CONST XML_PATH_SOCIALCOMMERCE_SINGLE_SIGN_ON = 'turnto_socialcommerce_configuration/sso/single_sign_on';

    CONST XML_PATH_SOCIALCOMMERCE_REVIEW_MSG = 'turnto_socialcommerce_configuration/sso/review_msg';

    CONST XML_PATH_SOCIALCOMMERCE_REVIEW_MSG_PUR_REQ = 'turnto_socialcommerce_configuration/sso/review_msg_pur_req';

    CONST XML_PATH_SOCIALCOMMERCE_QUESTION_MSG = 'turnto_socialcommerce_configuration/sso/question_msg';

    CONST XML_PATH_SOCIALCOMMERCE_QUESTION_MSG_ANON = 'turnto_socialcommerce_configuration/sso/question_msg_anon';

    CONST XML_PATH_SOCIALCOMMERCE_ANSWER_MSG = 'turnto_socialcommerce_configuration/sso/answer_msg';

    CONST XML_PATH_SOCIALCOMMERCE_REPLY_MSG = 'turnto_socialcommerce_configuration/sso/reply_msg';




    /**
     * @var \Magento\Store\Model\StoreManagerInterface|null
     */
    protected $storeManager = null;

    /**
     * @var \Magento\Config\Model\ResourceModel\Config|null
     */
    protected $resourceModel = null;

    /**
     * @var \Magento\Framework\Encryption\Encryptor
     */
    protected $encryptor;

    /**
     * Config constructor.
     *
     * @param \Magento\Framework\App\Helper\Context      $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Config\Model\ResourceModel\Config $resourceModel
     * @param \Magento\Framework\Encryption\Encryptor    $encryptor
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Config\Model\ResourceModel\Config $resourceModel,
        \Magento\Framework\Encryption\Encryptor $encryptor
    )
    {
        $this->storeManager = $storeManager;
        $this->resourceModel = $resourceModel;
        $this->encryptor = $encryptor;
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
     *
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
     *
     * @return mixed
     */
    public function getIsProductFeedSubmissionEnabled($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_ENABLE_PRODUCT_FEED_SUBMISSION,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the TurnTo Site Key
     *
     * @param $scopeCode = null
     *
     * @return mixed
     */
    public function getSiteKey($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_SITE_KEY,
            ScopeInterface::SCOPE_STORE,
            $scopeCode ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the TurnTo API Version
     *
     * @param null $scopeCode
     *
     * @return mixed
     */
    public function getTurnToVersion($scopeCode = null)
    {
        return self::SOCIALCOMMERCE_VERSION;
    }

    /**
     * Gets the Static URL configuration value
     *
     * @param null $store
     *
     * @return mixed
     */
    public function getStaticUrl($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::SOCIALCOMMERCE_STATIC_URL,
            ScopeInterface::SCOPE_STORE,
            $scopeCode ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Widget URL configuration value
     *
     * @param null $store
     *
     * @return mixed
     */
    public function getWidgetUrl($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::WIDGET_URL,
            ScopeInterface::SCOPE_STORE,
            $scopeCode ?: $this->getCurrentStoreCode()
        );

    }



    /**
     * Gets the Teaser URL configuration value
     *
     * @param null $store
     *
     * @return mixed
     */
    public function getTeaserUrl($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::TEASER_URL,
            ScopeInterface::SCOPE_STORE,
            $scopeCode ?: $this->getCurrentStoreCode()
        );

    }




    /**
     * Gets the URL configuration value
     *
     * @param null $store
     *
     * @return mixed
     */
    public function getUrl($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::SOCIALCOMMERCE_URL,
            ScopeInterface::SCOPE_STORE,
            $scopeCode ?: $this->getCurrentStoreCode()
        );

    }

    /**
     * Gets the Use Child SKU configuration value
     *
     * @param null $store
     *
     * @return mixed
     */
    public function getUseChildSku($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_USE_CHILD_SKU,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Enable Single Sign On configuration value
     *
     * @param null $store
     *
     * @return bool
     */
    public function getSingleSignOn($store = null)
    {
        return self::SOCIALCOMMERCE_SINGLE_SIGN_ON;
    }

    /**
     * Gets the Static URL configuration value with the protocol removed
     *
     * @param null $store
     *
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
     *
     * @return mixed
     */
    public function getUrlWithoutProtocol($store = null)
    {
        return preg_replace("(^https?://)", "", $this->getUrl($store));
    }

    /**
     * Gets the TurnTo API Authorization Key
     *
     * @param $store = null
     *
     * @return mixed
     */
    public function getAuthorizationKey($store = null)
    {
        $authKey = $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_AUTHORIZATION_KEY,
            ScopeInterface::SCOPE_STORE,
            $store ? $store : $this->getCurrentStoreCode()
        );

        if ($authKey) {
            $authKey = $this->encryptor->decrypt($authKey);
        }

        return $authKey;
    }

    /**
     * Gets the TurnTo URL to send a feed to
     *
     * @param $store = null
     *
     * @return mixed
     */
    public function getFeedUploadAddress($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_FEED_SUBMISSION_URL,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getExportFeedAddress($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_EXPORT_FEED_URL,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Product Attribute Code that corresponds to the mapping key (see constants on this class)
     *
     * @param $mappingKey
     * @param $store
     *
     * @return mixed
     */
    public function getProductAttributeMapping($mappingKey, $store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_PRODUCT_GROUP . $mappingKey,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets an associative array of any set product attributes related to GTIN, key => mappingKey, value => attr_code
     *
     * @param $store = null
     *
     * @return array
     */
    public function getGtinAttributesMap($store = null)
    {
        $gtinMap = [];
        foreach (self::PRODUCT_ATTRIBUTE_MAPPING_KEYS as $mappingKey) {
            $tempResult = $this->getProductAttributeMapping(
                $mappingKey,
                $store ?: $this->getCurrentStoreCode()
            );
            if (!empty($tempResult)) {
                $gtinMap[$mappingKey] = $tempResult;
            }
        }

        return $gtinMap;
    }

    /**
     * Gets the Question and Answer Enabled configuration value
     *
     * @param null $store
     *
     * @return bool
     */
    public function getQaEnabled($store = null)
    {
        return (bool)$this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_ENABLE_QA,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Question and Answer Teaser Enabled configuration value
     *
     * @param null $store
     *
     * @return bool
     */
    public function getQaTeaserEnabled($store = null)
    {
        return (bool)$this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_ENABLE_QA_TEASER,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Reviews Enabled configuration value
     *
     * @param null $store
     *
     * @return bool
     */
    public function getReviewsEnabled($store = null)
    {
        return (bool)$this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_ENABLE_REVIEWS,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Reviews Teaser Enabled configuration value
     *
     * @param null $store
     *
     * @return bool
     */
    public function getReviewsTeaserEnabled($store = null)
    {
        return (bool)$this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_ENABLE_REVIEWS_TEASER,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Reviews Setup Type configuration value
     *
     * @param null $store
     *
     * @return mixed
     */
    public function getMobilePageTitle($store = null)
    {
        return self::SOCIALCOMMERCE_MOBILE_TITLE_PAGE;
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getIsHistoricalOrdersFeedEnabled($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_HISTORICAL_FEED_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * @param $store
     *
     * @return mixed
     */
    public function getExcludeItemsWithoutDeliveryDate($store)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_EXCLUDE_ITEMS_WITHOUT_DELIVERY_DATE,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Checkout Comments Enabled configuration value
     * @param null $store
     * @return bool
     */
    public function getCheckoutCommentsEnabled($store = null)
    {
        return (bool)$this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_ENABLE_CHECKOUT_COMMENTS,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Comments Enabled on PDP configuration value
     * @param null $store
     * @return bool
     */
    public function getCommentsPdpEnabled($store = null)
    {
        return (bool)$this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_ENABLE_COMMENTS_PDP,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Top Comments Enabled on PDP configuration value
     * @param null $store
     * @return bool
     */
    public function getTopCommentsEnabled($store = null)
    {
        return (bool)$this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_ENABLE_TOP_COMMENTS,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * Gets the Comments Teaser Enabled configuration value
     * @param null $store
     * @return bool
     */
    public function getCommentsTeaserEnabled($store = null)
    {
        return (bool)$this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_ENABLE_COMMENTS_TEASER,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * @param null $store
     * @return bool
     */
    public function getReviewMsg($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_REVIEW_MSG,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * @param null $store
     * @return bool
     */
    public function getReviewMsgPurchaseReq($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_REVIEW_MSG_PUR_REQ,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * @param null $store
     * @return bool
     */
    public function getQuestionMsg($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_QUESTION_MSG,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * @param null $store
     * @return bool
     */
    public function getQuestionMsgAnon($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_QUESTION_MSG_ANON,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * @param null $store
     * @return bool
     */
    public function getAnswerMessage($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_ANSWER_MSG,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * @param null $store
     * @return bool
     */
    public function getReplyMsg($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_REPLY_MSG,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

    /**
     * @param null $store
     * @return mixed
     */
    public function getVisualContentGalleryRowWidget($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_VISUAL_CONTENT_GALLERY_ROW_WIDGET,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }


    /**
     * @param null $store
     * @return bool
     */
    public function getSsoEnabled($store = null)
    {
        return (bool)$this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_SINGLE_SIGN_ON,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getCurrentStoreCode()
        );
    }

}
