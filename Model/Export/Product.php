<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Export;

use Exception;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use TurnTo\SocialCommerce\Logger\Monolog;

/**
 * Export Product
 */
class Product
{
    /**
     * @var array<int, string>|null
     */
    protected $rewriteUrls;

    /**
     * @var int|null
     */
    protected $cachedStoreId;

    /**
     * @var UrlFinderInterface
     */
    protected $urlFinder;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var Monolog
     */
    protected $logger;

    /**
     * @param UrlFinderInterface $urlFinder
     * @param StoreManagerInterface $storeManager
     * @param Monolog $logger
     */
    public function __construct(
        UrlFinderInterface $urlFinder,
        StoreManagerInterface $storeManager,
        Monolog $logger
    ) {
        $this->urlFinder = $urlFinder;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Required to get beautified urls in cron (see https://github.com/magento/magento2/issues/3074)
     *
     * @param CatalogProduct $product
     * @param int|string $storeId
     * @return string
     */
    public function getProductUrl(CatalogProduct $product, $storeId)
    {
        $storeId = (int) $storeId;

        if ($this->cachedStoreId !== $storeId) {
            $this->cachedStoreId = $storeId;
            $this->rewriteUrls = null;
        }

        if ($this->rewriteUrls !== null) {
            $productId = $product->getId();
            if (isset($this->rewriteUrls[$productId])) {
                return $this->rewriteUrls[$productId];
            }
        }

        return $this->resolveProductUrlWithoutPreloadedRewrites($product, $storeId);
    }

    /**
     * Native URL resolution with explicit store scope (covers order export, CLI, and cache misses).
     *
     * @param CatalogProduct $product
     * @param int $storeId
     * @return string
     */
    protected function resolveProductUrlWithoutPreloadedRewrites(CatalogProduct $product, int $storeId)
    {
        $originalStoreId = $product->getStoreId();
        try {
            $product->setStoreId($storeId);
            return $product->getProductUrl();
        } finally {
            $product->setStoreId($originalStoreId);
        }
    }

    /**
     * Build an absolute URL from a store-relative request path.
     *
     * @param string $relativeUrl
     * @param string $storeUrl
     * @return string
     */
    protected function getAbsoluteUrl($relativeUrl, $storeUrl)
    {
        return rtrim($storeUrl, '/') . '/' . ltrim($relativeUrl, '/');
    }

    /**
     * Batch-load product rewrites for the given store to avoid repeated lookups during catalog feed generation.
     *
     * When several rewrites exist per product (category paths), the canonical product rewrite is preferred.
     *
     * @param int|string $storeId
     * @param int[] $productIds
     * @return void
     */
    public function preloadRewriteUrls($storeId, array $productIds)
    {
        $storeId = (int) $storeId;
        $this->cachedStoreId = $storeId;
        $this->rewriteUrls = [];

        if ($productIds === []) {
            return;
        }

        $productIds = array_values(array_unique(array_map('intval', $productIds)));

        $candidates = [];
        try {
            $urlRewrites = $this->urlFinder->findAllByData(
                [
                    UrlRewrite::ENTITY_TYPE => ProductUrlRewriteGenerator::ENTITY_TYPE,
                    UrlRewrite::STORE_ID => $storeId,
                    UrlRewrite::REDIRECT_TYPE => 0,
                    UrlRewrite::ENTITY_ID => $productIds,
                ]
            );
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return;
        }

        try {
            $storeUrl = $this->storeManager->getStore($storeId)->getBaseUrl();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return;
        }

        foreach ($urlRewrites as $urlRewrite) {
            try {
                $entityId = $urlRewrite->getEntityId();
                $candidates[$entityId][] = [
                    'url' => $this->getAbsoluteUrl($urlRewrite->getRequestPath(), $storeUrl),
                    'rewrite' => $urlRewrite,
                ];
            } catch (Exception $e) {
                $this->logger->error($e->getMessage(), ['exception' => $e]);
            }
        }

        foreach ($candidates as $entityId => $rows) {
            $this->rewriteUrls[$entityId] = $this->pickPreferredProductRewriteUrl($rows);
        }
    }

    /**
     * Prefer canonical product rewrites (no category metadata) over category-scoped paths.
     *
     * @param array<int, array{url: string, rewrite: UrlRewrite}> $rows
     * @return string
     */
    protected function pickPreferredProductRewriteUrl(array $rows)
    {
        foreach ($rows as $row) {
            if ($this->isCanonicalProductRewrite($row['rewrite'])) {
                return $row['url'];
            }
        }

        // Fallback: Sort deterministically to ensure consistent exports
        // Prefer shorter URLs (fewer category segments), then sort alphabetically
        usort($rows, function ($a, $b) {
            $pathA = $a['rewrite']->getRequestPath();
            $pathB = $b['rewrite']->getRequestPath();

            $lengthComparison = strlen($pathA) <=> strlen($pathB);
            if ($lengthComparison !== 0) {
                return $lengthComparison;
            }

            return strcmp($pathA, $pathB);
        });

        return $rows[0]['url'];
    }

    /**
     * Whether the rewrite is the product-level URL (not scoped to a category path).
     *
     * @param UrlRewrite $rewrite
     * @return bool
     */
    protected function isCanonicalProductRewrite(UrlRewrite $rewrite)
    {
        $metadata = $rewrite->getMetadata() ?? [];

        return empty($metadata['category_id']) && empty($metadata['redirect_type']);
    }
}
