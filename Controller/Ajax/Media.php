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
use Magento\Catalog\Model\Product\Attribute\Source\Status;

class Media extends \Magento\Swatches\Controller\Ajax\Media
{
    /**
     * @var \TurnTo\SocialCommerce\Helper\Config
     */
    protected $configHelper;

    /**
     * @var \Magento\Swatches\Helper\Data
     */
    protected $swatchHelper;
    /**
     * @var Product
     */
    protected $productHelper;

    /**
     * @var \Magento\PageCache\Model\Config
     */
    protected $config;

    /**
     * Media constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Swatches\Helper\Data $swatchHelper
     * @param \Magento\Catalog\Model\ProductFactory $productModelFactory
     * @param \TurnTo\SocialCommerce\Helper\Config $configHelper
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadata
     * @param Product $productHelper
     * @param \Magento\PageCache\Model\Config $config
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Swatches\Helper\Data $swatchHelper,
        \Magento\Catalog\Model\ProductFactory $productModelFactory,
        \TurnTo\SocialCommerce\Helper\Config $configHelper,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        Product $productHelper,
        \Magento\PageCache\Model\Config $config
    ) {
        $version = $productMetadata->getVersion();

        if ($this->versionNeedsPageConfig($version)) {
            $this->loadParentConstructorWithConfig($context, $productModelFactory, $swatchHelper, $config);
        } elseif ($this->versionNeedsNewOrder($version)) {
            $this->loadParentConstructor($context, $productModelFactory, $swatchHelper);
        } else {
            $this->loadLegacyParentConstructor($context, $swatchHelper, $productModelFactory);
        }

        $this->productHelper = $productHelper;
        $this->configHelper = $configHelper;
        $this->swatchHelper = $swatchHelper;
        $this->config = $config;
    }

    /**
     * Determines if this version requires page cache config for the constructor
     *
     * @param $version
     * @return bool
     */
    public function versionNeedsPageConfig($version)
    {
        $returnValue = false;

        if (version_compare($version, '2.3.1', '>=') || version_compare($version, '2.2.9', '>=')) {
            $returnValue = true;
        }

        return $returnValue;
    }

    /**
     * Determines if this version requires new constructor order
     *
     * @param $version
     * @return bool
     */
    public function versionNeedsNewOrder($version)
    {
        $returnValue = false;

        if (version_compare($version, '2.2.0', '>=') && version_compare($version, '2.2.8', '<=')) {
            $returnValue = true;
        } elseif (version_compare($version, '2.3.0', '=')) {
            $returnValue = true;
        }

        return $returnValue;
    }

    /**
     * Call through to parent constructor with page cache config; Magneto 2.3.1+ and 2.2.9+
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Catalog\Model\ProductFactory $productModelFactory
     * @param \Magento\Swatches\Helper\Data $swatchHelper
     * @param \Magento\PageCache\Model\Config $config
     */
    public function loadParentConstructorWithConfig(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Catalog\Model\ProductFactory $productModelFactory,
        \Magento\Swatches\Helper\Data $swatchHelper,
        \Magento\PageCache\Model\Config $config
    ) {
        parent::__construct($context, $productModelFactory, $swatchHelper, $config);
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
    ) {
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
    ) {
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
        if (!$this->configHelper->getUseChildSku()) {
            return parent::execute();
        }
        // End Edit

        $productMedia = [];

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        /** @var \Magento\Framework\App\ResponseInterface $response */
        $response = $this->getResponse();

        if ($productId = (int)$this->getRequest()->getParam('product_id')) {
            /** @var \Magento\Catalog\Api\Data\ProductInterface $product */
            $product = $this->productModelFactory->create()->load($productId);
            $productMedia = [];
            if ($product->getId() && $product->getStatus() == Status::STATUS_ENABLED) {
                $productMedia = $this->swatchHelper->getProductMediaGallery($product);
            }
            $resultJson->setHeader('X-Magento-Tags', implode(',', $product->getIdentities()));

            $response->setPublicHeaders($this->config->getTtl());

            // Begin Edit
            $childProduct = $this->swatchHelper->loadVariationByFallback($product, []);
            $productMedia['sku'] = $this->productHelper->turnToSafeEncoding(
                $childProduct ? $childProduct->getSku() : $product->getSku()
            );
            // End Edit
        }

        $resultJson->setData($productMedia);

        return $resultJson;
    }
}
