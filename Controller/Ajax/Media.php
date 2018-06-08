<?php
/**
 * TurnTo_SocialCommerce
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright  Copyright (c) 2018 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Controller\Ajax;

use Magento\Framework\Controller\ResultFactory;
use TurnTo\SocialCommerce\Helper\Product;

class Media extends \Magento\Swatches\Controller\Ajax\Media
{
    /**
     * @var \TurnTo\SocialCommerce\Helper\Config
     */
    protected $config;

    /**
     * @var \Magento\Swatches\Helper\Data
     */
    protected $swatchHelper;
    /**
     * @var Product
     */
    protected $productHelper;

    /**
     * Media constructor.
     *
     * @param \Magento\Framework\App\Action\Context           $context
     * @param \Magento\Swatches\Helper\Data                   $swatchHelper
     * @param \Magento\Catalog\Model\ProductFactory           $productModelFactory
     * @param \TurnTo\SocialCommerce\Helper\Config            $config
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadata
     * @param Product                                         $productHelper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Swatches\Helper\Data $swatchHelper,
        \Magento\Catalog\Model\ProductFactory $productModelFactory,
        \TurnTo\SocialCommerce\Helper\Config $config,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        Product $productHelper
    )
    {
        $version = $productMetadata->getVersion();

        if (version_compare($version, '2.2.0', '>=')) {
            $this->loadParentConstructor($context, $productModelFactory, $swatchHelper);
        } else {
            $this->loadLegacyParentConstructor($context, $swatchHelper, $productModelFactory);
        }

        $this->productHelper = $productHelper;
        $this->config = $config;
        $this->swatchHelper = $swatchHelper;
    }

    /**
     * Call through to parent constructor with new order of arguments; Magento 2.2.x+
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Catalog\Model\ProductFactory $productModelFactory
     * @param \Magento\Swatches\Helper\Data         $swatchHelper
     */
    public function loadParentConstructor(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Catalog\Model\ProductFactory $productModelFactory,
        \Magento\Swatches\Helper\Data $swatchHelper
    )
    {
        parent::__construct($context, $productModelFactory, $swatchHelper);
    }

    /**
     * Call through to parent constructor with old order of arguments; Magento 2.1.x
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Swatches\Helper\Data         $swatchHelper
     * @param \Magento\Catalog\Model\ProductFactory $productModelFactory
     */
    public function loadLegacyParentConstructor(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Swatches\Helper\Data $swatchHelper,
        \Magento\Catalog\Model\ProductFactory $productModelFactory
    )
    {
        parent::__construct($context, $swatchHelper, $productModelFactory);
    }

    /**
     * Get product media by fallback:
     * 1stly by default attribute values
     * 2ndly by getting base image from configurable product
     *
     * @return string
     */
    public function execute()
    {
        // Begin Edit
        if (!$this->config->getUseChildSku()) {
            return parent::execute();
        }
        // End Edit

        $productMedia = [];
        if ($productId = (int)$this->getRequest()->getParam('product_id')) {
            $currentConfigurable = $this->productModelFactory->create()->load($productId);
            $attributes = (array)$this->getRequest()->getParam('attributes');
            if (!empty($attributes)) {
                $product = $this->getProductVariationWithMedia($currentConfigurable, $attributes);
            }
            if ((empty($product) || (!$product->getImage() || $product->getImage() == 'no_selection'))
                && isset($currentConfigurable)
            ) {
                $product = $currentConfigurable;
            }
            $productMedia = $this->swatchHelper->getProductMediaGallery($product);

            // Begin Edit
            $childProduct = $this->swatchHelper->loadVariationByFallback($product, $attributes);
            $productMedia['sku'] = $this->productHelper->turnToSafeEncoding(
                $childProduct ? $childProduct->getSku() : $product->getSku()
            );
            // End Edit
        }

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData($productMedia);

        return $resultJson;
    }
}
