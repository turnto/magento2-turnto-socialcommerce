<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\ViewModel;

use Magento\Catalog\Model\Locator\RegistryLocator;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use TurnTo\SocialCommerce\Logger\Monolog;
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
     * @var RegistryLocator
     */
    protected $locator;
    /**
     * @var Monolog
     */
    protected $logger;

    /**
     * @param ConfigModel $config
     * @param ProductModel $productModel
     * @param RegistryLocator $locator
     * @param Monolog $logger
     */
    public function __construct(
        ConfigModel $config,
        ProductModel $productModel,
        RegistryLocator $locator,
        Monolog $logger
    ) {
        $this->config = $config;
        $this->productModel = $productModel;
        $this->locator = $locator;
        $this->logger = $logger;
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
        if ($this->product === null) {
            try {
                $product = $this->locator->getProduct();
                $this->product = ($product && $product->getId()) ? $product : null;
            } catch (NotFoundException $e) {
                $this->logger->error($e->getMessage(), ['exception' => $e]);
                $this->product = null;
            }
        }

        return $this->product;
    }
}
