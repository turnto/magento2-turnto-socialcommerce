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
use TurnTo\SocialCommerce\Model\Export\Product as ExportProduct;
use TurnTo\SocialCommerce\Service\Feed\FeedGeneratorFactory;

/**
 * Export Catalog
 */
class Catalog
{
    const MAX_TRANSMISSION_ATTEMPTS = 3;

    /**
     * Delay between retry attempts in microseconds.
     *
     * usleep() expects microseconds, so keep the suffix for clarity.
     */
    const TRANSMISSION_RETRY_DELAY_MICROSECONDS = 0;

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
        ExportProduct         $exportProduct
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
                $emulationStarted = false;
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
                    $page = 1;
                    $productCount = 0;
                    $fileIndex = 1;
                    $products = $this->getProducts($storeId, $page, $pageSize);
                    if (!$products) {
                        continue;
                    }
                    $totalPages = $this->totalPages;
                    $totalFiles = $totalPages > 0 ? ceil($totalPages / $pagesPerBatch) : 0;

                    $generator->beginFeed($store);

                    while (true) {
                        try {
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
                                        ->join(['ce' => $connection->getTableName('catalog_product_entity')], 'ce.entity_id = l.product_id', ['child_sku' => 'sku'])
                                        ->join(['pe' => $connection->getTableName('catalog_product_entity')], 'pe.entity_id = l.parent_id', ['parent_sku' => 'sku'])
                                        ->where('l.product_id IN (?)', $simpleIds);
                                    $rows = $connection->fetchAll($select);

                                    if (!empty($rows)) {
                                        $parentIdsToLoad = [];
                                        $parentMap = [];
                                        $emptyCollection = $this->productCollectionFactory->create();

                                        foreach ($rows as $row) {
                                            $parentId = (int)$row['parent_id'];
                                            $parentIdsToLoad[] = $parentId;

                                            if (!isset($parentMap[$parentId])) {
                                                $parentProduct = $emptyCollection->getNewEmptyItem();
                                                $parentProduct->setData([
                                                    'entity_id' => $parentId,
                                                    'sku' => $row['parent_sku'],
                                                    'store_id' => $storeId
                                                ]);
                                                $parentMap[$parentId] = $parentProduct;
                                            }

                                            $childProducts[$row['child_sku']] = $parentMap[$parentId];
                                        }

                                        $productIds = array_merge($productIds, array_unique($parentIdsToLoad));

                                        unset($emptyCollection, $parentMap);
                                    }
                                }
                            }

                            $this->exportProduct->preloadRewriteUrls($storeId, $productIds);

                            foreach ($products as $product) {
                                $parent = isset($childProducts[$product->getSku()]) ? $childProducts[$product->getSku()] : false;
                                if ($generator->addProduct($product, $parent, $storeId)) {
                                    $productCount++;
                                }
                            }

                            $products->clear();
                            unset($products);

                            if ($productCount >= $batchSize) {
                                $feedData = $generator->finishFeed();
                                $fileName = sprintf('%s_of_%s_store_%s_%s', $fileIndex, $totalFiles, $storeId, $feedStyle);

                                try {
                                    $attempts = 0;
                                    while (true) {
                                        try {
                                            $this->feedClient->transmitFeedFile($feedData, $fileName, $feedStyle, $store->getCode());
                                            break;
                                        } catch (Exception $transmitException) {
                                            $attempts++;
                                            if ($attempts >= self::MAX_TRANSMISSION_ATTEMPTS) {
                                                throw $transmitException;
                                            }
                                            $this->logger->warning(
                                                'TurnTo catalog export transmit file failed; retrying',
                                                [
                                                    'storeId' => $storeId,
                                                    'file_name' => $fileName,
                                                    'page' => $page,
                                                    'batch_size' => $batchSize,
                                                    'file_index' => $fileIndex,
                                                    'attempt' => $attempts,
                                                    'exception' => $transmitException
                                                ]
                                            );
                                            usleep(self::TRANSMISSION_RETRY_DELAY_MICROSECONDS);
                                        }
                                    }
                                } catch (Exception $e) {
                                    $this->logger->error(
                                        'TurnTo catalog export transmit file error',
                                        [
                                            'storeId' => $storeId,
                                            'file_name' => $fileName,
                                            'page' => $page,
                                            'batch_size' => $batchSize,
                                            'file_index' => $fileIndex,
                                            'exception' => $e
                                        ]
                                    );
                                    throw $e;
                                }

                                $productCount = 0;
                                $fileIndex++;
                                $generator->beginFeed($store);
                            }
                        } catch (Exception $e) {
                            $this->logger->error(
                                'TurnTo catalog export error sending page',
                                [
                                    'store_id' => $storeId,
                                    'store_code' => $store->getCode(),
                                    'page' => $page,
                                    'exception' => $e
                                ]
                            );
                            throw $e;
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

                    if ($productCount > 0) {
                        $feedData = $generator->finishFeed();
                        $totalFiles = ceil($this->totalPages / $pagesPerBatch);
                        $fileName = sprintf('%s_of_%s_store_%s_%s', $fileIndex, $totalFiles, $storeId, $feedStyle);
                        $attempts = 0;
                        while (true) {
                            try {
                                $this->feedClient->transmitFeedFile($feedData, $fileName, $feedStyle, $store->getCode());
                                break;
                                } catch (Exception $transmitException) {
                                $attempts++;
                                if ($attempts >= self::MAX_TRANSMISSION_ATTEMPTS) {
                                        throw $transmitException;
                                }
                                $this->logger->warning(
                                    'TurnTo catalog export transmit file failed; retrying',
                                    [
                                        'storeId' => $storeId,
                                        'file_name' => $fileName,
                                        'attempt' => $attempts,
                                        'exception' => $transmitException
                                    ]
                                );
                                usleep(self::TRANSMISSION_RETRY_DELAY_MICROSECONDS);
                            }
                        }
                    }
                } catch (Exception $e) {
                    $this->logger->error(
                        'Catalog export error',
                        [
                            'exception' => $e,
                            'store_id' => $storeId,
                            'store_code' => $store->getCode(),
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

        $collection->joinField(
            'qty',
            'cataloginventory_stock_item',
            'qty',
            'product_id=entity_id',
            '{{table}}.stock_id=1',
            'left'
        )->joinField(
            'is_in_stock',
            'cataloginventory_stock_item',
            'is_in_stock',
            'product_id=entity_id',
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
