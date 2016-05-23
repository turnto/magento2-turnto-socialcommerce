<?php

namespace TurnTo\SocialCommerce\Model\Export;

class Catalog extends AbstractExport
{
    const FEED_STYLE = 'google-product.xml';
    const FEED_MIME = 'application/atom+xml';

    /**
     * Creates the product feed and pushes it to TurnTo
     */
    public function cronUploadFeed()
    {
        $feed = $this->writeProductFeed();
        $this->transmitFeed($feed);
    }

    /**
     * Transmits xml product feed to TurnTo
     *
     * @param \SimpleXMLElement $feed
     */
    protected function transmitFeed($feed) {
        try {
            $this->_httpClient->setUri($this->_config->getFeedUploadAddress());
            $this->_httpClient->setMethod(\Zend_Http_Client::POST);
            $this->_httpClient->setMethod(\Zend_Http_Client::POST);
            $this->_httpClient->setParameterPost(array(
                'siteKey' => $this->_config->getSiteKey(),
                'authKey' => $this->_config->getAuthorizationKey(),
                'feedStyle' => $this::FEED_STYLE)
            );
            $this->_httpClient->setFileUpload($this::FEED_STYLE, 'file', $feed->asXML(), $this::FEED_MIME);
            $response = $this->_httpClient->send();

            $body = 'failed';
            if (!$response || !$response->isSuccess()) {
                throw new \Exception('TurnTo catalog feed submission failed without error');
            }
        } catch (\Exception $e) {
            $this->_logger->error('An error occured while transmitting the catalog feed to TurnTo',
                array('exception' => $e, 'response' => $response ? 'null' : $response));
        }
    }

    /**
     * Escapes special characters for xml use
     *
     * @param string $dirtyString
     * @return string
     */
    protected function sanitizeData($dirtyString) {
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
     *
     * @return null|\SimpleXMLElement
     * @throws \Exception //if feed as a whole could not be created
     */
    protected function writeProductFeed()
    {
        $feed = null;
        $progressCounter = 0;

        try {
            $products = $this->getProducts();

            if ($products) {
                $feed = new \SimpleXMLElement(
                    '<?xml version="1.0" encoding="UTF-8"?><feed xmlns="http://www.w3.org/2005/Atom" xmlns:g="http://base.google.com/ns/1.0" xml:lang="en-US" />'
                );
                $feed->addChild('title', $this->getStoreName() . ' - Google Product Atom 1.0 Feed');
                $feed->addChild('link', $this->getStoreUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK));
                $feed->addChild('updated', $this->_datetimefactory->create()->date(DATE_ATOM));
                $authorNode = $feed->addChild('author');
                $authorNode->addChild('name', 'TurnTo');
                $feed->addChild('id', $this->getStoreUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB));

                foreach ($products as $product) {
                    try {
                        $this->addProductToAtomFeed($feed->addChild('entry'), $product);
                    } catch (\Exception $entryException) {
                        $this->_logger->error("Product failed to be added to feed", array(
                            'exception' => $entryException,
                            'product' => $product,
                        ));
                    }

                    $progressCounter++;
                }
            }
        } catch (\Exception $feedException) {
            if ($feed) {
                $this->_logger->error('An exception occurred while creating the catalog feed', array (
                    'exception' => $feedException,
                    'productCount' => count($products),
                    'productsProcessed' => $progressCounter,
                ));
            } else if ($products) {
                $this->_logger->error('An exception occurred that prevented the creation of the catalog feed', array (
                    'exception' => $feedException,
                    'productCount' => count($products),
                    'productsProcessed' => $progressCounter,
                ));
            } else {
                $this->_logger->error('An exception occured while retrieving the products for the catalog feed', array (
                    'exception' => $feedException,
                    'productsProcessed' => $progressCounter,
                ));
            }
            throw $feedException;
        }
        
        return $feed;
    }

    /*
     * Adds a magento catalog product to a Google Products ATOM 1.0 xml feed
     *
     * @param SimpleXMLElement $entry
     * @param \Magento\Catalog\Model\Product $product
     */
    protected function addProductToAtomFeed($entry, $product) {
        if (empty($product)) {
            throw new \Exception("Product can not be null or empty");
        }

        $sku = $product->getSku();
        if (empty($sku)) {
            throw new \Exception("Product must have a valid sku");
        }

        $productUrl = $product->getUrlInStore();
        if (empty($productUrl)) {
            throw new \Exception("Product must have a valid store-product url");
        }

        $productName = $product->getName();
        if (empty($productName)) {
            throw new \Exception("Product must have a valid name");
        }

        $entry->addChild('id', $sku);
        $entry->addChild('g:identifier_exists', 'FALSE');
        $entry->addChild('link', $productUrl);
        $entry->addChild('title', $this->sanitizeData($productName));

        $categories = $product->getCategoryCollection();
        $categoryIds = $categories->getAllIds();
        $categoryName = "";

        for($i = 0; $i < count($categoryIds); $i++) {
            $categoryLoader = $this->_categoryFactory->create();
            $categoryLoader->load($i);
            $tempName = null;
            $tempName = $categoryLoader->getName();
            if ($tempName) {
                if (!empty($categoryName))  {
                    $categoryName .= ' > ';
                }
                $categoryName .= $tempName;
            }
        }

        $cleanCategoryName = $this->sanitizeData($categoryName);
        if (!empty($cleanCategoryName)) {
            $entry->addChild('g:google_product_category', $cleanCategoryName);
            $entry->addChild('g:product_type', $cleanCategoryName);
        }

        $entry->addChild('g:image_link', $this->getStoreUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . ltrim($product->getImage(), '/'));
        $entry->addChild('g:condition', 'new');
        $entry->addChild('g:availability', $product->getQuantityAndStockStatus() == 1 ? 'in stock' : 'out of stock');
        $entry->addChild('g:price', $product->getPrice());
        $entry->addChild('description', $this->sanitizeData($product->getDescription()));
    }
}
