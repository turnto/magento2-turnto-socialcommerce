<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Service\Feed;

use Exception;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Api\Data\StoreInterface;
use TurnTo\SocialCommerce\Api\FeedGeneratorInterface;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Config\Gtin;
use TurnTo\SocialCommerce\Model\Product;

/**
 * Shared helpers for TurnTo product feed generators (sanitization, GTIN, images, categories).
 */
abstract class AbstractFeedGenerator implements FeedGeneratorInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Gtin
     */
    protected $gtinConfig;

    /**
     * @var Image
     */
    protected $imageHelper;

    /**
     * @var Product
     */
    protected $product;

    /**
     * @var EavConfig
     */
    protected $eavConfig;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var Monolog
     */
    protected $logger;

    /**
     * @param Config $config
     * @param Gtin $gtinConfig
     * @param Image $imageHelper
     * @param Product $product TurnTo product helper (SKU encoding, etc.)
     * @param EavConfig $eavConfig
     * @param PriceCurrencyInterface $priceCurrency
     * @param Monolog $logger
     */
    public function __construct(
        Config $config,
        Gtin $gtinConfig,
        Image $imageHelper,
        Product $product,
        EavConfig $eavConfig,
        PriceCurrencyInterface $priceCurrency,
        Monolog $logger
    ) {
        $this->config = $config;
        $this->gtinConfig = $gtinConfig;
        $this->imageHelper = $imageHelper;
        $this->product = $product;
        $this->eavConfig = $eavConfig;
        $this->priceCurrency = $priceCurrency;
        $this->logger = $logger;
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
     * @param CatalogProduct $product
     * @param array $gtinMap
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
     * @param CatalogProduct $product
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
     * @param array $categoryBranch
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
            return $this->product->turnToSafeEncoding($parent->getSku());
        } else {
            return $this->product->turnToSafeEncoding($product->getSku());
        }
    }
}
