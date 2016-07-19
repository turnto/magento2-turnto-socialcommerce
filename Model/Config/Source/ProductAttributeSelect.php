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
