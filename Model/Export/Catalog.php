<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Export;

use Exception;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Filesystem;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use SimpleXMLElement;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Helper\Config;
use TurnTo\SocialCommerce\Helper\Product as ProductHelper;
use TurnTo\SocialCommerce\Logger\Monolog;

/**
 * Class Catalog
 * @package TurnTo\SocialCommerce\Model\Export
 */
class Catalog
{
    /**
     * Feed Style used for the Product Feed
     */
    const FEED_STYLE = 'google-product.xml';

    /**
     * @var Image $imageHelper
     */
    protected $imageHelper = null;

    /**
     * @var StockRegistryProviderInterface
     */
    protected $stockRegistryProvider;

    /**
     * @var ProductHelper
     */
    protected $productHelper;

    /**
     * Used to generate file name (x_of_totalPages_feed.xml)
     * @var Int
     */
    protected $totalPages;
    /**
     * @var Filesystem
     */
    protected $filesystem;
    /**
     * @var StoreManagerInterface|null
     */
    protected $storeManager;
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var FeedClient
     */
    protected $feedClient;
    /**
     * @var Monolog
     */
    protected $logger;
    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;
    /**
     * @var DateTimeFactory
     */
    protected $dateTimeFactory;
    /**
     * @var Product
     */
    protected $product;

    /**
     * Catalog constructor.
     *
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param CollectionFactory $productCollectionFactory
     * @param DateTimeFactory $dateTimeFactory
     * @param Image $imageHelper
     * @param ProductHelper $productHelper
     * @param Product $product
     * @param FeedClient $feedClient
     * @param Filesystem $filesystem
     * @param Monolog $logger
     */
    public function __construct(
        Config                $config,
        StoreManagerInterface $storeManager,
        CollectionFactory     $productCollectionFactory,
        DateTimeFactory       $dateTimeFactory,
        Image                 $imageHelper,
        ProductHelper         $productHelper,
        Product               $product,
        FeedClient                $feedClient,
        Filesystem            $filesystem,
        Monolog               $logger
    ) {
        $this->config = $config;
        $this->imageHelper = $imageHelper;
        $this->storeManager = $storeManager;
        $this->productHelper = $productHelper;
        $this->product = $product;
        $this->feedClient = $feedClient;
        $this->filesystem = $filesystem;
        $this->logger = $logger;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->dateTimeFactory = $dateTimeFactory;
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
     * @param StoreInterface $store
     * @param $page
     * @return bool|SimpleXMLElement
     * @throws Exception If feed could not be generated
     */
    protected function generateProductFeed(StoreInterface $store, $page)
    {
        $feed = null;
        $progressCounter = 0;
        $products = [];

        try {
            if (!$products = $this->getProducts($store, $page)) {
                return false;
            }
            $feed = new SimpleXMLElement(
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
                } catch (Exception $entryException) {
                    $this->logger->error(
                        'Product failed to be added to feed',
                        [
                            'exception' => $entryException,
                            'productSKU' => $product->getSku()
                        ]
                    );
                }
                $progressCounter++;
            }

            return $feed;
        } catch (Exception $e) {
            if ($feed) {
                $this->logger->error(
                    'An exception occurred while generating the catalog feed',
                    [
                        'exception' => $e,
                        'productCount' => count($products),
                        'productsProcessed' => $progressCounter
                    ]
                );
                throw $e;
            }
            if ($products) {
                $this->logger->error(
                    'An exception occurred that prevented the creation of the catalog feed due to invalid product data.',
                    [
                        'exception' => $e,
                        'productCount' => count($products),
                        'productsProcessed' => $progressCounter
                    ]
                );
                throw $e;
            }
            $this->logger->error(
                'An exception occurred while retrieving the products for the catalog feed',
                [
                    'exception' => $e,
                    'productsProcessed' => $progressCounter
                ]
            );

            throw $e;
        }
    }

