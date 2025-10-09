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
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\App\Emulation;
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
     * @var Emulation
     */
    protected $emulation;
    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

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
     * @param Emulation $emulation
     * @param PriceCurrencyInterface $priceCurrency
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
        Emulation             $emulation,
        PriceCurrencyInterface $priceCurrency,
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
        $this->emulation = $emulation;
        $this->priceCurrency = $priceCurrency;
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
        if (is_string($dirtyString)) {
            return htmlspecialchars($dirtyString, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        return $dirtyString;
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
                return (string)$label;
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
        $gtinAttributes = [
            Gtin::UPC_ATTRIBUTE,
            Gtin::EAN_ATTRIBUTE,
            Gtin::JAN_ATTRIBUTE,
            Gtin::ISBN_ATTRIBUTE,
            Gtin::ASIN_ATTRIBUTE
        ];
        foreach ($gtinAttributes as $key) {
            if (isset($gtinMap[$key])) {
                return $this->getProductAttributeValue($product, $gtinMap[$key]);
            }
        }

        return null;
    }

    /**
     * @param $product
     * @return string
     */
    public function getProductImageUrl($product)
    {
        $productImageUrl = '';
        try {
            $productImage = $product->getImage();
            if ($productImage) {
                $productImageUrl = $this->imageHelper->init($product, 'product_page_main_image')
                    ->setImageFile($productImage)->getUrl();
            }
        } catch (Exception $e) {
            $this->logger->error(
                'Error retrieving product image',
                [
                    'exception' => $e,
                    'productId' => $product->getId()
                ]
            );
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
        $storeId = $store->getId();

        try {
            if (!$products = $this->getProducts($storeId, $page)) {
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
            if ($this->config->getUseChildSku($storeId)) {
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
                if ($this->config->getUseChildSku($storeId) && isset($childProducts[$product->getSku()])) {
                    $parent = $childProducts[$product->getSku()];
                }
                try {
                    $this->addProductToAtomFeed($feed->addChild('entry'), $product, $storeId, $parent);
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
     * @param int|string $storeId
     * @param bool|CatalogProduct $parent
     *
     * @throws Exception
     */
    protected function addProductToAtomFeed($entry, $product, $storeId, $parent)
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
        $gtinMap = $this->gtinConfig->getGtinAttributesMap($storeId);
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
            if (!empty($brand) && (!empty($gtinValue) || !empty($mpn))) {
                $identifierExists = 'TRUE';
            }
        }

        $entry->addChild('g:identifier_exists', $identifierExists);
        $entry->addChild('g:link', $this->sanitizeData($productUrl));
        $entry->addChild('g:title', $this->sanitizeData($productName));

        $categoryName = $this->getCategoryTreeString($product, $storeId);
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
        $productImageUrl = $this->getProductImageUrl($product);
        $entry->addChild('g:image_link', $this->sanitizeData($productImageUrl));
        $entry->addChild('g:condition', 'new');
        $price = $this->priceCurrency->convertAndRound($product->getFinalPrice(), $storeId);
        $currencyCode = $this->priceCurrency->getCurrency($storeId)->getCurrencyCode();
        $entry->addChild('g:price', $price . ' ' . $currencyCode);
        $itemGroupId = $this->getItemGroupId($product, $parent);
        $entry->addChild('g:item_group_id', $this->sanitizeData($itemGroupId));
    }

    /**
     * Gets the deepest tree for given product and returns as "rootNodeName > branchNodeName > leafNodeName"
     *
     * @param CatalogProduct $product
     * @param int|StoreInterface|string $storeId
     * @return string
     * @throws LocalizedException
     */
    protected function getCategoryTreeString(CatalogProduct $product, $storeId)
    {
        $categoryName = '';
        $categories = $product->getCategoryCollection()->setStoreId($storeId)->addAttributeToSelect('name');
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
            $storeId = $store->getId();
            if (
                $this->config->getIsEnabled($storeId) &&
                $this->config->getConfigValue(Config::PRODUCT_ENABLE_AUTOMATIC_SUBMISSION, $storeId)
            ) {
                try {
                    $page = 1;
                    $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
                    while ($feed = $this->generateProductFeed($store, $page)) {
                        try {
                            $fileName = sprintf('%s_of_%s_store_%s_%s', $page, $this->totalPages, $storeId, self::FEED_STYLE);
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
                } catch (Exception $e) {
                    $this->logger->error(
                        'Catalog export error',
                        [
                            'exception' => $e,
                            'storeId' => $storeId,
                        ]
                    );
                } finally {
                    $this->emulation->stopEnvironmentEmulation();
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
     * @param int|string $storeId
     * @param int $page
     * @param ?int $pageCount
     * @return Collection|false
     */
    public function getProducts($storeId, $page, $pageCount = 10000)
    {
        $collection = $this->productCollectionFactory->create()
            ->setStoreId($storeId)
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

        $gtinMap = $this->gtinConfig->getGtinAttributesMap($storeId);

        if (!empty($gtinMap)) {
            foreach ($gtinMap as $attributeName) {
                $collection->addAttributeToSelect($attributeName);
            }
        }

        if (!$this->config->getUseChildSku($storeId)) {
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

        $collection->addStoreFilter($storeId);

        //used to generate file name 1_of_$totalPages.xml
        $this->totalPages = $collection->getLastPageNumber();

        //stop the feed once we get to the last page
        if ($this->totalPages < $page) {
            return false;
        }

        return $collection;
    }
}
