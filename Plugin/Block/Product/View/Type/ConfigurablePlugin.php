<?php
/**
 * @category    ClassyLlama
 * @package     
 * @copyright   Copyright (c) 2020 Classy Llama Studios, LLC
 */
namespace TurnTo\SocialCommerce\Plugin\Block\Product\View\Type;

class ConfigurablePlugin
{

    /**
     * @var \TurnTo\SocialCommerce\Helper\Config
     */
    protected $config;

    /**
     * @var \Magento\Catalog\Model\Product
     */
    protected $product;

    public function __construct(\TurnTo\SocialCommerce\Helper\Config $config, \Magento\Catalog\Model\ProductRepository $product)
    {
        $this->config = $config;
        $this->product = $product;
    }

    public function afterGetJsonConfig(
        \Magento\ConfigurableProduct\Block\Product\View\Type\Configurable $subject,
        $result
    )
    {
        $result = json_decode($result,true);
        $result['useChild'] = (bool)$this->config->getUseChildSku();
        $result['parentSku'] = $this->product->getById($result['productId'])->getSku();
        return json_encode($result);
    }
}
