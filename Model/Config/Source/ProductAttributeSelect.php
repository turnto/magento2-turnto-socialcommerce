<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TurnTo\SocialCommerce\Model\Config\Source;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class ProductAttributeSelect
 * @package TurnTo\SocialCommerce\Model\Config\Source
 */
class ProductAttributeSelect implements OptionSourceInterface
{
    /**
     * @var AttributeRepositoryInterface
     */
    protected $attributeRepository;
    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;
    /**
     * @var SortOrderBuilder
     */
    protected $sortOrderBuilder;

    /**
     * @param AttributeRepositoryInterface $attributeRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     */
    public function __construct(
        AttributeRepositoryInterface $attributeRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
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

        $sortOrder = $this->sortOrderBuilder->setField('frontend_label')->setAscendingDirection()->create();
        // Filter out system only attributes e.g. created_at, entity_id, etc.
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('frontend_label', null, 'neq')
            ->addSortOrder($sortOrder)
            ->create();
        $attributeRepository = $this->attributeRepository->getList(
            ProductAttributeInterface::ENTITY_TYPE_CODE,
            $searchCriteria
        );

        foreach ($attributeRepository->getItems() as $productAttribute) {
            $attributeCode = $productAttribute->getAttributeCode();
            $optionArray[] = [
                'value' => $attributeCode,
                'label' => $productAttribute->getFrontend()->getLocalizedLabel() . " ($attributeCode)"
            ];
        }

        return $optionArray;
    }
}
