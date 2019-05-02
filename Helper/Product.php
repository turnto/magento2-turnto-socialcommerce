<?php
/**
 * @category    ClassyLlama
 * @copyright   Copyright (c) 2018 Classy Llama Studios, LLC
 * @author      sean.templeton
 */

namespace TurnTo\SocialCommerce\Helper;


use Magento\Framework\App\Helper\AbstractHelper;

class Product extends AbstractHelper
{
    /**
     * @var \Magento\Catalog\Model\Product
     */
    protected $product;

    /**
     * Product constructor.
     * @param \Magento\Catalog\Block\Product\View\Description $descriptionBlock
     */
    public function __construct(
        \Magento\Catalog\Block\Product\View\Description $descriptionBlock
    )
    {
        $this->product = $descriptionBlock->getProduct();
    }

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
     * Converts characters from Magento that are not safe for TurnTo
     *
     * @param string $string
     *
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
     *
     * @return string
     */
    public function turnToSafeDecoding($string)
    {
        return str_replace(array_values(self::TURNTO_CHARACTER_MAPPING), array_keys(self::TURNTO_CHARACTER_MAPPING), $string);
    }

    /**
     * @return string
     */
    public function getProductSku()
    {
        $value = "";
        if ($this->product) {
            $value = $this->turnToSafeEncoding($this->product->getSku());
        }

        return $value;
    }
}