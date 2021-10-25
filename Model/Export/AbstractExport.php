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

namespace TurnTo\SocialCommerce\Model\Export;

/**
 * Class AbstractExport
 * @package TurnTo\SocialCommerce\Model\Export
 */
class AbstractExport
{
    /**
     * Default page size
     */
    const DEFAULT_PAGE_SIZE = 25;

    /**
     * @var null|\TurnTo\SocialCommerce\Helper\Config
     */
    protected $config = null;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory|null
     */
    protected $productCollectionFactory = null;

    /**
     * @var null|\TurnTo\SocialCommerce\Logger\Monolog
     */
    protected $logger = null;

    /**
     * @var \Magento\Framework\Intl\DateTimeFactory|null
     */
    protected $dateTimeFactory = null;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder|null
     */
    protected $searchCriteriaBuilder = null;

    /**
     * @var \Magento\Framework\Api\FilterBuilder|null
     */
    protected $filterBuilder = null;

    /**
     * @var \Magento\Framework\Api\SortOrderBuilder|null
     */
    protected $sortOrderBuilder = null;

    /**
     * @var \Magento\Framework\HTTP\ZendClientFactory|null
     */
    protected $zendClientFactory = null;

    /**
     * @var \Magento\UrlRewrite\Model\UrlFinderInterface|null
     */
    protected $urlFinder = null;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface|null
     */
    protected $storeManager = null;

    /**
     * AbstractExport constructor.
     *
     * @param \TurnTo\SocialCommerce\Helper\Config $config
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \TurnTo\SocialCommerce\Logger\Monolog $logger
     * @param \Magento\Framework\Intl\DateTimeFactory $dateTimeFactory
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Framework\Api\FilterBuilder $filterBuilder
     * @param \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder
     * @param \Magento\UrlRewrite\Model\UrlFinderInterface $urlFinder
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \TurnTo\SocialCommerce\Helper\Config $config,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \TurnTo\SocialCommerce\Logger\Monolog $logger,
        \Magento\Framework\Intl\DateTimeFactory $dateTimeFactory,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder,
        \Magento\UrlRewrite\Model\UrlFinderInterface $urlFinder,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->logger = $logger;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->urlFinder = $urlFinder;
        $this->storeManager = $storeManager;
    }

    /**
     * @param $fieldId
     * @param string $direction
     * @return \Magento\Framework\Api\AbstractSimpleObject
     */
    public function getSortOrder($fieldId, $direction = \Magento\Framework\Api\SortOrder::SORT_ASC)
    {
        return $this->sortOrderBuilder->setField($fieldId)->setDirection($direction)->create();
    }

    public function getFilter($fieldId, $value, $conditionType)
    {
        return $this->filterBuilder
            ->setField($fieldId)
            ->setValue($value)
            ->setConditionType($conditionType)
            ->create();
    }

    public function getSearchCriteria($sortOrder, $filters = [], $pageSize = self::DEFAULT_PAGE_SIZE)
    {
        $searchCriteriaBuilder = $this->searchCriteriaBuilder->setPageSize($pageSize)->addSortOrder($sortOrder);
        foreach ($filters as $filter) {
            //add as separate groups to get AND join instead of OR
            $searchCriteriaBuilder = $searchCriteriaBuilder->addFilters([$filter]);
        }
        return $searchCriteriaBuilder->create();
    }

    /**
     * Retrieves a store/visibility filtered product collection selecting only attributes necessary for the TurnTo Feed
     *
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function getProducts(\Magento\Store\Api\Data\StoreInterface $store)
    {
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect('id')
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('sku')
            ->addAttributeToSelect('url_path')
            ->addAttributeToSelect('url_key')
            ->addAttributeToSelect('url_in_store')
            ->addAttributeToSelect('image')
            ->addAttributeToSelect('quantity_and_stock_status')
            ->addAttributeToSelect('price')
            ->addAttributeToSelect('description');

        $gtinMap = $this->config->getGtinAttributesMap($store->getCode());

        if (!empty($gtinMap)) {
            foreach ($gtinMap as $attributeName) {
                $collection->addAttributeToSelect($attributeName);
            }
        }

        if (!$this->config->getUseChildSku($store->getId())) {
            $collection->addFieldToFilter(
                'visibility',
                [
                    'in' =>
                        [
                            \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH,
                            \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG
                        ]
                ]
            );
        }

        $collection->addStoreFilter($store);

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/turnt-query.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);

        $logger->info($collection->getSelect()->__toString());

        return $collection;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param $storeId
     * @return string
     */
    protected function getProductUrl(\Magento\Catalog\Model\Product $product, $storeId)
    {
        // Due to core bug, it is necessary to retrieve url using this method (see https://github.com/magento/magento2/issues/3074)
        $urlRewrite = $this->urlFinder->findOneByData(
            [
                \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::ENTITY_ID => $product->getId(),
                \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::ENTITY_TYPE =>
                    \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator::ENTITY_TYPE,
                \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::STORE_ID => $storeId
            ]
        );

        if (isset($urlRewrite)) {
            return $this->getAbsoluteUrl($urlRewrite->getRequestPath(), $storeId);
        } else {
            return $product->getProductUrl();
        }
    }

    /**
     * @param $relativeUrl
     * @param $storeId
     * @return string
     */
    protected function getAbsoluteUrl($relativeUrl, $storeId)
    {
        $storeUrl = $this->storeManager->getStore($storeId)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);
        return rtrim($storeUrl, '/') . '/' . ltrim($relativeUrl, '/');
    }
}
