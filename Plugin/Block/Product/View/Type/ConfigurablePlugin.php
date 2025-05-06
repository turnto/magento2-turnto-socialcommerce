<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Plugin\Block\Product\View\Type;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\ConfigurableProduct\Block\Product\View\Type\Configurable;
use Magento\Framework\Exception\NoSuchEntityException;
use TurnTo\SocialCommerce\Model\Config;

class ConfigurablePlugin
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Product
     */
    protected $product;

    /**
     * ConfigurablePlugin constructor.
     *
     * @param Config            $config
     * @param ProductRepository $product
     */
    public function __construct(Config $config, ProductRepository $product)
    {
        $this->config = $config;
        $this->product = $product;
    }

    /**
     * @param Configurable $subject
     * @param $result
     * @return false|string
     * @throws NoSuchEntityException
     */
    public function afterGetJsonConfig(
        Configurable $subject,
        $result
    ) {
        $result = json_decode($result,true);
        $parentProduct =  $this->product->getById($result['productId']);
        $result['useChild'] = $this->config->getUseChildSku();
        $result['parentSku'] = $parentProduct->getSku();

        $children = $parentProduct->getTypeInstance()->getUsedProducts($parentProduct);
        if ($children) {
            $result['childSkuMap'] = [];
            foreach ($children as $child) {
                $result['childSkuMap'][$child->getId()] = $child->getSku();
            }
        }

        return json_encode($result);
    }
}
