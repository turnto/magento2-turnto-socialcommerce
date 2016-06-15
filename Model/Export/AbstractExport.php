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
     * @var \Magento\Framework\Encryption\EncryptorInterface|null
     */
    protected $encryptor = null;

    /**
     * AbstractExport constructor.
     * 
     * @param \TurnTo\SocialCommerce\Helper\Config $config
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Zend\Http\Client $httpClient
     * @param \TurnTo\SocialCommerce\Logger\Monolog $logger
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     */
    public function __construct(
        \TurnTo\SocialCommerce\Helper\Config $config,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Zend\Http\Client $httpClient,
        \TurnTo\SocialCommerce\Logger\Monolog $logger,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor
    ) {
        $this->config = $config;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->encryptor = $encryptor;
    }

    /**
     * Retrieves a store/visibility filtered product collection selecting only attributes necessary for the TurnTo Feed
     *
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    protected function getProducts(\Magento\Store\Api\Data\StoreInterface $store)
    {
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect('id')
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('sku')
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
        
        $collection->addFieldToFilter('visibility',
            [
                'in' =>
                [
                    \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH,
                    \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG
                ]
            ]
        );

        $collection->addStoreFilter($store);
        
        return $collection;
    }
}
