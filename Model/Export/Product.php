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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use TurnTo\SocialCommerce\Logger\Monolog;

class Product
{
    /**
     * @var array
     */
    protected $rewriteUrls;

    /**
     * @var
     */
    protected $storeId;

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
     * @param CatalogProduct $product
     * @param $storeId
     * @return string
     */
    public function getProductUrl(CatalogProduct $product, $storeId)
    {
        if (!$this->rewriteUrls){
            $this->getRewriteUrls($storeId);
        }

        if (isset($this->rewriteUrls[$product->getId()])) {
            return $this->rewriteUrls[$product->getId()];
        }

        return $product->getProductUrl();
    }

    /**
     * @param $relativeUrl
     * @param $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getAbsoluteUrl($relativeUrl, $storeId)
    {
        $storeUrl = $this->storeManager->getStore($storeId)->getBaseUrl();
        return rtrim($storeUrl, '/') . '/' . ltrim($relativeUrl, '/');
    }

    /**
     * @param $storeId
     * @return void
     */
    protected function getRewriteUrls($storeId)
    {
        $this->rewriteUrls = [];
        $urlRewrites = $this->urlFinder->findAllByData(
            [
                UrlRewrite::ENTITY_TYPE =>
                    ProductUrlRewriteGenerator::ENTITY_TYPE,
                UrlRewrite::STORE_ID => $storeId
            ]
        );
        foreach ($urlRewrites as $urlRewrite) {
            try {
                $this->rewriteUrls[$urlRewrite->getEntityId()] = $this->getAbsoluteUrl($urlRewrite->getRequestPath(), $storeId);
            } catch (Exception $e) {
                $this->logger->error($e);
            }
        }
    }
}
