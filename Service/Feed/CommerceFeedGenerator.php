<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Service\Feed;

use Exception;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Api\Data\StoreInterface;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Config\Gtin;
use TurnTo\SocialCommerce\Model\Config\Source\FeedFormat;
use TurnTo\SocialCommerce\Model\Export\Product as ExportProduct;
use TurnTo\SocialCommerce\Model\Product;

/**
 * Tab-delimited Commerce product feed generator for TurnTo.
 */
class CommerceFeedGenerator extends AbstractFeedGenerator
{
    /**
     * @var ExportProduct
     */
    protected $exportProduct;

    /**
     * @var resource|null Writable stream for the in-progress feed body
     */
    protected $stream;

    /**
     * @param Config $config
     * @param Gtin $gtinConfig
     * @param Image $imageHelper
     * @param Product $product
     * @param EavConfig $eavConfig
     * @param PriceCurrencyInterface $priceCurrency
     * @param Monolog $logger
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
        $this->exportProduct = $exportProduct;
    }

    /**
     * @inheritdoc
     */
    public function getFeedStyle(): string
    {
        return FeedFormat::COMMERCE;
    }

    /**
     * @inheritdoc
     */
    public function beginFeed(StoreInterface $store)
    {
        $this->stream = fopen('php://temp', 'r+');
        $header = implode("\t", [
            'id', 'title', 'item_url', 'image_url', 'stock', 'active',
            'category_path_json', 'virtual_parent_code', 'members', 'brand',
            'currency', 'price', 'upc', 'ean', 'mpn'
        ]) . "\n";
        fwrite($this->stream, $header);
    }

    /**
     * @inheritdoc
     */
    public function addProduct($product, $parent = null, $storeId = null): bool
    {
        try {
            $line = $this->generateProductLine($product, $storeId, $parent);
            if ($line) {
                fwrite($this->stream, $line . "\n");
                return true;
            }
        } catch (Exception $entryException) {
            $this->logger->error(
                'Product failed to be added to feed',
                [
                    'exception' => $entryException,
                    'productSKU' => $product->getSku()
                ]
            );
            return false;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function finishFeed()
    {
        rewind($this->stream);
        $content = stream_get_contents($this->stream);
        fclose($this->stream);
        return $content;
    }

    /**
     * Sanitize text fields for tab-delimited feeds to prevent column/row shifting.
     *
     * @param string|null $text
     * @return string
     */
    protected function sanitizeTextField(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        return trim(preg_replace('/[\t\r\n]+/', ' ', $text));
    }

    /**
     * Build one tab-separated line for the commerce feed.
     *
     * @param CatalogProduct $product
     * @param int|string|null $storeId
     * @param bool|CatalogProduct|null $parent
     * @return string
     * @throws Exception
     */
    protected function generateProductLine($product, $storeId, $parent)
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
        $productName = $this->sanitizeTextField($productName);

        $turntoDisable = $product->getCustomAttribute('turnto_disabled') ?
            $product->getCustomAttribute('turnto_disabled')->getValue() :
            false;
        $active = $turntoDisable ? '0' : (($product->getStatus() == Status::STATUS_ENABLED) ? '1' : '0');

        $stock = '0';
        $isInStock = (bool) $product->getData('is_in_stock');
        if ($isInStock) {
            $qty = (float) $product->getData('qty');
            $stock = (string)(int)$qty;
        }

        $categoryPathJson = '';
        $categoryName = $this->getCategoryTreeString($product, $storeId);
        if (!empty($categoryName)) {
            $categories = explode(' > ', $categoryName);
            $catArray = [];
            foreach ($categories as $index => $cat) {
                $catArray[] = [
                    'id' => (string)(($index + 1) * 10),
                    'name' => $cat
                ];
            }
            $categoryPathJson = json_encode($catArray);
        }

        $virtualParentCode = '';
        $itemGroupId = $this->getItemGroupId($product, $parent);
        if ($itemGroupId !== $sku) {
            $virtualParentCode = $itemGroupId;
        }

        $members = $this->getMembers($product);

        $brand = '';
        $upc = '';
        $ean = '';
        $mpn = '';
        $gtinMap = $this->gtinConfig->getGtinAttributesMap($storeId);
        if (!empty($gtinMap)) {
            if (isset($gtinMap[Gtin::BRAND_ATTRIBUTE])) {
                $brand = $this->sanitizeTextField((string) $this->getProductAttributeValue($product, $gtinMap[Gtin::BRAND_ATTRIBUTE]));
            }
            if (isset($gtinMap[Gtin::UPC_ATTRIBUTE])) {
                $upc = $this->sanitizeTextField((string) $this->getProductAttributeValue($product, $gtinMap[Gtin::UPC_ATTRIBUTE]));
            }
            if (isset($gtinMap[Gtin::EAN_ATTRIBUTE])) {
                $ean = $this->sanitizeTextField((string) $this->getProductAttributeValue($product, $gtinMap[Gtin::EAN_ATTRIBUTE]));
            }
            if (isset($gtinMap[Gtin::MPN_ATTRIBUTE])) {
                $mpn = $this->sanitizeTextField((string) $this->getProductAttributeValue($product, $gtinMap[Gtin::MPN_ATTRIBUTE]));
            }
        }

        $price = $this->priceCurrency->convertAndRound($product->getFinalPrice(), $storeId);
        $currencyCode = $this->priceCurrency->getCurrency($storeId)->getCurrencyCode();

        $data = [
            $sku,
            $productName,
            $productUrl,
            $this->getProductImageUrl($product),
            $stock,
            $active,
            $categoryPathJson,
            $virtualParentCode,
            $members,
            $brand,
            $currencyCode,
            $price,
            $upc,
            $ean,
            $mpn
        ];

        return implode("\t", $data);
    }

    /**
     * @param CatalogProduct $product
     * @return string
     */
    protected function getMembers($product)
    {
        $members = [];
        $typeId = $product->getTypeId();

        try {
            if ($typeId === 'bundle') {
                $selections = $product->getTypeInstance()->getSelectionsCollection(
                    $product->getTypeInstance()->getOptionsIds($product),
                    $product
                );
                foreach ($selections as $selection) {
                    $members[] = $selection->getSku();
                }
            } elseif ($typeId === 'grouped') {
                $associatedProducts = $product->getTypeInstance()->getAssociatedProducts($product);
                foreach ($associatedProducts as $child) {
                    $members[] = $child->getSku();
                }
            }
        } catch (Exception $e) {
            $this->logger->error(
                'Error retrieving members for product',
                [
                    'exception' => $e,
                    'productId' => $product->getId()
                ]
            );
        }

        return implode(',', array_unique($members));
    }
}
