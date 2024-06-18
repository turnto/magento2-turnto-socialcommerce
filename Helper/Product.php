<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Helper;

use Magento\Catalog\Block\Product\View\Description;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class Product extends AbstractHelper
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
     * @var ProductModel
     */
    protected $product;

    /**
     * Product constructor.
     * @param Context $context
     * @param Description $descriptionBlock
     */
    public function __construct(
        Context $context,
        Description $descriptionBlock
    ) {
        parent::__construct($context);
        $this->product = $descriptionBlock->getProduct();
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
     * @return string
     */
    public function getProductSku()
    {
        $value = "";
        if ($this->product && $this->product->getSku()) {
            $value = $this->turnToSafeEncoding($this->product->getSku());
        }

        return $value;
    }
}