    /**
     * Adds a Magento catalog product to a Google Products ATOM 1.0 xml feed
     *
     * @param SimpleXMLElement              $entry
     * @param \Magento\Catalog\Model\Product $product
     * @param                                $store
     * @param                                $parent bool|\Magento\Catalog\Model\Product
     *
     * @throws Exception
     */
    protected function addProductToAtomFeed($entry, $product, $store, $parent)
    {
        if (empty($product)) {
            throw new Exception('Product can not be null or empty');
        }

        $sku = $this->productHelper->turnToSafeEncoding($product->getSku());
        if (empty($sku)) {
            throw new Exception('Product must have a valid sku');
        }

        $productUrl = $parent ? $parent->getProductUrl() : $product->getProductUrl();
        if (empty($productUrl)) {
            throw new Exception('Product must have a valid store-product url');
        }
        // Replace spaces with dashes, so we always have a valid URL
        $productUrl = str_replace(" ", "-", $productUrl);

        $productName = $product->getName();
        if (empty($productName)) {
            throw new Exception('Product must have a valid name');
        }
        $productName = str_replace("\n", "", $productName);

        $entry->addChild('id', $this->sanitizeData($sku));

        $gtin = null;
        $mpn = null;
        $brand = null;
        $identifierExists = 'FALSE';
        $gtinMap = $this->config->getGtinAttributesMap($store->getCode());

        if (!empty($gtinMap)) {
            if (isset($gtinMap[Config::MPN_ATTRIBUTE])) {
                $mpn = $product->getData($gtinMap[Config::MPN_ATTRIBUTE]);
            }
            if (isset($gtinMap[Config::BRAND_ATTRIBUTE])) {
                $brand = $product->getData($gtinMap[Config::BRAND_ATTRIBUTE]);
            }
            if (empty($gtin) && isset($gtinMap[Config::UPC_ATTRIBUTE])) {
                $gtin = $product->getData($gtinMap[Config::UPC_ATTRIBUTE]);
            }
            if (empty($gtin) && isset($gtinMap[Config::ISBN_ATTRIBUTE])) {
                $gtin = $product->getData($gtinMap[Config::ISBN_ATTRIBUTE]);
            }
            if (empty($gtin) && isset($gtinMap[Config::EAN_ATTRIBUTE])) {
                $gtin = $product->getData($gtinMap[Config::EAN_ATTRIBUTE]);
            }
            if (empty($gtin) && isset($gtinMap[Config::JAN_ATTRIBUTE])) {
                $gtin = $product->getData($gtinMap[Config::JAN_ATTRIBUTE]);
            }
            if (empty($gtin) && isset($gtinMap[Config::ASIN_ATTRIBUTE])) {
                $gtin = $product->getData($gtinMap[Config::ASIN_ATTRIBUTE]);
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
        $entry->addChild('g:link', $productUrl);
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

        // Check if the product has an image. If it does NOT, we don't send an image URL, so TurnTo doesn't
        // just process the placeholder image
        $productHasImage = (bool) $product->getData('image');
        if (!$productHasImage) {
            $productImageUrl = '';
        } else {
            $productImageUrl = $this->imageHelper->init($product, 'product_page_main_image')->setImageFile(
                $product->getImage()
            )->getUrl();
            $productImageUrl = str_replace(" ", "-", $productImageUrl);
        }

        // Restore the "current store"
        $this->storeManager->setCurrentStore($currentStore);

        // Availability is normally determined by status, but can be overridden by custom "turnto_disabled" attribute
        $turntoDisable = $product->getCustomAttribute('turnto_disabled') ?
            $product->getCustomAttribute('turnto_disabled')->getValue() :
            false;
        $availability = $turntoDisable ? 'out of stock' :
            (($product->getStatus() == 1) ? 'in stock' : 'out of stock');

        $entry->addChild('g:availability', $availability);
        $entry->addChild('g:image_link', $this->sanitizeData($productImageUrl));
        $entry->addChild('g:condition', 'new');
        $entry->addChild('g:price', $product->getPrice() . ' ' . $store->getBaseCurrencyCode());
        $itemGroupId = $this->getItemGroupId($product, $parent);
        $entry->addChild('g:item_group_id', $itemGroupId);
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
     * @return void
     * @throws Exception
     */
    public function cronUploadFeed()
    {
        foreach ($this->storeManager->getStores() as $store) {
            if (
                $this->config->getIsEnabled($store->getCode()) &&
                $this->config->getIsProductFeedSubmissionEnabled($store->getCode())
            ) {
                $page = 1;
                while ($feed = $this->generateProductFeed($store, $page)) {
                    try {
                        $fileName = sprintf('%s_of_%s_store_%s_%s', $page, $this->totalPages, $store->getId(), self::FEED_STYLE);
                        $this->feedClient->transmitFeedFile($feed, $fileName, self::FEED_STYLE, $store->getCode());
                    } catch (Exception $e) {
                        $this->logger->error(
                            "TurnTo catalog export error sending page $page.",
                            [
                                'exception' => $e
                            ]
                        );
                    }
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
     * @return string
     */
    public function getItemGroupId($product, $parent)
    {
        if ($parent) {
            return $parent->getSku();
        } else {
            return $product->getSku();
        }
    }

    /**
     * Retrieves a store/visibility filtered product collection selecting only attributes necessary for the TurnTo Feed
     * overwritten to allow for pagination
     *
     * @param StoreInterface $store
     * @param int $page
     * @param ?int $pageCount
     * @return Collection|false
     */
    public function getProducts(StoreInterface $store, $page, $pageCount = 10000)
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
            ->addAttributeToSelect('turnto_disabled')
            ->addUrlRewrite()
            ->setPage($page, $pageCount);

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
                    'in' => [
                        Visibility::VISIBILITY_BOTH,
                        Visibility::VISIBILITY_IN_CATALOG
                    ]
                ]
            );
        }

        $collection->addStoreFilter($store);

        //used to generate file name 1_of_$totalPages.xml
        $this->totalPages = $collection->getLastPageNumber();

        //stop the feed once we get to the last page
        if ($this->totalPages < $page) {
            return false;
        }

        return $collection;
    }
}
