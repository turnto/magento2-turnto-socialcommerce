<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Export;

use DateTimeZone;
use Exception;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Framework\UrlInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use SimpleXMLElement;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Config\Gtin;
use TurnTo\SocialCommerce\Model\Product;
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
     * @var Product
     */
    protected $product;

    /**
     * Used to generate file name (x_of_totalPages_feed.xml)
     * @var Int
     */
    protected $totalPages;
    /**
     * @var StoreManagerInterface
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
     * @var Gtin
     */
    protected $gtinConfig;
    /**
     * @var EavConfig
     */
    protected $eavConfig;

    /**
     * Catalog constructor.
     *
     * @param Config $config
     * @param Gtin $gtinConfig
     * @param StoreManagerInterface $storeManager
     * @param CollectionFactory $productCollectionFactory
     * @param DateTimeFactory $dateTimeFactory
     * @param Image $imageHelper
     * @param Product $product
     * @param EavConfig $eavConfig
     * @param FeedClient $feedClient
     * @param Monolog $logger
     */
    public function __construct(
        Config                $config,
        Gtin                  $gtinConfig,
        StoreManagerInterface $storeManager,
        CollectionFactory     $productCollectionFactory,
        DateTimeFactory       $dateTimeFactory,
        Image                 $imageHelper,
        Product               $product,
        EavConfig             $eavConfig,
        FeedClient            $feedClient,
        Monolog               $logger
    ) {
        $this->config = $config;
        $this->gtinConfig = $gtinConfig;
        $this->imageHelper = $imageHelper;
        $this->storeManager = $storeManager;
        $this->product = $product;
        $this->eavConfig = $eavConfig;
        $this->feedClient = $feedClient;
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
        return htmlspecialchars($dirtyString, ENT_XML1 | ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
    }

	/**
	 * Resolves a product attribute value, returning the display label for
	 * EAV select/multiselect attributes instead of the raw option ID.
	 *
	 * @param CatalogProduct $product
	 * @param string $attributeCode
	 * @return string|null
	 */
    protected function getProductAttributeValue(CatalogProduct $product, string $attributeCode)
    {
        if (empty($attributeCode)) {
            return null;
        }

        try {
            $attribute = $this->eavConfig->getAttribute(ProductAttributeInterface::ENTITY_TYPE_CODE, $attributeCode);
            if ($attribute && $attribute->usesSource()) {
                $label = $product->getAttributeText($attributeCode);
                if (is_array($label)) {
                    return implode(', ', $label);
                }
                return $label;
            }
        } catch (Exception $e) {
            $this->logger->error(
                'Error retrieving product attribute',
                [
                    'exception' => $e,
                    'attributeCode' => $attributeCode,
                    'productId' => $product->getId()
                ]
            );
        }

        $value = $product->getData($attributeCode);
        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }
        return !empty($value) ? (string)$value : null;
    }

    /**
     * @param $product
     * @param $gtinMap
     * @return string|null
     */
    public function getGtinValue($product, $gtinMap)
    {
        if (isset($gtinMap[Gtin::UPC_ATTRIBUTE])) {
             return $this->getProductAttributeValue($product, $gtinMap[Gtin::UPC_ATTRIBUTE]);
        }
        if (isset($gtinMap[Gtin::ISBN_ATTRIBUTE])) {
            return$this->getProductAttributeValue($product, $gtinMap[Gtin::ISBN_ATTRIBUTE]);
        }
        if (isset($gtinMap[Gtin::EAN_ATTRIBUTE])) {
            return$this->getProductAttributeValue($product, $gtinMap[Gtin::EAN_ATTRIBUTE]);
        }
        if (isset($gtinMap[Gtin::JAN_ATTRIBUTE])) {
            return$this->getProductAttributeValue($product, $gtinMap[Gtin::JAN_ATTRIBUTE]);
        }
        if (isset($gtinMap[Gtin::ASIN_ATTRIBUTE])) {
            return$this->getProductAttributeValue($product, $gtinMap[Gtin::ASIN_ATTRIBUTE]);
        }

        return null;
    }

    /**
     * @param $product
     * @param $storeId
     * @return string
     */
    public function getProductImageUrl($product, $storeId)
    {
        $productImageUrl = '';
        try {
            // In order for the product image url to use the url from the proper store view, temporarily change the store
            $currentStore = $this->storeManager->getStore();
            $this->storeManager->setCurrentStore($storeId);
            // Don't return placeholder image if product does not have an image
            $productImage = $product->getImage();
            if ($productImage) {
                $productImageUrl = $this->imageHelper->init($product, 'product_page_main_image')
                    ->setImageFile($productImage)->getUrl();
                $productImageUrl = str_replace(" ", "-", $productImageUrl);
            }
        } catch (Exception $e) {
            $this->logger->error(
                'Error retrieving product image url',
                [
                    'exception' => $e,
                    'productId' => $product->getId(),
                    'storeId' => $storeId
                ]
            );
        }
        if (isset($currentStore)) {
            $this->storeManager->setCurrentStore($currentStore);
        }

        return $productImageUrl;
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
                $this->sanitizeData($store->getBaseUrl(UrlInterface::URL_TYPE_LINK))
            );
            $feed->addChild(
                'updated',
                $this->dateTimeFactory->create('now', new DateTimeZone('UTC'))->format(DATE_ATOM)
            );
            $feed->addChild('author')->addChild('name', 'TurnTo');
            $feed->addChild(
                'id',
                $this->sanitizeData($store->getBaseUrl(UrlInterface::URL_TYPE_WEB))
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
     * @param SimpleXMLElement $entry
     * @param CatalogProduct $product
     * @param $store
     * @param $parent bool|CatalogProduct
     *
     * @throws Exception
     */
    protected function addProductToAtomFeed($entry, $product, $store, $parent)
    {
        if (empty($product)) {
            throw new Exception('Product can not be null or empty');
        }

        $sku = $this->product->turnToSafeEncoding($product->getSku());
        if (empty($sku)) {
            throw new Exception('Product must have a valid sku');
        }

        $productUrl = $parent ? $parent->getProductUrl() : $product->getProductUrl();
        if (empty($productUrl)) {
            throw new Exception('Product must have a valid store-product url');
        }

        $productName = $product->getName();
        if (empty($productName)) {
            throw new Exception('Product must have a valid name');
        }
        $productName = str_replace("\n", "", $productName);

        $entry->addChild('id', $this->sanitizeData($sku));

        $identifierExists = 'FALSE';
        $gtinMap = $this->gtinConfig->getGtinAttributesMap($store->getCode());
        if (!empty($gtinMap)) {
            $gtinValue = $this->getGtinValue($product, $gtinMap);
            if (!empty($gtinValue)) {
                $entry->addChild('g:gtin', $this->sanitizeData($gtinValue));
            }
            $brand = null;
            if (isset($gtinMap[Gtin::BRAND_ATTRIBUTE])) {
                $brand = $this->getProductAttributeValue($product, $gtinMap[Gtin::BRAND_ATTRIBUTE]);
                if (!empty($brand)) {
                    $entry->addChild('g:brand', $this->sanitizeData($brand));
                }
            }
            $mpn = null;
            if (isset($gtinMap[Gtin::MPN_ATTRIBUTE])) {
                $mpn = $this->getProductAttributeValue($product, $gtinMap[Gtin::MPN_ATTRIBUTE]);
                if (!empty($mpn)) {
                    $entry->addChild('g:mpn', $this->sanitizeData($mpn));
                }
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

        // Availability is normally determined by status, but can be overridden by custom "turnto_disabled" attribute
        $turntoDisable = $product->getCustomAttribute('turnto_disabled') ?
            $product->getCustomAttribute('turnto_disabled')->getValue() :
            false;
        $availability = $turntoDisable ? 'out of stock' :
            (($product->getStatus() == Status::STATUS_ENABLED) ? 'in stock' : 'out of stock');

        $entry->addChild('g:availability', $availability);
        $productImageUrl = $this->getProductImageUrl($product, $store->getId());
        $entry->addChild('g:image_link', $this->sanitizeData($productImageUrl));
        $entry->addChild('g:condition', 'new');
        $price = number_format($product->getPrice(), 2, '.', '');
        $entry->addChild('g:price', $price . ' ' . $store->getBaseCurrencyCode());
        $itemGroupId = $this->getItemGroupId($product, $parent);
        $entry->addChild('g:item_group_id', $itemGroupId);
    }

    /**
     * Gets the deepest tree for given product and returns as "rootNodeName > branchNodeName > leafNodeName"
     *
     * @param CatalogProduct $product
     *
     * @return string
     */
    protected function getCategoryTreeString(CatalogProduct $product)
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
     * @param Category $category
     * @param array                           $categoryBranch
     *
     * @return array
     */
    protected function getCategoryBranch(Category $category, array $categoryBranch = [])
    {
        try {
            $parent = $category->getParentCategory();
        } catch (NoSuchEntityException $isRootEntity) {
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
                $this->config->getConfigValue(Config::PRODUCT_ENABLE_AUTOMATIC_SUBMISSION, $store->getCode())
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
     * @param CatalogProduct $product
     * @param CatalogProduct|bool $parent
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

        $gtinMap = $this->gtinConfig->getGtinAttributesMap($store->getCode());

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
