<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use TurnTo\SocialCommerce\Model\Product as ProductModel;
use TurnTo\SocialCommerce\Model\Config as ConfigModel;

class ConfigJs implements ArgumentInterface
{
    /**
     * @var Product
     */
    protected $product;
    /**
     * @var ConfigModel
     */
    protected $config;
    /**
     * @var ProductModel
     */
    protected $productModel;
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @param ConfigModel $config
     * @param ProductModel $productModel
     * @param Registry $registry
     */
    public function __construct(
        ConfigModel $config,
        ProductModel $productModel,
        Registry $registry
    ) {
        $this->config = $config;
        $this->productModel = $productModel;
        $this->registry = $registry;
    }

    /**
     * @return bool
     */
    public function getIsEnabled()
    {
        return $this->config->getIsEnabled();
    }

    /**
     * @return string|null
     */
    public function getProductSku()
    {
        if (is_null($this->getProduct())) {
            return null;
        }

        return $this->productModel->turnToSafeEncoding($this->getProduct()->getSku());
    }

    protected function getProduct()
    {
        if (is_null($this->product)) {
            $this->product = $this->registry->registry('product');

            if (!$this->product->getId()) {
                return null;
            }
        }

        return $this->product;
    }
}
