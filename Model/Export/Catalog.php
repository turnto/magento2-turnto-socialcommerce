<?php

namespace TurnTo\SocialCommerce\Model\Export;

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
     * Creates the product feed and pushes it to TurnTo
     */
    public function cronUploadFeed()
    {
        $feed = $this->generateProductFeed();
        $this->transmitFeed($feed);
    }

    /**
     * Transmits xml product feed to TurnTo
     * @param \SimpleXMLElement $feed
     */
    protected function transmitFeed(\SimpleXMLElement $feed)
    {
        try
        {
            $response = null;
            $this->httpClient->setUri($this->config->getFeedUploadAddress())
            ->setMethod(\Zend_Http_Client::POST)
            ->setParameterPost
            (
                [
                    'siteKey' => $this->config->getSiteKey(),
                    'authKey' => $this->config->getAuthorizationKey(),
                    'feedStyle' => self::FEED_STYLE
                ]
            )
            ->setFileUpload(self::FEED_STYLE, 'file', $feed->asXML(), self::FEED_MIME);

            $response = $this->httpClient->send();
            if (!$response || !$response->isSuccess())
            {
                throw new \Exception('TurnTo catalog feed submission failed silently');
            }
        }
        catch (\Exception $e)
        {
            $this->logger->error('An error occured while transmitting the catalog feed to TurnTo',
                [
                    'exception' => $e,
                    'response' => $response ? 'null' : $response->getBody()
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
     * Writes all individually visible products to an ATOM 1.0 feed which is returned in a SimpleXMLElement
     * @return null|\SimpleXMLElement
     * @throws \Exception //if feed as a whole could not be created
     */
    protected function generateProductFeed()
    {
        $feed = null;
        $progressCounter = 0;

        try
        {
            $products = $this->getProducts();

            $feed = new \SimpleXMLElement
            (
                '<?xml version="1.0" encoding="UTF-8"?><feed xmlns="http://www.w3.org/2005/Atom" 
                    xmlns:g="http://base.google.com/ns/1.0" xml:lang="en-US" />'
            );

            $feed->addChild('title', $this->sanitizeData($this->getStoreName() . ' - Google Product Atom 1.0 Feed'));
            $feed->addChild('link',
                $this->sanitizeData($this->getStoreUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK))
            );
            $feed->addChild('updated', $this->datetimefactory->create()->date(DATE_ATOM));
            $feed->addChild('author')->addChild('name', 'TurnTo');
            $feed->addChild('id',
                $this->sanitizeData($this->getStoreUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB))
            );

            foreach ($products as $product)
            {
                try
                {
                    $this->addProductToAtomFeed($feed->addChild('entry'), $product);
                }
                catch (\Exception $entryException)
                {
                    $this->logger->error('Product failed to be added to feed',
                        [
                            'exception' => $entryException,
                            'productSKU' => $product->getSku()
                        ]
                    );
                }
                finally
                {
                    $progressCounter++;
                }
            }
        }
        catch (\Exception $feedException)
        {
            if ($feed)
            {
                $this->logger
                    ->error('An exception occurred while creating the catalog feed',
                        [
                            'exception' => $feedException,
                            'productCount' => count($products),
                            'productsProcessed' => $progressCounter
                        ]
                    );
            }
            else if ($products)
            {
                $this->logger
                    ->error('An exception occurred that prevented the creation of the catalog feed',
                        [
                            'exception' => $feedException,
                            'productCount' => count($products),
                            'productsProcessed' => $progressCounter
                        ]
                    );
            }
            else
            {
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

    /*
     * Adds a Magento catalog product to a Google Products ATOM 1.0 xml feed
     * @param SimpleXMLElement $entry
     * @param \Magento\Catalog\Model\Product $product
     */
    protected function addProductToAtomFeed($entry, $product) {
        if (empty($product))
        {
            throw new \Exception('Product can not be null or empty');
        }

        $sku = $product->getSku();
        if (empty($sku))
        {
            throw new \Exception('Product must have a valid sku');
        }

        $productUrl = $product->getUrlInStore();
        if (empty($productUrl))
        {
            throw new \Exception('Product must have a valid store-product url');
        }

        $productName = $product->getName();
        if (empty($productName))
        {
            throw new \Exception('Product must have a valid name');
        }

        $entry->addChild('id', $this->sanitizeData($sku));

        $gtin = null;
        $mpn = null;
        $brand = null;
        $identifierExists = 'FALSE';
        $gtinMap = $this->config->getGtinAttributesMap();
        if (!empty($gtinMap))
        {

            if (isset($gtinMap[\TurnTo\SocialCommerce\Helper\Config::MPN_ATTRIBUTE]))
            {
                $mpn = $product->getResource()
                    ->getAttribute($gtinMap[\TurnTo\SocialCommerce\Helper\Config::MPN_ATTRIBUTE])
                    ->getFrontend()->getValue($product);
            }
            if (isset($gtinMap[\TurnTo\SocialCommerce\Helper\Config::BRAND_ATTRIBUTE]))
            {
                $brand = $product->getResource()
                    ->getAttribute($gtinMap[\TurnTo\SocialCommerce\Helper\Config::BRAND_ATTRIBUTE])
                    ->getFrontend()->getValue($product);
            }
            if (empty($gtin) && isset($gtinMap[\TurnTo\SocialCommerce\Helper\Config::UPC_ATTRIBUTE]))
            {
                $gtin = $product->getResource()
                    ->getAttribute($gtinMap[\TurnTo\SocialCommerce\Helper\Config::UPC_ATTRIBUTE])
                    ->getFrontend()->getValue($product);
            }
            if (empty($gtin) && isset($gtinMap[\TurnTo\SocialCommerce\Helper\Config::EAN_ATTRIBUTE]))
            {
                $gtin = $product->getResource()
                    ->getAttribute($gtinMap[\TurnTo\SocialCommerce\Helper\Config::EAN_ATTRIBUTE])
                    ->getFrontend()->getValue($product);
            }
            if (empty($gtin) && isset($gtinMap[\TurnTo\SocialCommerce\Helper\Config::JAN_ATTRIBUTE]))
            {
                $gtin = $product->getResource()
                    ->getAttribute($gtinMap[\TurnTo\SocialCommerce\Helper\Config::JAN_ATTRIBUTE])
                    ->getFrontend()->getValue($product);
            }
            if (empty($gtin) && isset($gtinMap[\TurnTo\SocialCommerce\Helper\Config::ISBN_ATTRIBUTE]))
            {
                $gtin = $product->getResource()
                    ->getAttribute($gtinMap[\TurnTo\SocialCommerce\Helper\Config::ISBN_ATTRIBUTE])
                    ->getFrontend()->getValue($product);
            }
            if (!empty($gtin))
            {
                $entry->addChild('g:gtin', $this->sanitizeData($gtin));
            }
            if(!empty($brand))
            {
                $entry->addChild('g:brand', $this->sanitizeData($brand));
            }
            if (!empty($mpn))
            {
                $entry->addChild('g:mpn', $this->sanitizeData($mpn));
            }
            if (!empty($brand) && (!empty($gtin) || !empty($mpn)))
            {
                $identifierExists = 'TRUE';
            }
        }

        $currencyCode = $this->storeManager->getStore()->getBaseCurrencyCode();

        $entry->addChild('g:identifier_exists', $identifierExists);
        $entry->addChild('g:link', $this->sanitizeData($productUrl));
        $entry->addChild('g:title', $this->sanitizeData($productName));
        $categoryName = $this->getCategoryTreeString($product);

        if (!empty($categoryName))
        {
            $cleanCategoryName = $this->sanitizeData($categoryName);
            $entry->addChild('g:google_product_category', $cleanCategoryName);
            $entry->addChild('g:product_type', $cleanCategoryName);
        }

        $entry->addChild('g:image_link', $this->sanitizeData($this->productHelper->getImageUrl($product)));
        $entry->addChild('g:condition', 'new');
        $entry->addChild('g:availability', $product->getQuantityAndStockStatus() == 1 ? 'in stock' : 'out of stock');
        $entry->addChild('g:price', $product->getPrice() . " $currencyCode");
        $entry->addChild('g:description', $this->sanitizeData($product->getDescription()));
    }

    /**
     * Gets the deepest tree for given product and returns as "rootNodeName > branchNodeName > leafNodeName"
     * @param \Magento\Catalog\Model\Product $product
     * @return string
     */
    protected function getCategoryTreeString(\Magento\Catalog\Model\Product $product)
    {
        $categoryName = '';
        $categories = $product->getCategoryCollection();
        $deepestLength = 0;
        $deepestTree = null;

        foreach($categories as $category)
        {
            $tempTree = [];
            $tempRoot = $this->getRootCategory($category, $tempTree);
            if ($tempRoot != $category)
            {
                $treeLength = count($tempTree);
                if ($treeLength > $deepestLength)
                {
                    $deepestLength = $treeLength;
                    $deepestTree = $tempTree;
                }
            }
        }

        for($i = ($deepestLength - 1); $i > 0; $i--)
        {
            if (!empty($categoryName))
            {
                $categoryName .= ' > ';
            }
            $categoryName .= $deepestTree[$i]->getName();
        }

        return $categoryName;
    }

    /**
     * recursively walks category chain until reaching root category while building an array from leaf to root
     * @param \Magento\Catalog\Model\Category $category
     * @param array $categories
     * @return \Magento\Catalog\Model\Category
     */
    protected function getRootCategory(\Magento\Catalog\Model\Category $category, array &$categories)
    {
        try
        {
            $parent = $category->getParentCategory();
        }
        catch (\NoSuchEntityException $isRootEntity)
        {
            $parent = null;
        }
        finally
        {
            $categories[] = $category;
            if (isset($parent))
            {
                return $this->getRootCategory($parent, $categories);
            }
            else
            {
                return $category;
            }
        }
    }
}
