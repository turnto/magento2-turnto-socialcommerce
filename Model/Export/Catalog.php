<?php

namespace TurnTo\SocialCommerce\Model\Export;

use TurnTo\SocialCommerce\Helper\Config;

/**
 * Class Catalog
 * @package TurnTo\SocialCommerce\Model\Export
 */
class Catalog extends AbstractExport
{
    /**
     * Feed Style used for the Product Feed
     */
    const FEED_STYLE = 'google-product.xml';

    /**
     * MIME Type used for the Product Feed
     */
    const FEED_MIME = 'application/atom+xml';

    /**
     * Response body from TurnTo servers on successful operation
     */
    const TURNTO_SUCCESS_RESPONSE = 'SUCCESS';

    /**
     * @var \Magento\Catalog\Helper\Product|null
     */
    protected $productHelper = null;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface|null
     */
    protected $storeManager = null;

    /**
     * Catalog constructor.
     * @param Config $config
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \TurnTo\SocialCommerce\Logger\Monolog $logger
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Framework\Intl\DateTimeFactory $dateTimeFactory
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Framework\Api\FilterBuilder $filterBuilder
     * @param \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder
     * @param \Magento\Catalog\Helper\Product $productHelper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \TurnTo\SocialCommerce\Helper\Config $config,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \TurnTo\SocialCommerce\Logger\Monolog $logger,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\Intl\DateTimeFactory $dateTimeFactory,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder,
        \Magento\Catalog\Helper\Product $productHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->productHelper = $productHelper;
        $this->storeManager = $storeManager;

        parent::__construct(
            $config, 
            $productCollectionFactory,
            $logger,
            $encryptor,
            $dateTimeFactory,
            $searchCriteriaBuilder,
            $filterBuilder,
            $sortOrderBuilder
        );
    }

    /**
     * Creates the product feed and pushes it to TurnTo
     */
    public function cronUploadFeed()
    {
        foreach ($this->storeManager->getStores() as $store) {
            if (
                $this->config->getIsEnabled($store->getCode())
                && $this->config->getIsProductFeedSubmissionEnabled($store->getCode())
            ) {
                $feed = $this->generateProductFeed($store);
                $this->transmitFeed($feed, $store);
            }
        }
    }

    /**
     * Transmits an XML feed to the TurnTo Product Feed endpoint set in the TurnTo configuration
     *
     * @param \SimpleXMLElement $feed
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @throws \Exception
     */
    protected function transmitFeed(\SimpleXMLElement $feed, \Magento\Store\Api\Data\StoreInterface $store)
    {
        $response = null;

        try {
            $zendClient = new \Magento\Framework\HTTP\ZendClient;
            $zendClient
                ->setUri($this->config
                    ->getFeedUploadAddress($store->getCode()))
                ->setMethod(\Zend_Http_Client::POST)
                ->setParameterPost(
                    [
                        'siteKey' => $this->config
                            ->getSiteKey($store->getCode()),
                        'authKey' => $this->encryptor->decrypt($this->config
                            ->getAuthorizationKey($store->getCode())),
                        'feedStyle' => self::FEED_STYLE
                    ]
                )
                ->setFileUpload(self::FEED_STYLE, 'file', $feed->asXML(), self::FEED_MIME);

            $response = $zendClient->request();

            if (!$response || !$response->isSuccessful()) {
                throw new \Exception('TurnTo catalog feed submission failed silently');
            }

            $body = $response->getBody();

            //It is possible to get a status 200 message who's body is an error message from TurnTo
            if (empty($body) || $body != self::TURNTO_SUCCESS_RESPONSE) {
                throw new \Exception("TurnTo catalog feed submission failed with message: $body" );
            }
        } catch (\Exception $e) {
            $this->logger->error('An error occurred while transmitting the catalog feed to TurnTo',
                [
                    'exception' => $e,
                    'response' => $response ? $response->getBody() : 'null'
                ]
            );
            throw $e;
        }
    }

    /**
     * Escapes special characters for xml use
     * @param string $dirtyString
     * @return string
     */
    protected function sanitizeData($dirtyString)
    {
        $replacementMap = [
            '&' => '&amp;',
            '"' => '&quot;',
            '\'' => '&apos;',
            '<' => '&lt;',
            '>' => '&gt;',
        ];

        return str_replace(array_keys($replacementMap), array_values($replacementMap), $dirtyString);
    }

