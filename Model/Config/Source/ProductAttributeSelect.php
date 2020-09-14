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

namespace TurnTo\SocialCommerce\Model\Config\Source;

use Magento\Eav\Api\Data\AttributeInterface;

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
     * @var \Magento\Catalog\Model\Product\Attribute\Repository
     */
    protected $productAttributeRepository;

    /**
     * @var \Magento\Framework\Api\FilterBuilder
     */
    protected $filterBuilder;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * ProductAttributeSelect constructor.
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \TurnTo\SocialCommerce\Helper\Config $config
     */
    public function __construct(
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \TurnTo\SocialCommerce\Helper\Config $config,
        \Magento\Catalog\Model\Product\Attribute\Repository $productAttributeRepository,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->config = $config;
        $this->productFactory = $productFactory;
        $this->productAttributeRepository = $productAttributeRepository;
        $this->filterBuilder = $filterBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
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

        // empty search criteria to get all product attributes
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $attributeList = $this->productAttributeRepository->getList($searchCriteria);

        foreach ($attributeList->getItems() as $attribute) {
            $attributeCode = $attribute->getAttributeCode();

            //Prevents exposing system only attributes to user like (created_at, entity_id, etc)
            if ($attributeCode) {
                $optionArray[] = [
                    'value' => $attributeCode,
                    'label' => $attribute->getDefaultFrontendLabel() . " ($attributeCode)"
                    //not utilizing the translation function as this is already returning the Locale specific variant.
                ];
            }
        }

        return $optionArray;
    }
}
