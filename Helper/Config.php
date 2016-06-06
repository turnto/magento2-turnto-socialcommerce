<?php

namespace TurnTo\SocialCommerce\Helper;

/**
 * Class Config - Assists with retrieval of TurnTo configuration settings
 * @package TurnTo\SocialCommerce\Helper
 */
class Config extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Configuration key for UPC to Product Attribute Mapping
     */
    const UPC_ATTRIBUTE = 'upc_attribute';

    /**
     * Configuration key for MPN to Product Attribute Mapping
     */
    const MPN_ATTRIBUTE = 'mpn_attribute';

    /**
     * Configuration key for ISBN to Product Attribute Mapping
     */
    const ISBN_ATTRIBUTE = 'isbn_attribute';

    /**
     * Configuration key for EAN to Product Attribute Mapping
     */
    const EAN_ATTRIBUTE = 'ean_attribute';

    /**
     * Configuration key for JAN to Product Attribute Mapping
     */
    const JAN_ATTRIBUTE = 'jan_attribute';

    /**
     * Configuration key for ASIN to Product Attribute Mapping
     */
    const ASIN_ATTRIBUTE = 'asin_attribute';

    /**
     * Configuration key for Brand to Product Attribute Mapping
     */
    const BRAND_ATTRIBUTE = 'brand_attribute';

    /**
     * Array of the Product Attribute related mapping keys
     */
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
     * XPATH to the TurnTo General Enabled Setting
     */
    const XML_PATH_ENABLED = 'turnto_socialcommerce_configuration/general/enabled';

    /**
     * XPATH to the TurnTo General Site Key Setting
     */
    const XML_PATH_SITE_KEY = 'turnto_socialcommerce_configuration/general/site_key';


    /**
     * XPATH to the TurnTo General API Version Setting
     */
    const XML_PATH_VERSION = 'turnto_socialcommerce_configuration/general/version';


    /**
     * XPATH to the TurnTo General Authorization Key Setting
     */
    const XML_PATH_AUTHORIZATION_KEY = 'turnto_socialcommerce_configuration/general/authorization_key';

    /**
     * XPATH to the TurnTo Product Feed Automatic Submission Enabled Setting
     */
    const XML_PATH_ENABLE_PRODUCT_FEED_SUBMISSION = 'turnto_socialcommerce_configuration/product_feed/enable_automatic_submission';

    /**
     * XPATH to the TurnTo Product Feed Submission URL Setting
     */
    const XML_PATH_FEED_SUBMISSION_URL = 'turnto_socialcommerce_configuration/product_feed/feed_submission_url';

    /**
     * XPATH to the TurnTo Product Attribute Mappings Setting Group
     */
    const XML_PATH_PRODUCT_GROUP = 'turnto_socialcommerce_configuration/product_attribute_mappings/';

    /**
     * @var \Magento\Store\Model\StoreManagerInterface|null
     */
    protected $storeManager = null;

    /**
     * Config constructor.
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
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
     * @param $scopeType
     * @param $scopeCode
     * @return mixed
     */
    public function getIsEnabled ($scopeType, $scopeCode)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_ENABLED, $scopeType, $scopeCode);
    }

    /**
     * Gets the value of the setting that determines if automated Product Feed Submission is enabled
     *
     * @param $scopeType
     * @param $scopeCode
     * @return mixed
     */
    public function getIsProductFeedSubmissionEnabled ($scopeType, $scopeCode)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_ENABLE_PRODUCT_FEED_SUBMISSION, $scopeType, $scopeCode);
    }

    /**
     * Gets the TurnTo Site Key
     *
     * @param $scopeType
     * @param $scopeCode
     * @return mixed
     */
    public function getSiteKey ($scopeType, $scopeCode)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_SITE_KEY, $scopeType, $scopeCode);
    }

    /**
     * Gets the TurnTo API Version
     *
     * @param $scopeType
     * @param $scopeCode
     * @return mixed
     */
    public function getTurnToVersion ($scopeType, $scopeCode)
    {
        return str_replace(
            '.',
            '_',
            $this->scopeConfig->getValue(self::XML_PATH_VERSION, $scopeType, $scopeCode)
        );
    }

    /**
     * Gets the TurnTo API Authorization Key
     *
     * @param $scopeType
     * @param $scopeCode
     * @return mixed
     */
    public function getAuthorizationKey ($scopeType, $scopeCode)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_AUTHORIZATION_KEY, $scopeType, $scopeCode);
    }

    /**
     * Gets the TurnTo URL to send a feed to
     *
     * @param $scopeType
     * @param $scopeCode
     * @return mixed
     */
    public function getFeedUploadAddress ($scopeType, $scopeCode)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_FEED_SUBMISSION_URL, $scopeType, $scopeCode);
    }

    /**
     * Gets the Product Attribute Code that corresponds to the mapping key (see constants on this class)
     *
     * @param $mappingKey
     * @param $scopeType
     * @param $scopeCode
     * @return mixed
     */
    public function getProductAttributeMapping ($mappingKey, $scopeType, $scopeCode)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_PRODUCT_GROUP . $mappingKey, $scopeType, $scopeCode);
    }

    /**
     * Gets an associative array of any set product attributes related to GTIN, key => mappingKey, value => attr_code
     *
     * @param $scopeType
     * @param $scopeCode
     * @return array
     */
    public function getGtinAttributesMap ($scopeType, $scopeCode)
    {
        $gtinMap = [];
        foreach (self::PRODUCT_ATTRIBUTE_MAPPING_KEYS as $mappingKey) {
            $tempResult = null;
            $tempResult = $this->getProductAttributeMapping($mappingKey, $scopeType, $scopeCode);
            if (!empty($tempResult)) {
                $gtinMap[$mappingKey] = $tempResult;
            }
        }
        return $gtinMap;
    }
}
