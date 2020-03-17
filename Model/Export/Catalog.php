<?php
/**
 * TurnTo_SocialCommerce
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright  Copyright (c) 2018 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Model\Export;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use TurnTo\SocialCommerce\Helper\Config;
use TurnTo\SocialCommerce\Helper\Product;

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
     * @var \Magento\Catalog\Helper\Image $imageHelper
     */
    protected $imageHelper = null;

    /**
     * @var \Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface
     */
    protected $stockRegistryProvider;

    /**
     * @var Product
     */
    protected $productHelper;

    /**
     * Used to generate file name (x_of_totalPages_feed.xml)
     * @var Int
     */
    protected $totalPages;

    /**
     * Catalog constructor.
     *
     * @param Config                                                             $config
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory     $productCollectionFactory
     * @param \TurnTo\SocialCommerce\Logger\Monolog                              $logger
     * @param \Magento\Framework\Intl\DateTimeFactory                            $dateTimeFactory
     * @param \Magento\Framework\Api\SearchCriteriaBuilder                       $searchCriteriaBuilder
     * @param \Magento\Framework\Api\FilterBuilder                               $filterBuilder
     * @param \Magento\Framework\Api\SortOrderBuilder                            $sortOrderBuilder
     * @param \Magento\UrlRewrite\Model\UrlFinderInterface                       $urlFinder
     * @param \Magento\Store\Model\StoreManagerInterface                         $storeManager
     * @param \Magento\Catalog\Helper\Image                                      $imageHelper
     * @param \Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface $stockRegistryProvider
     * @param Product                                                            $productHelper
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
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface $stockRegistryProvider,
        Product $productHelper
    )
    {
        parent::__construct(
            $config,
            $productCollectionFactory,
            $logger,
            $dateTimeFactory,
            $searchCriteriaBuilder,
            $filterBuilder,
            $sortOrderBuilder,
            $urlFinder,
            $storeManager
        );

        $this->imageHelper = $imageHelper;
        $this->storeManager = $storeManager;
        $this->stockRegistryProvider = $stockRegistryProvider;
        $this->productHelper = $productHelper;
    }

    /**
     * Transmits an XML feed to the TurnTo Product Feed endpoint set in the TurnTo configuration
     *
     * @param \SimpleXMLElement                      $feed
     * @param \Magento\Store\Api\Data\StoreInterface $store
     *
     * @throws \Exception
     */
    protected function transmitFeed(\SimpleXMLElement $feed, \Magento\Store\Api\Data\StoreInterface $store, $page = null)
    {
        $response = null;

        try {
            $zendClient = new \Magento\Framework\HTTP\ZendClient;
            $zendClient->setUri(
                $this->config->getFeedUploadAddress($store->getCode())
            )->setMethod(\Zend_Http_Client::POST)->setParameterPost(
                [
                    'siteKey' => $this->config->getSiteKey($store->getCode()),
                    'authKey' => $this->config->getAuthorizationKey($store->getCode()),
                    'feedStyle' => self::FEED_STYLE
                ]
            )->setFileUpload($page.'_of_'.$this->totalPages.'_store_'. $store->getId() .'_' . self::FEED_STYLE, 'file', $feed->asXML(), self::FEED_MIME);

            \file_put_contents(BP . "/var/google-product_storecode_{$store->getCode()}.xml", $feed->asXML());
            $response = $zendClient->request();

            if (!$response || !$response->isSuccessful()) {
                throw new \Exception('TurnTo catalog feed submission failed silently');
            }

            $body = $response->getBody();
            \file_put_contents(BP . "/var/google-product_storecode_{$store->getCode()}_request.xml", $body);

            //It is possible to get a status 200 message who's body is an error message from TurnTo
            if (empty($body) || $body != self::TURNTO_SUCCESS_RESPONSE) {
                throw new \Exception("TurnTo catalog feed submission failed with message: $body");
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'An error occurred while transmitting the catalog feed to TurnTo',
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
     *
     * @param string $dirtyString
     *
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
     *
     * @return bool|\SimpleXMLElement
     * @throws \Exception If feed could not be generated
     */
    protected function generateProductFeed(\Magento\Store\Api\Data\StoreInterface $store, $page)
    {
        $feed = null;
        $progressCounter = 0;
        $products = [];

        try {


            $feed = new \SimpleXMLElement(
                '<?xml version="1.0" encoding="UTF-8"?>' . '<feed xmlns="http://www.w3.org/2005/Atom"' . ' xmlns:g="http://base.google.com/ns/1.0" xml:lang="en-US" />'
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

            if ($products = $this->getProducts($store,$page,10000)){

                $childProducts = [];

                // TurnTo requires a product feed where children of configurable products are aware of their parent SKUs
                // and include that parent SKU in the feed. This code is not very performant and therefore the feed will
                // take longer to generate in large catalogs with many configurable products. However in the interest of
                // development time, this simpler approach is being taken and if it proves to not scale well, can be
                // refactored in the future to use a query that loads all child products for all configurable products
                // at one time.
                if ($this->config->getUseChildSku($store->getId())) {
                    foreach ($products as $product) {
                        if ($product->getTypeId() !== Configurable::TYPE_CODE) {
                            continue;
                        }

                        $children = $product->getTypeInstance()->getUsedProducts($product);
                        foreach ($children as $child) {
                            $childProducts[$child->getSku()] = $product;
                        }
                    }
                }

                foreach ($products as $product) {
                    $parent = false;
                    if ($this->config->getUseChildSku($store->getId()) && isset($childProducts[$product->getSku()])) {
                        $parent = $childProducts[$product->getSku()];
                    }
                    try {
                        $this->addProductToAtomFeed($feed->addChild('entry'), $product, $store, $parent);
                    } catch (\Exception $entryException) {
                        $this->logger->error(
                            'Product failed to be added to feed',
                            [
                                'exception' => $entryException,
                                'productSKU' => $product->getSku()
                            ]
                        );
                    } finally {
                        $progressCounter++;
                    }
                }
                return $feed;
            }else{
                //no more products to submit
                return false;
            }


        } catch (\Exception $feedException) {
            if ($feed) {
                $this->logger->error(
                    'An exception occurred while creating the catalog feed',
                    [
                        'exception' => $feedException,
                        'productCount' => count($products),
                        'productsProcessed' => $progressCounter
                    ]
                );
            } else if ($products) {
                $this->logger->error(
                    'An exception occurred that prevented the creation of the catalog feed',
                    [
                        'exception' => $feedException,
                        'productCount' => count($products),
                        'productsProcessed' => $progressCounter
                    ]
                );
            } else {
                $this->logger->error(
                    'An exception occured while retrieving the products for the catalog feed',
                    [
                        'exception' => $feedException,
                        'productsProcessed' => $progressCounter
                    ]
                );
            }
            throw $feedException;
        }


        return false;
    }

    /**
     * Adds a Magento catalog product to a Google Products ATOM 1.0 xml feed
     *
     * @param \SimpleXMLElement              $entry
     * @param \Magento\Catalog\Model\Product $product
     * @param                                $store
     * @param                                $parent bool|\Magento\Catalog\Model\Product
     *
     * @throws \Exception
     */
    protected function addProductToAtomFeed($entry, $product, $store, $parent)
    {
        if (empty($product)) {
            throw new \Exception('Product can not be null or empty');
        }

        $sku = $this->productHelper->turnToSafeEncoding($product->getSku());
        if (empty($sku)) {
            throw new \Exception('Product must have a valid sku');
        }

        $productUrl = $this->getProductUrl($parent ?: $product, $store->getId());
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
        $gtinMap = $this->config->getGtinAttributesMap($store->getCode());

        if (!empty($gtinMap)) {
            if (isset($gtinMap[Config::MPN_ATTRIBUTE])) {
                $mpn = $product->getResource()->getAttribute($gtinMap[Config::MPN_ATTRIBUTE])->getFrontend()->getValue(
                    $product
                );
            }
            if (isset($gtinMap[Config::BRAND_ATTRIBUTE])) {
                $brand = $product->getResource()->getAttribute(
                    $gtinMap[\TurnTo\SocialCommerce\Helper\Config::BRAND_ATTRIBUTE]
                )->getFrontend()->getValue($product);
            }
            if (empty($gtin) && isset($gtinMap[Config::UPC_ATTRIBUTE])) {
                $gtin = $product->getResource()->getAttribute($gtinMap[Config::UPC_ATTRIBUTE])->getFrontend()->getValue(
                    $product
                );
            }
            if (empty($gtin) && isset($gtinMap[Config::ISBN_ATTRIBUTE])) {
                $gtin = $product->getResource()
                    ->getAttribute($gtinMap[Config::ISBN_ATTRIBUTE])
                    ->getFrontend()
                    ->getValue($product);
            }
            if (empty($gtin) && isset($gtinMap[Config::EAN_ATTRIBUTE])) {
                $gtin = $product->getResource()->getAttribute($gtinMap[Config::EAN_ATTRIBUTE])->getFrontend()->getValue(
                    $product
                );
            }
            if (empty($gtin) && isset($gtinMap[Config::JAN_ATTRIBUTE])) {
                $gtin = $product->getResource()->getAttribute($gtinMap[Config::JAN_ATTRIBUTE])->getFrontend()->getValue(
                    $product
                );
            }
            if (empty($gtin) && isset($gtinMap[Config::ASIN_ATTRIBUTE])) {
                $gtin = $product->getResource()
                    ->getAttribute($gtinMap[Config::ASIN_ATTRIBUTE])
                    ->getFrontend()
                    ->getValue($product);
            }
            if (!empty($gtin)) {
                $entry->addChild('g:gtin', $this->sanitizeData($gtin));
            }
            if (!empty($brand)) {
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

        // In order for the product image url to use the url from the proper store view, temporarily change the store
        $currentStore = $this->storeManager->getStore();
        $this->storeManager->setCurrentStore($store->getStoreId());

        $productImageUrl = $this->imageHelper->init($product, 'product_page_main_image')->setImageFile(
            $product->getImage()
        )->getUrl();

        // Restore the "current store"
        $this->storeManager->setCurrentStore($currentStore);


        $entry->addChild('g:availability', ($product->getStatus() == 1) ? 'in stock' : 'out of stock');
        $entry->addChild('g:image_link', $this->sanitizeData($productImageUrl));
        $entry->addChild('g:condition', 'new');
        $entry->addChild('g:price', $product->getPrice() . ' ' . $store->getBaseCurrencyCode());
        $itemGroupId = $this->getItemGroupId($product, $parent);
        if ($parent) {
            $entry->addChild('g:item_group_id', $itemGroupId);
        }
    }

    /**
     * Gets the deepest tree for given product and returns as "rootNodeName > branchNodeName > leafNodeName"
     *
     * @param \Magento\Catalog\Model\Product $product
     *
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
     * @param array                           $categoryBranch
     *
     * @return array
     */
    protected function getCategoryBranch(\Magento\Catalog\Model\Category $category, array $categoryBranch = [])
    {
        try {
            $parent = $category->getParentCategory();
        } catch (\Magento\Framework\Exception\NoSuchEntityException $isRootEntity) {
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

    /**
     * Creates the product feed and pushes it to TurnTo
     */
    public function cronUploadFeed()
    {
        foreach ($this->storeManager->getStores() as $store) {
            if ($this->config->getIsEnabled($store->getCode()) && $this->config->getIsProductFeedSubmissionEnabled(
                    $store->getCode()
                )) {

               $page =1;
               while($feed=$this->generateProductFeed($store,$page)){
                   $this->transmitFeed($feed, $store,$page);
                   $page++;

               }
            }
        }
    }

    /**
     * Get item group ID for a given product
     *
     * @param \Magento\Catalog\Model\Product      $product
     * @param \Magento\Catalog\Model\Product|bool $parent
     *
     * @return string|bool
     */
    public function getItemGroupId($product, $parent)
    {
        if ($parent) {
            return $parent->getSku();
        }

        return false;
    }


    /**
     * Retrieves a store/visibility filtered product collection selecting only attributes necessary for the TurnTo Feed
     * over written to allow for pagination
     *
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */

    public function getProducts(\Magento\Store\Api\Data\StoreInterface $store, $page = null, $pageCount = 10000)
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
            ->addAttributeToSelect('status')
            ->setPage($page,$pageCount);


        //used to generate file name 1_of_$totalPages.xml
        $this->totalPages = $collection->getLastPageNumber();

        //stop the feed once we get to the last page
        if($this->totalPages < $page){
            return false;
        }

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

        return $collection;
    }


}
