<?php

namespace TurnTo\SocialCommerce\Block\Widget;

class Pinboard extends \Magento\CatalogWidget\Block\Product\ProductsList
{

    /**
     * Pinboard constructor.
     * @param \Magento\Catalog\Block\Product\Context $context
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\Catalog\Model\Product\Visibility $catalogProductVisibility
     * @param \Magento\Framework\App\Http\Context $httpContext
     * @param \Magento\Rule\Model\Condition\Sql\Builder $sqlBuilder
     * @param \Magento\CatalogWidget\Model\Rule $rule
     * @param \Magento\Widget\Helper\Conditions $conditionsHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Catalog\Model\Product\Visibility $catalogProductVisibility,
        \Magento\Framework\App\Http\Context $httpContext,
        \Magento\Rule\Model\Condition\Sql\Builder $sqlBuilder,
        \Magento\CatalogWidget\Model\Rule $rule,
        \Magento\Widget\Helper\Conditions $conditionsHelper,
        array $data
    ) {
        parent::__construct(
            $context,
            $productCollectionFactory,
            $catalogProductVisibility,
            $httpContext,
            $sqlBuilder,
            $rule,
            $conditionsHelper,
            $data
        );
    }

    public function getProductSkus()
    {
        $productSkus = [];
        
        foreach ($this->getProductCollection()->getItems() as $product) {
            array_push($productSkus, (string)$product->getSku());
        }

        return $productSkus;
    }

    public function getPinboardConfig()
    {
        $config = [
            'contentType' => 'checkoutComments',
            'skus' => $this->getProductSkus(),
            'limit' => (int)$this->getLimit(),
            'maxDaysOld' => (int)$this->getMaxDaysOld(),
            'maxCommentsPerBox' => (int)$this->getMaxCommentsPerBox(),
            'progressiveLoading' => (bool)$this->getProgressiveLoading()
        ];

        return json_encode($config, JSON_PRETTY_PRINT);
    }

}