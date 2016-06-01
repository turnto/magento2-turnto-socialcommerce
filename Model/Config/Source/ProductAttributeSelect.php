<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 5/31/16
 * Time: 12:46 PM
 */

namespace TurnTo\SocialCommerce\Model\Config\Source;

/**
 * Class ProductAttributeSelect
 * @package TurnTo\SocialCommerce\Model\Config\Source
 */
class ProductAttributeSelect implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var null|\TurnTo\SocialCommerce\Helper\Config
     */
    public $config = null;

    /**
     * @var \Magento\Catalog\Model\Product|null
     */
    public $productModel = null;

    /**
     * ProductAttributeSelect constructor.
     * @param \Magento\Catalog\Model\Product $productModel
     * @param \TurnTo\SocialCommerce\Helper\Config $config
     */
    public function __construct
    (
        \Magento\Catalog\Model\Product $productModel,
        \TurnTo\SocialCommerce\Helper\Config $config
    )
    {
        $this->config = $config;
        $this->productModel = $productModel;
    }

    /**
     * Options getter
     * @return array
     */
    public function toOptionArray()
    {
        $optionArray = [
            [
                'value' => '',
                'label' => __('Not Defined')
            ]
        ];

        foreach($this->productModel->getAttributes() as $attribute)
        {
            $attributeCode = $attribute->getAttributeCode();
            if ($attributeCode != $attribute->getFrontend()->getLabel())
            {
                $optionArray[] = [
                    'value' => $attributeCode,
                    'label' => $attribute->getFrontend()->getLocalizedLabel() . " ($attributeCode)"
                ];
            }
        }

        return $optionArray;
    }
}
