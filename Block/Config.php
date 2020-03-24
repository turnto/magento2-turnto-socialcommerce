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

namespace TurnTo\SocialCommerce\Block;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\View\Element\Template;
use TurnTo\SocialCommerce\Helper\Product;

class Config extends Template
{
    /**
     * @var \Magento\Catalog\Model\Product
     */
    protected $_product;

    /**
     * @var \TurnTo\SocialCommerce\Helper\Config
     */
    protected $config;

    /**
     * @var \Magento\Framework\Locale\Resolver
     */
    protected $localeResolver;
    /**
     * @var Product
     */
    protected $productHelper;

    /**
     * @param Template\Context                                $context
     * @param \TurnTo\SocialCommerce\Helper\Config            $config
     * @param \Magento\Framework\Locale\Resolver              $localeResolver
     * @param \Magento\Catalog\Block\Product\View\Description $descriptionBlock
     * @param Product                                         $productHelper
     * @param array                                           $data
     */
    public function __construct(
        Template\Context $context,
        \TurnTo\SocialCommerce\Helper\Config $config,
        \Magento\Framework\Locale\Resolver $localeResolver,
        \Magento\Catalog\Block\Product\View\Description $descriptionBlock,
        Product $productHelper,
        array $data = []
    )
    {
        parent::__construct($context, $data);

        $this->config = $config;
        $this->localeResolver = $localeResolver;
        $this->_product = $descriptionBlock->getProduct();
        $this->productHelper = $productHelper;
    }

    /**
     * @return null|string
     */
    public function getLocale()
    {
        return $this->localeResolver->getLocale();
    }

    /**
     * @return string
     */
    public function getProductSku()
    {
        $sku = $this->_product->getSku();

        if ($this->config->getUseChildSku() && $this->_product->getTypeId() == Configurable::TYPE_CODE) {
            $products = array_values($this->_product->getTypeInstance()->getUsedProducts($this->_product));
            $firstChild = reset($products);

            if ($firstChild) {
                $sku = $firstChild->getSku();
            }
        }

        return $this->productHelper->turnToSafeEncoding($sku);
    }

    /**
     * @return string
     */
    public function getGallerySkus()
    {
        $gallerySkus = [$this->productHelper->turnToSafeEncoding($this->_product->getSku())];

        if ($this->config->getUseChildSku() && $this->_product->getTypeId() == Configurable::TYPE_CODE) {
            $children = $this->_product->getTypeInstance()->getUsedProducts($this->_product);

            if (count($children) > 0) {
                $gallerySkus = array_map(
                    function ($child) {
                        return $this->productHelper->turnToSafeEncoding($child->getSku());
                    },
                    $children
                );
            }
        }

        return json_encode($gallerySkus);
    }
}
