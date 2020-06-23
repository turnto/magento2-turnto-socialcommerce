<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2020 Classy Llama Studios, LLC
 */
namespace TurnTo\SocialCommerce\Plugin\Block\Product\View\Type;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\ConfigurableProduct\Block\Product\View\Type\Configurable;
use TurnTo\SocialCommerce\Helper\Config;

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
     * @param              $result
     *
     * @return false|string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function afterGetJsonConfig(
        Configurable $subject,
        $result
    ) {
        $result = json_decode($result, true);
        $result['useChild'] = (bool)$this->config->getUseChildSku();
        $result['parentSku'] = $this->product->getById($result['productId'])->getSku();
        return json_encode($result);
    }
}
