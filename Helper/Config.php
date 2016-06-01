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
     * Gets the TurnTo Site Key
     * @return mixed
     */
    public function getSiteKey()
    {
        return $this->scopeConfig->getValue('turnto_socialcommerce_configuration/general/site_key');
    }

    /**
     * Gets the TurnTo API Version
     * @return mixed
     */
    public function getTurnToVersion()
    {
        return str_replace(
            '.',
            '_',
            $this->scopeConfig->getValue('turnto_socialcommerce_configuration/general/version')
        );
    }

    /**
     * Gets the TurnTo API Authorization Key
     * @return mixed
     */
    public function getAuthorizationKey()
    {
        return $this->scopeConfig->getValue('turnto_socialcommerce_configuration/general/authentication_key');
    }

    /**
     * Gets the TurnTo URL to send a feed to
     * @return mixed
     */
    public function getFeedUploadAddress()
    {
        //return 'https://www.turnto.com/feedUpload/postfile';
        return $this->scopeConfig->getValue('turnto_socialcommerce_configuration/product_feed/feed_submission_url');
    }

    /**
     * Gets the Product Attribute Code that corresponds to the mapping key (see constants on this class)
     * @param $mappingKey
     * @return mixed
     */
    public function getProductAttributeMapping($mappingKey)
    {
        return $this->scopeConfig->getValue("turnto_socialcommerce_configuration/general/$mappingKey");
    }

    /**
     * Gets an associative array of any set product attributes related to GTIN, key => mappingKey, value => attr_code
     * @return array
     */
    public function getGtinAttributesMap()
    {
        $gtinMap = [];
        foreach(self::PRODUCT_ATTRIBUTE_MAPPING_KEYS as $mappingKey)
        {
            $tempResult = null;
            $tempResult = self::getProductAttributeMapping($mappingKey);
            if (!empty($tempResult))
            {
                $gtinMap[$mappingKey] = $tempResult;
            }
        }
        return $gtinMap;
    }
}
