<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model;

use Magento\Catalog\Model\Locator\RegistryLocator;
use Magento\Framework\Exception\NotFoundException;

class Product
{
    const TURNTO_CHARACTER_MAPPING = [
        '/' => 'FORWARDSLASH',
        '#' => 'HASH',
        '\\' => 'BACKSLASH',
        '>' => 'GREATERTHAN',
        '<' => 'LESSTHAN',
        '&' => 'AMPERSIGN',
        '=' => 'EQUALS',
        '%' => 'PERCENT',
        '!' => 'EXCLAMATION',
        '.' => 'PERIOD',
        '+' => 'PLUS'
    ];

    /**
     * @var RegistryLocator
     */
    protected $locator;

    /**
     * @param RegistryLocator $locator
     */
    public function __construct(
        RegistryLocator $locator
    ) {
        $this->locator = $locator;
    }

    /**
     * Converts characters from Magento that are not safe for TurnTo
     *
     * @param string $string
     * @return string
     */
    public function turnToSafeEncoding($string)
    {
        return str_replace(array_keys(self::TURNTO_CHARACTER_MAPPING), array_values(self::TURNTO_CHARACTER_MAPPING), $string);
    }

    /**
     * Reverses encoding done for TurnTo to match what is in Magento
     *
     * @param string $string
     * @return string
     */
    public function turnToSafeDecoding($string)
    {
        return str_replace(array_values(self::TURNTO_CHARACTER_MAPPING), array_keys(self::TURNTO_CHARACTER_MAPPING), $string);
    }

    /**
     * Encoded SKU for the current product in PDP / catalog context.
     *
     * @return string
     */
    public function getProductSku()
    {
        try {
            $product = $this->locator->getProduct();
            if ($product && $product->getSku()) {
                return $this->turnToSafeEncoding((string) $product->getSku());
            }
        } catch (NotFoundException $e) {
            return '';
        }

        return '';
    }
}
