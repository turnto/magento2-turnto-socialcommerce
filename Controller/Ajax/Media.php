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
 * @copyright  Copyright (c) 2016 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Controller\Ajax;

use \Magento\Framework\Controller\ResultFactory;

class Media extends \Magento\Swatches\Controller\Ajax\Media
{
    /**
     * Get product media by fallback:
     * 1stly by default attribute values
     * 2ndly by getting base image from configurable product
     *
     * @return string
     */
    public function execute()
    {
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
        }

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        // Begin Edit
        $childProduct = $this->swatchHelper->loadVariationByFallback($product, $attributes);
        if ($childProduct) {
            $productMedia['sku'] = $childProduct->getSku();
        } else {
            $productMedia['sku'] = $product->getSku();
        }
        // End Edit

        $resultJson->setData($productMedia);

        return $resultJson;
    }
}
