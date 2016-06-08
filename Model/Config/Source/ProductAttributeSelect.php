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
    protected $config = null;

    /**
     * @var \Magento\Catalog\Model\Product|null
     */
    protected $productFactory = null;

    /**
     * ProductAttributeSelect constructor.
     * @param \Magento\Catalog\Model\Product $productModel
     * @param \TurnTo\SocialCommerce\Helper\Config $config
     */
    public function __construct(
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \TurnTo\SocialCommerce\Helper\Config $config
    ) {
        $this->config = $config;
        $this->productFactory = $productFactory;
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

        foreach ($this->productFactory->create()->getAttributes() as $attribute) {
            $attributeCode = $attribute->getAttributeCode();

            //Prevents exposing system only attributes to user like (created_at, entity_id, etc)
            if ($attributeCode != $attribute->getFrontend()->getLabel()) {
                $optionArray[] = [
                    'value' => $attributeCode,
                    'label' => $attribute->getFrontend()->getLocalizedLabel() . " ($attributeCode)"
                    //not utilizing the translation function as this is already returning the Locale specific variant.
                ];
            }
        }

        return $optionArray;
    }
}
