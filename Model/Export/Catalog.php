<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Export;

use Exception;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\App\Emulation;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Config\Gtin;
use TurnTo\SocialCommerce\Model\Config\Source\FeedFormat;
use TurnTo\SocialCommerce\Model\Export\CategoryPathResolver;
use TurnTo\SocialCommerce\Model\Export\Product as ExportProduct;
use TurnTo\SocialCommerce\Service\Feed\FeedGeneratorFactory;

/**
 * Export Catalog
 */
class Catalog
{
    /**
     * @var FeedGeneratorFactory
     */
    protected $feedGeneratorFactory;
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
     * @var Gtin
     */
    protected $gtinConfig;
    /**
     * @var Emulation
     */
    protected $emulation;
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;
    /**
     * @var ExportProduct
     */
    protected $exportProduct;

    /**
     * @var CategoryPathResolver
     */
    protected $categoryPathResolver;

    /**
     * @var Int
     */
    protected $totalPages;

    /**
     * Catalog constructor.
     *
     * @param Config $config
     * @param Gtin $gtinConfig
     * @param StoreManagerInterface $storeManager
     * @param CollectionFactory $productCollectionFactory
     * @param FeedClient $feedClient
     * @param Emulation $emulation
     * @param Monolog $logger
     * @param FeedGeneratorFactory $feedGeneratorFactory
     * @param ResourceConnection $resourceConnection
     * @param ExportProduct $exportProduct
     * @param CategoryPathResolver $categoryPathResolver
     */
    public function __construct(
        Config                $config,
        Gtin                  $gtinConfig,
        StoreManagerInterface $storeManager,
        CollectionFactory     $productCollectionFactory,
        FeedClient            $feedClient,
        Emulation             $emulation,
        Monolog               $logger,
        FeedGeneratorFactory  $feedGeneratorFactory,
        ResourceConnection    $resourceConnection,
        ExportProduct         $exportProduct,
        CategoryPathResolver  $categoryPathResolver
    ) {
        $this->config = $config;
        $this->gtinConfig = $gtinConfig;
        $this->storeManager = $storeManager;
        $this->feedClient = $feedClient;
        $this->logger = $logger;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->emulation = $emulation;
        $this->feedGeneratorFactory = $feedGeneratorFactory;
        $this->resourceConnection = $resourceConnection;
        $this->exportProduct = $exportProduct;
        $this->categoryPathResolver = $categoryPathResolver;
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
                $this->config->getConfigBool(Config::PRODUCT_ENABLE_AUTOMATIC_SUBMISSION, $storeId)
            ) {
                $emulationStarted = false;
                $page = 1;
                $productCount = 0;
                $pagesInCurrentFile = 0;
                $fileIndex = 1;
                $generator = null;
                $batchSize = null;
                $currentFeedFile = null;
                try {
                    $feedFormat = $this->config->getFeedFormat($storeId);
                    $siteKey = $this->config->getSiteKey($storeId);
                    $authorizationKey = $this->config->getAuthorizationKey($storeId);
                    $submissionUrl = $this->config->getConfigValue(Config::PRODUCT_FEED_SUBMISSION_URL, $storeId);

                    if (!in_array($feedFormat, [FeedFormat::GOOGLE_PRODUCT, FeedFormat::COMMERCE], true)) {
                        $this->logger->error(
                            'TurnTo catalog export skipped due to unsupported feed format',
                            [
                                'store_id' => $storeId,
                                'feed_format' => $feedFormat
                            ]
                        );
                        continue;
                    }

                    if (empty($siteKey) || empty($authorizationKey) || empty($submissionUrl)) {
                        $this->logger->error(
                            'TurnTo catalog export skipped due to incomplete TurnTo credentials',
                            [
                                'store_id' => $storeId,
                                'site_key_set' => !empty($siteKey),
                                'auth_key_set' => !empty($authorizationKey),
                                'submission_url_set' => !empty($submissionUrl)
                            ]
                        );
                        continue;
                    }

                    $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
                    $emulationStarted = true;
                    $generator = $this->feedGeneratorFactory->create($feedFormat);
                    $feedStyle = $generator->getFeedStyle();

                    $batchSize = 10000;
                    $pageSize = 500;
                    $pagesPerBatch = ceil($batchSize / $pageSize);
                    $products = $this->getProducts($storeId, $page, $pageSize);
                    if (!$products) {
                        continue;
                    }
                    $totalPages = $this->totalPages;
                    $totalFiles = $totalPages > 0 ? ceil($totalPages / $pagesPerBatch) : 0;

                    while (true) {
                        $productIds = [];
                        foreach ($products as $product) {
                            $productIds[] = $product->getId();
                        }

                        $childProducts = [];
                        if ($this->config->getUseChildSku($storeId)) {
                            $simpleIds = [];
                            foreach ($products as $product) {
                                if ($product->getTypeId() !== Configurable::TYPE_CODE) {
                                    $simpleIds[] = $product->getId();
                                }
                            }

                            if (!empty($simpleIds)) {
                                $connection = $this->resourceConnection->getConnection();
                                $select = $connection->select()
                                    ->from(['l' => $connection->getTableName('catalog_product_super_link')], ['parent_id', 'product_id'])
                                    ->join(['pe' => $connection->getTableName('catalog_product_entity')], 'pe.entity_id = l.parent_id', ['parent_sku' => 'sku'])
                                    ->where('l.product_id IN (?)', $simpleIds);
                                $rows = $connection->fetchAll($select);

                                if (!empty($rows)) {
                                    $parentMap = [];
                                    $emptyCollection = $this->productCollectionFactory->create();
                                    $parentIds = array_values(array_unique(array_map('intval', array_column($rows, 'parent_id'))));
                                    $parentCollection = $this->productCollectionFactory->create();
                                    $parentCollection->setStoreId($storeId)
                                        ->addAttributeToSelect(['url_key', 'url_path'])
                                        ->addFieldToFilter('entity_id', ['in' => $parentIds]);
                                    $loadedParentProducts = [];
                                    foreach ($parentCollection as $parentProduct) {
                                        $loadedParentProducts[(int) $parentProduct->getId()] = $parentProduct;
                                    }

                                    foreach ($rows as $row) {
                                        $parentId = (int) $row['parent_id'];

                                        if (!isset($parentMap[$parentId])) {
                                            if (isset($loadedParentProducts[$parentId])) {
                                                $parentProduct = $loadedParentProducts[$parentId];
                                            } else {
                                                $parentProduct = $emptyCollection->getNewEmptyItem();
                                                $parentProduct->setData([
                                                    'entity_id' => $parentId,
                                                    'sku' => $row['parent_sku'],
                                                    'type_id' => 'configurable',
                                                    'store_id' => $storeId
                                                ]);
                                            }

                                            $parentMap[$parentId] = $parentProduct;
                                        }

                                        $childProducts[(int) $row['product_id']] = $parentMap[$parentId];
                                    }

                                    $productIds = array_merge($productIds, $parentIds);

                                    unset($emptyCollection, $parentMap);
                                }
                            }
                        }

                        $categoryProductIds = $productIds;
                        $this->exportProduct->preloadRewriteUrls($storeId, $productIds);
                        $this->categoryPathResolver->preloadCategoryPaths($storeId, $categoryProductIds);

                        if (!$generator->isFeedOpen()) {
                            $generator->beginFeed($store);
                        }

                        foreach ($products as $product) {
                            $parent = isset($childProducts[(int) $product->getId()]) ? $childProducts[(int) $product->getId()] : false;
                            if ($generator->addProduct($product, $parent, $storeId)) {
                                $productCount++;
                            }
                        }

                        $products->clear();
                        unset($products);

                        $pagesInCurrentFile++;
                        $isLastPage = ($page >= $totalPages);
                        $pageBatchComplete = ($pagesInCurrentFile >= $pagesPerBatch);
                        if (($pageBatchComplete || $isLastPage) && $generator->isFeedOpen()) {
                            $feedData = $generator->finishFeed();
                            $shouldTransmit = ($totalFiles > 1) || ($productCount > 0);
                            if ($shouldTransmit) {
                                $fileName = sprintf('%s_of_%s_store_%s_%s', $fileIndex, $totalFiles, $storeId, $feedStyle);
                                $currentFeedFile = $fileName;
                                $this->feedClient->transmitFeedFile($feedData, $fileName, $feedStyle, $store->getCode());
                                $fileIndex++;
                            }
                            $productCount = 0;
                            $pagesInCurrentFile = 0;
                        }
                        if ($page >= $totalPages) {
                            break;
                        }

                        $page++;
                        $products = $this->getProducts($storeId, $page, $pageSize, false);
                        if (!$products) {
                            break;
                        }
                    }

                    if ($generator->isFeedOpen()) {
                        $feedData = $generator->finishFeed();
                        $shouldTransmit = ($totalFiles > 1) || ($productCount > 0);
                        if ($shouldTransmit) {
                            $fileName = sprintf('%s_of_%s_store_%s_%s', $fileIndex, $totalFiles, $storeId, $feedStyle);
                            $currentFeedFile = $fileName;
                            $this->feedClient->transmitFeedFile($feedData, $fileName, $feedStyle, $store->getCode());
                        }
                    }
                } catch (Exception $e) {
                    if ($generator !== null && $generator->isFeedOpen()) {
                        try {
                            $generator->finishFeed();
                        } catch (Exception $finishException) {
                            $this->logger->error(
                                'TurnTo catalog export failed to finish feed',
                                [
                                    'store_id' => $storeId,
                                    'store_code' => $store->getCode(),
                                    'exception' => $finishException
                                ]
                            );
                        }
                    }

                    $this->logger->error(
                        'Catalog export error',
                        [
                            'exception' => $e,
                            'store_id' => $storeId,
                            'store_code' => $store->getCode(),
                            'page' => $page,
                            'file_name' => $currentFeedFile,
                            'batch_size' => $batchSize
                        ]
                    );
                } finally {
                    if ($emulationStarted) {
                        $this->emulation->stopEnvironmentEmulation();
                    }
                }
            }
        }
    }

    /**
     * Retrieves a store/visibility filtered product collection selecting only attributes necessary for the TurnTo Feed
     * overwritten to allow for pagination
     *
     * @param int|string $storeId
     * @param int $page
     * @param ?int $pageCount
     * @param bool $fetchTotalPages
     * @return Collection|false
     * @throws LocalizedException
     */
    public function getProducts($storeId, $page, $pageCount = 500, $fetchTotalPages = true)
    {
        $collection = $this->productCollectionFactory->create()
            ->setStoreId($storeId)
            ->addAttributeToSelect('url_key')
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('sku')
            ->addAttributeToSelect('image')
            ->addAttributeToSelect('price')
            ->addAttributeToSelect('status')
            ->addAttributeToSelect('turnto_disabled')
            ->addUrlRewrite()
            ->setOrder('entity_id', 'ASC')
            ->setPage($page, $pageCount);

        $collection->joinTable(
            ['stock_item' => 'cataloginventory_stock_item'],
            'product_id=entity_id',
            [
                'qty' => 'qty',
                'is_in_stock' => 'is_in_stock'
            ],
            '{{table}}.stock_id=1',
            'left'
        );

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

        if (!$fetchTotalPages && $this->totalPages > 0 && $page > $this->totalPages) {
            return false;
        }

        if ($fetchTotalPages) {
            //used to generate file name 1_of_$totalPages.xml
            $this->totalPages = $collection->getLastPageNumber();
            //stop the feed once we get to the last page
            if ($this->totalPages < $page) {
                return false;
            }
        }

        return $collection;
    }
}
