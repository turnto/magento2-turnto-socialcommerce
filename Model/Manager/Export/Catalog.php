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

namespace TurnTo\SocialCommerce\Model\Manager\Export;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use TurnTo\SocialCommerce\Helper\Config;

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
     * MIME Type used for the Product Feed
     */
    const FEED_MIME = 'application/atom+xml';

    /**
     * Response body from TurnTo servers on successful operation
     */
    const TURNTO_SUCCESS_RESPONSE = 'SUCCESS';

    /**
     * @var \TurnTo\SocialCommerce\Helper\Config
     */
    protected $config;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var \TurnTo\SocialCommerce\Logger\Monolog
     */
    protected $logger;

    /**
     * @var \Magento\Framework\Intl\DateTimeFactory
     */
    protected $dateTimeFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Catalog\Helper\Image $imageHelper
     */
    protected $imageHelper = null;

    /**
     * @var TurnTo\SocialCommerce\Helper\Product
     */
    protected $productHelper;

    /**
     * @var \TurnTo\SocialCommerce\Helper\Export\Catalog
     */
    protected $exportHelper;

    /**
     * Used to generate file name (x_of_totalPages_feed.xml)
     * @var Int
     */
    protected $totalPages;


    public function __construct(
        \TurnTo\SocialCommerce\Helper\Config $config,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \TurnTo\SocialCommerce\Logger\Monolog $logger,
        \Magento\Framework\Intl\DateTimeFactory $dateTimeFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Helper\Image $imageHelper,
        \TurnTo\SocialCommerce\Helper\Product $productHelper,
        \TurnTo\SocialCommerce\Helper\Export\Catalog $exportHelper
    )
    {
        $this->config = $config;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->logger = $logger;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->storeManager = $storeManager;
        $this->imageHelper = $imageHelper;
        $this->productHelper = $productHelper;
        $this->exportHelper = $exportHelper;
    }

    /**
     * Retrieves a store/visibility filtered product collection selecting only attributes necessary for the TurnTo Feed
     * over written to allow for pagination
     *
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection | bool
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
            ->addAttributeToSelect('turnto_disabled')
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

    public function createFeed($store)
    {
        try {
            $feed = new \SimpleXMLElement(
                '<?xml version="1.0" encoding="UTF-8"?>' . '<feed xmlns="http://www.w3.org/2005/Atom"' . ' xmlns:g="http://base.google.com/ns/1.0" xml:lang="en-US" />'
            );

            $feed->addChild('title', $this->exportHelper->sanitizeData($store->getName() . ' - Google Product Atom 1.0 Feed'));
            $feed->addChild(
                'link',
                $this->exportHelper->sanitizeData($store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK))
            );
            $feed->addChild(
                'updated',
                $this->dateTimeFactory->create('now', new \DateTimeZone('UTC'))->format(DATE_ATOM)
            );
            $feed->addChild('author')->addChild('name', 'TurnTo');
            $feed->addChild(
                'id',
                $this->exportHelper->sanitizeData($store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB))
            );

            return $feed;
        } catch (\Exception $feedException) {
            $this->logger->error(
                'An exception occurred while creating the catalog feed',
                [
                    'exception' => $feedException,
                ]
            );
            throw $feedException;
        }
    }

    /**
     * @description Writes all individually visible products to an ATOM 1.0 feed which is returned in a SimpleXMLElement Object
     *
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @param $feed
     * @param $products
     * @return mixed
     * @throws \Exception
     */
    public function populateProductFeed(\Magento\Store\Api\Data\StoreInterface $store, $feed, $products)
    {
        $progressCounter = 0;
        try {

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

        } catch (\Exception $feedException) {
            $this->logger->error(
                'An exception occurred that prevented the creation of the catalog feed',
                [
                    'exception' => $feedException,
                    'productCount' => count($products),
                    'productsProcessed' => $progressCounter
                ]
            );
            throw $feedException;
        }
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

        $entry->addChild('id', $this->exportHelper->sanitizeData($sku));

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
                $entry->addChild('g:gtin', $this->exportHelper->sanitizeData($gtin));
            }
            if (!empty($brand)) {
                $entry->addChild('g:brand', $this->exportHelper->sanitizeData($brand));
            }
            if (!empty($mpn)) {
                $entry->addChild('g:mpn', $this->exportHelper->sanitizeData($mpn));
            }
            if (!empty($brand) && (!empty($gtin) || !empty($mpn))) {
                $identifierExists = 'TRUE';
            }
        }

        $entry->addChild('g:identifier_exists', $identifierExists);
        $entry->addChild('g:link', $this->exportHelper->sanitizeData($productUrl));
        $entry->addChild('g:title', $this->exportHelper->sanitizeData($productName));

        $categoryName = $this->exportHelper->getCategoryTreeString($product);
        if (!empty($categoryName)) {
            $cleanCategoryName = $this->exportHelper->sanitizeData($categoryName);
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

        // Availability is normally determined by status, but can be overridden by custom "turnto_disabled" attribute
        $turntoDisable = $product->getCustomAttribute('turnto_disabled') ?
            $product->getCustomAttribute('turnto_disabled')->getValue():
            false;
        $availability = $turntoDisable ? 'out of stock' :
            (($product->getStatus() == 1) ? 'in stock' : 'out of stock');

        $entry->addChild('g:availability', $availability);
        $entry->addChild('g:image_link', $this->exportHelper->sanitizeData($productImageUrl));
        $entry->addChild('g:condition', 'new');
        $entry->addChild('g:price', $product->getPrice() . ' ' . $store->getBaseCurrencyCode());
        $itemGroupId = $this->exportHelper->getItemGroupId($product, $parent);
        $entry->addChild('g:item_group_id', $itemGroupId);
    }

    /**
     * Transmits an XML feed to the TurnTo Product Feed endpoint set in the TurnTo configuration
     *
     * @param \SimpleXMLElement                      $feed
     * @param \Magento\Store\Api\Data\StoreInterface $store
     *
     * @throws \Exception
     */
    public function transmitFeed(\SimpleXMLElement $feed, \Magento\Store\Api\Data\StoreInterface $store, $page = null)
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
                'An error occurred while transmitting the catalog feed to TurnTo. Error:',
                [
                    'exception' => $e,
                    'response' => $response ? $response->getBody() : 'null'
                ]
            );
            throw $e;
        }
    }

    /**
     * @return Int
     */
    public function getTotalPages()
    {
        return $this->totalPages;
    }

    /**
     * @param Int $totalPages
     */
    public function setTotalPages($totalPages)
    {
        $this->totalPages = $totalPages;
    }
}
