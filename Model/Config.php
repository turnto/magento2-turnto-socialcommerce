<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use TurnTo\SocialCommerce\Logger\Monolog;

class Config
{
    public const GENERAL_ENABLED = 'turnto_socialcommerce_configuration/general/enabled';
    public const GENERAL_SITE_KEY = 'turnto_socialcommerce_configuration/general/site_key';
    public const GENERAL_AUTHORIZATION_KEY = 'turnto_socialcommerce_configuration/general/authorization_key';
    public const GENERAL_USE_CHILD_SKU = 'turnto_socialcommerce_configuration/general/use_child_sku';

    public const QA_ENABLE = 'turnto_socialcommerce_configuration/qa/enable_qa';

    public const REVIEWS_ENABLE = 'turnto_socialcommerce_configuration/reviews/enable_reviews';

    public const VISUAL_CONTENT_ENABLE_GALLERY_ROW = 'turnto_socialcommerce_configuration/visual_content/visual_content_gallery_row';

    public const TEASER_LOCAL_TEASER_CODE= 'turnto_socialcommerce_configuration/teaser/use_local_teaser_code';
    public const TEASER_REVIEWS_TEASER_ENABLE = 'turnto_socialcommerce_configuration/teaser/enable_reviews_teaser';
    public const TEASER_ENABLE_QA_TEASER = 'turnto_socialcommerce_configuration/teaser/enable_qa_teaser';
    public const TEASER_ENABLE_COMMENTS_TEASER = 'turnto_socialcommerce_configuration/teaser/enable_comments_teaser';


    public const CHECKOUT_ENABLE_COMMENTS_CAPTURE = 'turnto_socialcommerce_configuration/checkout_comments/enable_checkout_comment_capture';
    public const CHECKOUT_ENABLE_COMMENTS_PINBOARD_TEASER = 'turnto_socialcommerce_configuration/checkout_comments/enable_comments_pinboard_teaser';
    public const CHECKOUT_ENABLE_COMMENTS_PDP = 'turnto_socialcommerce_configuration/checkout_comments/enable_comments_pdp';
    public const CHECKOUT_ENABLE_TOP_COMMENTS = 'turnto_socialcommerce_configuration/checkout_comments/enable_top_comments';
    public const CHECKOUT_CUSTOMER_NAME_FALLBACK = 'turnto_socialcommerce_configuration/checkout_comments/js_order_feed_customer_name_fallback';

    public const PRODUCT_ENABLE_AUTOMATIC_SUBMISSION = 'turnto_socialcommerce_configuration/product_feed/enable_automatic_submission';
    public const PRODUCT_FEED_URL = 'turnto_socialcommerce_configuration/product_feed/product_feed_url';
    public const PRODUCT_FEED_SUBMISSION_URL = 'turnto_socialcommerce_configuration/product_feed/feed_submission_url';
    public const PRODUCT_REVIEW_URL = 'turnto_socialcommerce_configuration/product_feed/review_api_url';
    public const PRODUCT_WIDGET_URL = 'turnto_socialcommerce_configuration/product_feed/config_api_url';
    public const PRODUCT_API_URL = 'turnto_socialcommerce_configuration/product_feed/social_commerce_api_url';
    public const PRODUCT_STATIC_API_URL = 'turnto_socialcommerce_configuration/product_feed/social_commerce_static_api_url';

    const ORDER_ENABLE_FEED = 'turnto_socialcommerce_configuration/historical_orders_feed/enable_historical_feed';
    const ORDER_ENABLE_CANCELLED_FEED = 'turnto_socialcommerce_configuration/historical_orders_feed/enable_cancelled_feed';
    const ORDER_EXCLUDE_ITEMS_WITHOUT_DELIVERY_DATE = 'turnto_socialcommerce_configuration/historical_orders_feed/exclude_items_without_delivery_date';
    const ORDER_EXCLUDE_DELIVERY_DATE_ON_PARTIAL_SHIPMENT = 'turnto_socialcommerce_configuration/historical_orders_feed/exclude_delivery_date_until_all_items_shipped';

