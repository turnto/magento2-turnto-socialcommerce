<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TurnTo\SocialCommerce\Model\Config\Source;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\SortOrderBuilderFactory;
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
     * @var SearchCriteriaBuilderFactory
     */
    protected $searchCriteriaBuilderFactory;
    /**
     * @var SortOrderBuilderFactory
     */
    protected $sortOrderBuilderFactory;

    /**
     * @param AttributeRepositoryInterface $attributeRepository
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param SortOrderBuilderFactory $sortOrderBuilderFactory
     */
    public function __construct(
        AttributeRepositoryInterface $attributeRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        SortOrderBuilderFactory $sortOrderBuilderFactory
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->sortOrderBuilderFactory = $sortOrderBuilderFactory;
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

        $sortOrder = $this->sortOrderBuilderFactory->create()
            ->setField('frontend_label')->setAscendingDirection()->create();
        // Filter out system only attributes e.g. created_at, entity_id, etc.
        $searchCriteria = $this->searchCriteriaBuilderFactory->create()
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