    /**
     * Writes all individually visible products to an ATOM 1.0 feed which is returned in a SimpleXMLElement Object
     *
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @return null|\SimpleXMLElement
     * @throws \Exception If feed could not be generated
     */
    protected function generateProductFeed(\Magento\Store\Api\Data\StoreInterface $store)
    {
        $feed = null;
        $progressCounter = 0;

        try {
            $products = $this->getProducts($store);

            $feed = new \SimpleXMLElement(
                '<?xml version="1.0" encoding="UTF-8"?>'
                    . '<feed xmlns="http://www.w3.org/2005/Atom"'
                    . ' xmlns:g="http://base.google.com/ns/1.0" xml:lang="en-US" />'
            );

            $feed->addChild('title', $this->sanitizeData($store->getName() . ' - Google Product Atom 1.0 Feed'));
            $feed->addChild(
                'link',
                $this->sanitizeData($store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK))
            );
            $feed->addChild(
                'updated',
                $this->dateTimeFactory->create('now', new \DateTimeZone('UTC'))->format(DATE_ATOM)
            );
            $feed->addChild('author')->addChild('name', 'TurnTo');
            $feed->addChild(
                'id',
                $this->sanitizeData($store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB))
            );

            foreach ($products as $product) {
                try {
                    $this->addProductToAtomFeed($feed->addChild('entry'), $product, $store);
                } catch (\Exception $entryException) {
                    $this->logger->error('Product failed to be added to feed',
                        [
                            'exception' => $entryException,
                            'productSKU' => $product->getSku()
                        ]
                    );
                } finally {
                    $progressCounter++;
                }
            }
        } catch (\Exception $feedException) {
            if ($feed) {
                $this->logger
                    ->error('An exception occurred while creating the catalog feed',
                        [
                            'exception' => $feedException,
                            'productCount' => count($products),
                            'productsProcessed' => $progressCounter
                        ]
                    );
            } else if ($products) {
                $this->logger
                    ->error('An exception occurred that prevented the creation of the catalog feed',
                        [
                            'exception' => $feedException,
                            'productCount' => count($products),
                            'productsProcessed' => $progressCounter
                        ]
                    );
            } else {
                $this->logger
                    ->error('An exception occured while retrieving the products for the catalog feed',
                        [
                            'exception' => $feedException,
                            'productsProcessed' => $progressCounter
                        ]
                    );
            }
            throw $feedException;
        }
        
        return $feed;
    }

    /**
     * Adds a Magento catalog product to a Google Products ATOM 1.0 xml feed
     *
     * @param $entry
     * @param $product
     * @param $store
     * @throws \Exception
     */
    protected function addProductToAtomFeed($entry, $product, $store)
    {
        if (empty($product)) {
            throw new \Exception('Product can not be null or empty');
        }

        $sku = $product->getSku();
        if (empty($sku)) {
            throw new \Exception('Product must have a valid sku');
        }

        $productUrl = $product->getUrlInStore();
        if (empty($productUrl)) {
            throw new \Exception('Product must have a valid store-product url');
        }

        $productName = $product->getName();
        if (empty($productName)) {
            throw new \Exception('Product must have a valid name');
        }

        $entry->addChild('id', $this->sanitizeData($sku));

        $gtin = null;
        $mpn = null;
        $brand = null;
        $identifierExists = 'FALSE';
        $gtinMap = $this->config
            ->getGtinAttributesMap($store->getCode());

        if (!empty($gtinMap)) {

            if (isset($gtinMap[Config::MPN_ATTRIBUTE])) {
                $mpn = $product->getResource()
                    ->getAttribute($gtinMap[Config::MPN_ATTRIBUTE])
                    ->getFrontend()->getValue($product);
            }
            if (isset($gtinMap[Config::BRAND_ATTRIBUTE])) {
                $brand = $product->getResource()
                    ->getAttribute($gtinMap[\TurnTo\SocialCommerce\Helper\Config::BRAND_ATTRIBUTE])
                    ->getFrontend()->getValue($product);
            }
            if (empty($gtin) && isset($gtinMap[Config::UPC_ATTRIBUTE])) {
                $gtin = $product->getResource()
                    ->getAttribute($gtinMap[Config::UPC_ATTRIBUTE])
                    ->getFrontend()->getValue($product);
            }
            if (empty($gtin) && isset($gtinMap[Config::EAN_ATTRIBUTE])) {
                $gtin = $product->getResource()
                    ->getAttribute($gtinMap[Config::EAN_ATTRIBUTE])
                    ->getFrontend()->getValue($product);
            }
            if (empty($gtin) && isset($gtinMap[Config::JAN_ATTRIBUTE])) {
                $gtin = $product->getResource()
                    ->getAttribute($gtinMap[Config::JAN_ATTRIBUTE])
                    ->getFrontend()->getValue($product);
            }
            if (empty($gtin) && isset($gtinMap[Config::ISBN_ATTRIBUTE])) {
                $gtin = $product->getResource()
                    ->getAttribute($gtinMap[Config::ISBN_ATTRIBUTE])
                    ->getFrontend()->getValue($product);
            }
            if (!empty($gtin)) {
                $entry->addChild('g:gtin', $this->sanitizeData($gtin));
            }
            if(!empty($brand)) {
                $entry->addChild('g:brand', $this->sanitizeData($brand));
            }
            if (!empty($mpn)) {
                $entry->addChild('g:mpn', $this->sanitizeData($mpn));
            }
            if (!empty($brand) && (!empty($gtin) || !empty($mpn))) {
                $identifierExists = 'TRUE';
            }
        }

        $entry->addChild('g:identifier_exists', $identifierExists);
        $entry->addChild('g:link', $this->sanitizeData($productUrl));
        $entry->addChild('g:title', $this->sanitizeData($productName));

        $categoryName = $this->getCategoryTreeString($product);
        if (!empty($categoryName)) {
            $cleanCategoryName = $this->sanitizeData($categoryName);
            $entry->addChild('g:google_product_category', $cleanCategoryName);
            $entry->addChild('g:product_type', $cleanCategoryName);
        }

        $entry->addChild('g:image_link', $this->sanitizeData($this->productHelper->getImageUrl($product)));
        $entry->addChild('g:condition', 'new');
        $entry->addChild('g:availability', $product->getQuantityAndStockStatus() == 1 ? 'in stock' : 'out of stock');
        $entry->addChild('g:price', $product->getPrice() . ' ' . $store->getBaseCurrencyCode());
        $entry->addChild('g:description', $this->sanitizeData($product->getDescription()));
    }

    /**
     * Gets the deepest tree for given product and returns as "rootNodeName > branchNodeName > leafNodeName"
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return string
     */
    protected function getCategoryTreeString(\Magento\Catalog\Model\Product $product)
    {
        $categoryName = '';
        $categories = $product->getCategoryCollection();
        $deepestLength = 0;
        $deepestTree = [];

        foreach ($categories as $category) {
            $tempTree = $this->getCategoryBranch($category);
            $treeLength = count($tempTree);
            if ($treeLength > $deepestLength) {
                $deepestLength = $treeLength;
                $deepestTree = $tempTree;
            }
        }

        foreach (array_reverse($deepestTree) as $node) {
            $nodeName = $node->getName();
            if (!empty($nodeName)) {
                if (!empty($categoryName)) {
                    $categoryName .= ' > ';
                }
                $categoryName .= $node->getName();
            }
        }

        return $categoryName;
    }

    /**
     * Recursively walks category chain from leaf to root while writing the traversed branch to an array
     *
     * @param \Magento\Catalog\Model\Category $category
     * @param array $categoryBranch
     * @return array
     */
    protected function getCategoryBranch(\Magento\Catalog\Model\Category $category, array $categoryBranch = [])
    {
        try {
            $parent = $category->getParentCategory();
        } catch (\NoSuchEntityException $isRootEntity) {
            $parent = null;
        } finally {
            $categoryBranch[] = $category;
            if (isset($parent)) {
                return $this->getCategoryBranch($parent, $categoryBranch);
            } else {
                return $categoryBranch;
            }
        }
    }
}