    public const AVERAGE_RATING_IMPORT_ENABLED = 'turnto_socialcommerce_configuration/average_rating_import/enable_average_rating';
    public const AVERAGE_RATING_IMPORT_AGGREGATE_DATA = 'turnto_socialcommerce_configuration/average_rating_import/import_aggregate_data';

    public const SSO_REVIEW_MSG = 'turnto_socialcommerce_configuration/sso/review_msg';
    public const SSO_REVIEW_MSG_PUR_REQ = 'turnto_socialcommerce_configuration/sso/review_msg_pur_req';
    public const SSO_QUESTION_MSG = 'turnto_socialcommerce_configuration/sso/question_msg';
    public const SSO_QUESTION_MSG_ANON = 'turnto_socialcommerce_configuration/sso/question_msg_anon';
    public const SSO_ANSWER_MSG = 'turnto_socialcommerce_configuration/sso/answer_msg';
    public const SSO_REPLY_MSG = 'turnto_socialcommerce_configuration/sso/reply_msg';

    public const SOCIALCOMMERCE_VERSION = 'v5';

    /**
     * @var Encryptor
     */
    protected $encryptor;
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var Monolog
     */
    protected $logger;

    public function __construct(
        Encryptor $encryptor,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Monolog $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->encryptor = $encryptor;
        $this->logger = $logger;
    }

    /**
     * Retrieves configuration value by path
     *
     * @param string $path
     * @param string|int|null $scopeCode
     * @param string $scopeType
     * @return mixed
     */
    public function getConfigValue($path, $scopeCode = null, $scopeType = ScopeInterface::SCOPE_STORES)
    {
        try {
            return $this->scopeConfig->getValue($path, $scopeType, $scopeCode ?: $this->getStoreCode());
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * @param $path
     * @param string|int|null $scopeCode
     * @param string $scopeType
     * @return bool
     */
    public function getConfigBool($path, $scopeCode = null, $scopeType = ScopeInterface::SCOPE_STORES)
    {
        return $this->scopeConfig->isSetFlag($path, $scopeType, $scopeCode);
    }

    /**
     * Gets the store code from the currently set/scoped store
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getStoreCode()
    {
        return $this->storeManager->getStore()->getCode();
    }

    /**
     * @param string|int|null $scopeCode
     * @param string $scopeType
     * @return bool
     */
    public function getIsEnabled($scopeCode = null, $scopeType = ScopeInterface::SCOPE_STORES)
    {
        return $this->getConfigBool(self::GENERAL_ENABLED, $scopeCode, $scopeType);
    }

    /**
     * @param string|int|null $scopeCode
     * @param string $scopeType
     * @return string|null
     */
    public function getSiteKey($scopeCode = null, $scopeType = ScopeInterface::SCOPE_STORES)
    {
        return $this->getConfigValue(self::GENERAL_SITE_KEY, $scopeCode, $scopeType);
    }

    /**
     * @param string|int|null $scopeCode
     * @param string $scopeType
     * @return string|null
     */
    public function getAuthorizationKey($scopeCode = null, $scopeType = ScopeInterface::SCOPE_STORES)
    {
        $authKey = $this->getConfigValue(self::GENERAL_AUTHORIZATION_KEY, $scopeCode, $scopeType);
        if ($authKey) {
            try {
                $authKey = $this->encryptor->decrypt($authKey);
            } catch (Exception $e) {
                $this->logger->error('Failed to decrypt authorization key. Error: ' . $e->getMessage());
                $authKey = null;
            }
        }

        return $authKey;
    }

    /**
     * @param string|int|null $scopeCode
     * @param string $scopeType
     * @return bool
     */
    public function getUseChildSku($scopeCode = null, $scopeType = ScopeInterface::SCOPE_STORES)
    {
        return $this->getConfigBool(self::GENERAL_USE_CHILD_SKU, $scopeCode, $scopeType);
    }

    /**
     * @param string $path
     * @param string|int|null $scopeCode
     * @param string $scopeType
     * @return string|null
     */
    public function getUrlWithoutProtocol($path, $scopeCode = null, $scopeType = ScopeInterface::SCOPE_STORES)
    {
        return preg_replace("(^https?://)", "", $this->getConfigValue($path, $scopeCode, $scopeType));
    }
}
