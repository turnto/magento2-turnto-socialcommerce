<?php
/**
f * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Block\Widget;

use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogWidget\Block\Product\ProductsList;
use Magento\CatalogWidget\Model\Rule;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Exception\LocalizedException;
use Magento\Rule\Model\Condition\Sql\Builder;
use Magento\Widget\Helper\Conditions;
use TurnTo\SocialCommerce\Block\TurnToConfig;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Data\PinboardConfigFactory;

class Pinboard extends ProductsList
{
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var PinboardConfigFactory
     */
    protected $pinboardConfigFactory;

    /**
     * @param Config $config
     * @param PinboardConfigFactory $pinboardConfigFactory
     * @param Context $context
     * @param CollectionFactory $productCollectionFactory
     * @param Visibility $catalogProductVisibility
     * @param HttpContext $httpContext
     * @param Builder $sqlBuilder
     * @param Rule $rule
     * @param Conditions $conditionsHelper
     * @param array $data
     * @param Json|null $json
     */
    public function __construct(
        Config $config,
        PinboardConfigFactory $pinboardConfigFactory,
        Context $context,
        CollectionFactory $productCollectionFactory,
        Visibility $catalogProductVisibility,
        HttpContext $httpContext,
        Builder $sqlBuilder,
        Rule $rule,
        Conditions $conditionsHelper,
        array $data = [],
        Json $json = null,
    ) {
        $this->config = $config;
        $this->pinboardConfigFactory = $pinboardConfigFactory;
        parent::__construct(
            $context,
            $productCollectionFactory,
            $catalogProductVisibility,
            $httpContext,
            $sqlBuilder,
            $rule,
            $conditionsHelper,
            $data,
            $json
        );
    }

    /**
     * Prepare and return product collection
     *
     * @return Collection
     * @SuppressWarnings(PHPMD.RequestAwareBlockMethod)
     */
    public function createCollection()
    {
        $collection = $this->productCollectionFactory->create();
        if ($this->getData('store_id') !== null) {
            $collection->setStoreId($this->getData('store_id'));
        }

        $collection->setVisibility($this->catalogProductVisibility->getVisibleInCatalogIds());

        $collection = $this->_addProductAttributesAndPrices($collection)
            ->addStoreFilter()
            ->addAttributeToSort('entity_id', 'desc')
            ->setPageSize($this->getPageSize())
            ->setCurPage($this->getRequest()->getParam($this->getData('page_var_name'), 1));

        // Removed conditions; getBaseCollection doesn't exist in Magento 3.3

        /**
         * Prevent retrieval of duplicate records. This may occur when multiselect product attribute matches
         * several allowed values from condition simultaneously
         */
        $collection->distinct(true);

        return $collection;
    }

    /**
     * Return an array of product SKUs from the pinboard widget configuration.
     *
     * @return array
     */
    public function getProductSkus()
    {
        $productSkus = $this->getData('skus');
        return $productSkus ? array_map('trim' , explode(',', $productSkus)) : [];
    }

    /**
     * Return an array of product Brands from the pinboard widget configuration.
     *
     * @return array
     */
    public function getProductBrands()
    {
        $productBrands = $this->getData('brands');
        return $productBrands ? array_map('trim' , explode(',', $productBrands)) : [];
    }

    /**
     * Return an array of product tags from the pinboard widget configuration.
     *
     * @return array
     */
    public function getProductTags()
    {
        $productTags = $this->getData('tags');
        return $productTags ? array_map('trim' , explode(',', $productTags)) : [];
    }

    /**
     * Creates a TurnTo config block and outputs its html content
     * @return string
     */
    public function getTurnToConfigHtml()
    {
        /** @var TurnToConfig $pinboardBlock */
        try {
            $pinboardBlock = $this->getLayout()->createBlock(
                TurnToConfig::class,
                'turnto.config.pinboard'
            );
        } catch (LocalizedException $e) {
            return '';
        }

        $pinboardBlock->setConfigData($this->pinboardConfigFactory->create(['pinboardBlock' => $this]));

        return $pinboardBlock->toHtml();
    }

    public function getPageTitle()
    {
        return $this->getData('title');
    }

    /**
     * @param $path
     * @return mixed|null
     */
    public function getConfigValue($path)
    {
        return $this->config->getConfigValue($path);
    }
}
