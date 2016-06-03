<?php

namespace TurnTo\SocialCommerce\Model\Export;

/**
 * Class AbstractExport
 * @package TurnTo\SocialCommerce\Model\Export
 */
class AbstractExport
{
    /**
     * @var null|\TurnTo\SocialCommerce\Helper\Config
     */
    protected $config = null;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface|null
     */
    protected $storeManager = null;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory|null
     */
    protected $productCollectionFactory = null;

    /**
     * @var null|\Zend\Http\Client
     */
    protected $httpClient = null;

    /**
     * @var null|\TurnTo\SocialCommerce\Logger\Monolog
     */
    protected $logger = null;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTimeFactory|null
     */
    protected $datetimefactory = null;

    /**
     * @var \Magento\Catalog\Model\CategoryFactory|null
     */
    protected $categoryFactory = null;

    /**
     * @var \Magento\Catalog\Model\Category\Tree|null
     */
    protected $categoryTreeManager = null;

    /**
     * @var \Magento\Catalog\Helper\Product|null
     */
    protected $productHelper = null;

    /**
     * AbstractExport constructor.
     * @param \TurnTo\SocialCommerce\Helper\Config $config
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Zend\Http\Client $httpClient
     * @param \TurnTo\SocialCommerce\Logger\Monolog $logger
     * @param \Magento\Framework\Stdlib\DateTime\DateTimeFactory $dateTimeFactory
     * @param \Magento\Catalog\Model\CategoryFactory $catFactory
     * @param \Magento\Catalog\Model\Category\Tree $categoryTreeManager
     * @param \Magento\Catalog\Helper\Product $productHelper
     */
    public function __construct(\TurnTo\SocialCommerce\Helper\Config $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Zend\Http\Client $httpClient,
        \TurnTo\SocialCommerce\Logger\Monolog $logger,
        \Magento\Framework\Stdlib\DateTime\DateTimeFactory $dateTimeFactory,
        \Magento\Catalog\Model\CategoryFactory $catFactory,
        \Magento\Catalog\Model\Category\Tree $categoryTreeManager,
        \Magento\Catalog\Helper\Product $productHelper
    ) {
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->datetimefactory = $dateTimeFactory;
        $this->categoryFactory = $catFactory;
        $this->categoryTreeManager = $categoryTreeManager;
        $this->productHelper = $productHelper;
    }

    /**
     * Get store related url, if $urlInterface is null then baseUrl is returned
     * @param null|\Magento\Framework\UrlInterface::Type $urlInterface
     * @return mixed
     */
    protected function getStoreUrl($urlInterface = null)
    {
        return $this->storeManager->getStore()->getBaseUrl($urlInterface);
    }

    /**
     * Get store name
     * @return string
     */
    protected function getStoreName()
    {
        return $this->storeManager->getStore()->getName();
    }

    /**
     * Retrieves a product collection filtered for visibility == 4 and selecting only attributes necessary for TurnTo Feed
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    protected function getProducts()
    {
        $collection = $this->productCollection->create()
            ->addAttributeToSelect('id')
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('sku')
            ->addAttributeToSelect('url_in_store')
            ->addAttributeToSelect('image')
            ->addAttributeToSelect('quantity_and_stock_status')
            ->addAttributeToSelect('price')
            ->addAttributeToSelect('description');

        $gtinMap = $this->config->getGtinAttributesMap();
        if (!empty($gtinMap)) {
            foreach($gtinMap as $key => $attributeName) {
                $collection->addAttributeToSelect($attributeName);
            }
        }
        
        $collection->addFieldToFilter('visibility', \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH);
        
        return $collection;
    }
}
