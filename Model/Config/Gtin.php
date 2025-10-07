<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Config;

use TurnTo\SocialCommerce\Model\Config;

class Gtin
{
    const PRODUCT_ATTRIBUTE_MAPPINGS = 'turnto_socialcommerce_configuration/product_attribute_mappings/';

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
     * @var Config
     */
    protected $config;

    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * Gets an associative array of any set product attributes related to GTIN, key => mappingKey, value => attr_code
     *
     * @param string|int|null $scopeCode
     * @return array
     */
    public function getGtinAttributesMap($scopeCode = null)
    {
        $gtinMap = [];
        foreach (self::PRODUCT_ATTRIBUTE_MAPPING_KEYS as $mappingKey) {
            $value = $this->config->getConfigValue(
                self::PRODUCT_ATTRIBUTE_MAPPINGS . $mappingKey,
                $scopeCode
            );
            if (!empty($value)) {
                $gtinMap[$mappingKey] = $value;
            }
        }

        return $gtinMap;
    }
}
