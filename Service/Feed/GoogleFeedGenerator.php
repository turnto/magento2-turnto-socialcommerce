<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Service\Feed;

use DateTimeZone;
use Exception;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use SimpleXMLElement;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Config\Gtin;
use TurnTo\SocialCommerce\Model\Config\Source\FeedFormat;
use TurnTo\SocialCommerce\Model\Export\Product as ExportProduct;
use TurnTo\SocialCommerce\Model\Product;

/**
 * Google Shopping Atom 1.0 product feed generator.
 */
class GoogleFeedGenerator extends AbstractFeedGenerator
{
    /**
     * @var DateTimeFactory
     */
    protected $dateTimeFactory;

    /**
     * @var ExportProduct
     */
    protected $exportProduct;

    /**
     * @param Config $config
     * @param Gtin $gtinConfig
     * @param Image $imageHelper
     * @param Product $product
     * @param EavConfig $eavConfig
     * @param PriceCurrencyInterface $priceCurrency
     * @param Monolog $logger
     * @param DateTimeFactory $dateTimeFactory
     * @param ExportProduct $exportProduct Resolves storefront product URLs for feed links
     */
    public function __construct(
        Config $config,
        Gtin $gtinConfig,
        Image $imageHelper,
        Product $product,
        EavConfig $eavConfig,
        PriceCurrencyInterface $priceCurrency,
        Monolog $logger,
        DateTimeFactory $dateTimeFactory,
        ExportProduct $exportProduct
    ) {
        parent::__construct(
            $config,
            $gtinConfig,
            $imageHelper,
            $product,
            $eavConfig,
            $priceCurrency,
            $logger
        );
        $this->dateTimeFactory = $dateTimeFactory;
        $this->exportProduct = $exportProduct;
    }

    /**
     * @var resource|null Writable stream for the in-progress feed body
     */
    protected $stream;

    /**
     * @inheritdoc
     */
    public function getFeedStyle(): string
    {
        return FeedFormat::GOOGLE_PRODUCT;
    }

    /**
     * @inheritdoc
     */
    public function beginFeed(StoreInterface $store)
    {
        $this->stream = fopen('php://temp', 'r+');
        $header = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
                  '<feed xmlns="http://www.w3.org/2005/Atom" xmlns:g="http://base.google.com/ns/1.0" xml:lang="en-US">' . "\n" .
                  '<title>' . $this->sanitizeData($store->getName() . ' - Google Product Atom 1.0 Feed') . '</title>' . "\n" .
                  '<link>' . $this->sanitizeData($store->getBaseUrl(UrlInterface::URL_TYPE_LINK)) . '</link>' . "\n" .
                  '<updated>' . $this->dateTimeFactory->create('now', new DateTimeZone('UTC'))->format(DATE_ATOM) . '</updated>' . "\n" .
                  '<author><name>TurnTo</name></author>' . "\n" .
                  '<id>' . $this->sanitizeData($store->getBaseUrl(UrlInterface::URL_TYPE_WEB)) . '</id>' . "\n";
        fwrite($this->stream, $header);
    }

    /**
     * @inheritdoc
     */
    public function addProduct($product, $parent = null, $storeId = null): bool
    {
        try {
            $entry = new SimpleXMLElement('<entry xmlns="http://www.w3.org/2005/Atom" xmlns:g="http://base.google.com/ns/1.0"/>');
            $this->addProductToAtomFeed($entry, $product, $storeId, $parent);
            $entryXml = $entry->asXML();
            $entryXml = str_replace(
                ['<?xml version="1.0"?>', ' xmlns="http://www.w3.org/2005/Atom"', ' xmlns:g="http://base.google.com/ns/1.0"'],
                '',
                $entryXml
            );
            fwrite($this->stream, trim($entryXml) . "\n");
            return true;
        } catch (Exception $e) {
            $this->logger->error(
                'Product failed to be added to feed',
                [
                    'exception' => $e,
                    'productSKU' => $product ? $product->getSku() : null
                ]
            );
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function finishFeed()
    {
        fwrite($this->stream, '</feed>');
        rewind($this->stream);
        $content = stream_get_contents($this->stream);
        fclose($this->stream);
        return $content;
    }

    /**
     * Adds a Magento catalog product to a Google Products ATOM 1.0 xml feed
     *
     * @param SimpleXMLElement $entry
     * @param CatalogProduct $product
     * @param int|string $storeId
     * @param bool|CatalogProduct $parent
     * @return void
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

        $productUrl = $parent ? $this->exportProduct->getProductUrl($parent, $storeId) : $this->exportProduct->getProductUrl($product, $storeId);
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
        $gNs = 'http://base.google.com/ns/1.0';
        if (!empty($gtinMap)) {
            $gtinValue = $this->getGtinValue($product, $gtinMap);
            if (!empty($gtinValue)) {
                $entry->addChild('g:gtin', $this->sanitizeData($gtinValue), $gNs);
            }
            $brand = null;
            if (isset($gtinMap[Gtin::BRAND_ATTRIBUTE])) {
                $brand = $this->getProductAttributeValue($product, $gtinMap[Gtin::BRAND_ATTRIBUTE]);
                if (!empty($brand)) {
                    $entry->addChild('g:brand', $this->sanitizeData($brand), $gNs);
                }
            }
            $mpn = null;
            if (isset($gtinMap[Gtin::MPN_ATTRIBUTE])) {
                $mpn = $this->getProductAttributeValue($product, $gtinMap[Gtin::MPN_ATTRIBUTE]);
                if (!empty($mpn)) {
                    $entry->addChild('g:mpn', $this->sanitizeData($mpn), $gNs);
                }
            }
            if (!empty($brand) && (!empty($gtinValue) || !empty($mpn))) {
                $identifierExists = 'TRUE';
            }
        }

        $entry->addChild('g:identifier_exists', $identifierExists, $gNs);
        $entry->addChild('g:link', $this->sanitizeData($productUrl), $gNs);
        $entry->addChild('g:title', $this->sanitizeData($productName), $gNs);

        $categoryName = $this->getCategoryTreeString($product, $storeId);
        if (!empty($categoryName)) {
            $cleanCategoryName = $this->sanitizeData($categoryName);
            $entry->addChild('g:google_product_category', $cleanCategoryName, $gNs);
            $entry->addChild('g:product_type', $cleanCategoryName, $gNs);
        }

        // Availability is normally determined by status, but can be overridden by custom "turnto_disabled" attribute
        $turntoDisable = $product->getCustomAttribute('turnto_disabled') ?
            $product->getCustomAttribute('turnto_disabled')->getValue() :
            false;
        $availability = $turntoDisable ? 'out of stock' :
            (($product->getStatus() == Status::STATUS_ENABLED) ? 'in stock' : 'out of stock');

        $entry->addChild('g:availability', $availability, $gNs);
        $productImageUrl = $this->getProductImageUrl($product);
        $entry->addChild('g:image_link', $this->sanitizeData($productImageUrl), $gNs);
        $entry->addChild('g:condition', 'new', $gNs);
        $price = $this->priceCurrency->convertAndRound($product->getFinalPrice(), $storeId);
        $currencyCode = $this->priceCurrency->getCurrency($storeId)->getCurrencyCode();
        $entry->addChild('g:price', $price . ' ' . $currencyCode, $gNs);
        $itemGroupId = $this->getItemGroupId($product, $parent);
        $entry->addChild('g:item_group_id', $this->sanitizeData($itemGroupId), $gNs);
    }
}
