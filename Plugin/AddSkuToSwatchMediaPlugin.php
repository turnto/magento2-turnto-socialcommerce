<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Plugin;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Swatches\Helper\Data;
use TurnTo\SocialCommerce\Model\Product;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Config;

class AddSkuToSwatchMediaPlugin
{
    /**
     * @var RequestInterface
     */
    protected $request;
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;
    /**
     * @var Data
     */
    protected $swatchHelper;
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var Product
     */
    protected $product;
    /**
     * @var Monolog
     */
    protected $logger;

    public function __construct(
        RequestInterface $request,
        ProductRepositoryInterface $productRepository,
        Data $swatchHelper,
        Config $config,
        Product $product,
        Monolog $logger
    ) {
        $this->request = $request;
        $this->productRepository = $productRepository;
        $this->swatchHelper = $swatchHelper;
        $this->config = $config;
        $this->product = $product;
        $this->logger = $logger;
    }

    /**
     * Add SKU to results
     *
     * @param Data $subject
     * @param array $result
     * @return array
     */
    public function afterGetProductMediaGallery(Data $subject, $result)
    {
        if (empty($result) || !$this->config->getIsEnabled() || !$this->config->getUseChildSku()) {
            return $result;
        }

        try {
            $productId = (int)$this->request->getParam('product_id');
            if (!empty($productId)) {
                $product = $this->productRepository->getById($productId);
                $childProduct = $this->swatchHelper->loadVariationByFallback($product, []);
                $result['sku'] = $this->product->turnToSafeEncoding(
                    $childProduct ? $childProduct->getSku() : $product->getSku()
                );
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }
        return $result;
    }
}
