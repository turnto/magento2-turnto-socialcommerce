<?php

namespace TurnTo\SocialCommerce\Model\Export;

class AbstractExport
{
    /**
     * @var null|\TurnTo\SocialCommerce\Helper\Config
     */
    protected $_config = null;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface|null
     */
    protected $_storeManager = null;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory|null
     */
    protected $_productCollection = null;

    /**
     * @var null|\Zend\Http\Client
     */
    protected $_httpClient = null;

    /**
     * @var \Magento\Framework\Logger\Monolog|null
     */
    protected $_logger = null;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTimeFactory|null
     */
    protected $_datetimefactory = null;

    /**
     * @var \Magento\Catalog\Model\CategoryFactory|null
     */
    protected $_categoryFactory = null;

    /**
     * AbstractExport constructor.
     * @param \TurnTo\SocialCommerce\Helper\Config $config
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollection
     * @param \Zend\Http\Client $httpClient
     * @param \Magento\Framework\Logger\Monolog $logger
     * @param \Magento\Framework\Stdlib\DateTime\DateTimeFactory $dtf
     * @param \Magento\Catalog\Model\CategoryFactory $catFactory
     */
    public function __construct(\TurnTo\SocialCommerce\Helper\Config $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollection,
        \Zend\Http\Client $httpClient,
        \Magento\Framework\Logger\Monolog $logger,
        \Magento\Framework\Stdlib\DateTime\DateTimeFactory $dtf,
        \Magento\Catalog\Model\CategoryFactory $catFactory
    ) {
        $this->_config = $config;
        $this->_storeManager = $storeManager;
        $this->_productCollection = $productCollection;
        $this->_httpClient = $httpClient;
        $this->_logger = $logger;
        $this->_datetimefactory = $dtf;
        $this->_categoryFactory = $catFactory;
    }

    /**
     * Get store related url, if $urlInterface is null then baseUrl is returned
     *  note: This is a convenience wrapper around StoreManager->getStore()->getBaseUrl($urlInterface)
     *  ex: $storeMediaUrl = getStoreUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA)
     *
     * @param null|\Magento\Framework\UrlInterface::Type $urlInterface
     *
     * @return mixed
     */
    protected function getStoreUrl($urlInterface = null) {
        return $this->_storeManager->getStore()->getBaseUrl($urlInterface);
    }

    /**
     * Get store name
     *  note: This is a convenience wrapper around StoreManager->getStore()->getName()
     *
     * @return string
     */
    protected function getStoreName() {
        return $this->_storeManager->getStore()->getName();
    }

    /**
     * Retrieves a product collection filtered for visibility == 4 and selecting only attributes necessary for TurnTo Feed
     *
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    protected function getProducts() {
        $collection = $this->_productCollection->create();
        $collection->addAttributeToSelect('id');
        $collection->addAttributeToSelect('name');
        $collection->addAttributeToSelect('sku');
        $collection->addAttributeToSelect('url_in_store');
        $collection->addAttributeToSelect('image');
        $collection->addAttributeToSelect('quantity_and_stock_status');
        $collection->addAttributeToSelect('price');

        $collection->addFieldToFilter('visibility', 4);
        
        return $collection;
    }
}
