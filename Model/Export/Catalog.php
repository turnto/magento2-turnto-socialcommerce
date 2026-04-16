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
                try {
                    $page = 1;
                    $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
                    $feedFormat = $this->config->getFeedFormat($storeId);
                    $generator = $this->feedGeneratorFactory->create($feedFormat);
                    $feedStyle = $generator->getFeedStyle();

                    $batchSize = 10000;
                    $pageSize = 500;
                    $pagesPerBatch = ceil($batchSize / $pageSize);
                    $productCount = 0;
                    $fileIndex = 1;

                    $generator->beginFeed($store);

                    while ($products = $this->getProducts($storeId, $page, $pageSize)) {
                        try {
                            $productIds = [];
                            foreach ($products as $product) {
                                $productIds[] = $product->getId();
                            }
                            $this->exportProduct->preloadRewriteUrls($storeId, $productIds);

                            $childProducts = [];
                            if ($this->config->getUseChildSku($storeId)) {
                                $configurableIds = [];
                                $parentMap = [];
                                foreach ($products as $product) {
                                    if ($product->getTypeId() === Configurable::TYPE_CODE) {
                                        $configurableIds[] = $product->getId();
                                        $parentMap[$product->getId()] = $product;
                                    }
                                }

                                if (!empty($configurableIds)) {
                                    $connection = $this->resourceConnection->getConnection();
                                    $select = $connection->select()
                                        ->from(['l' => $connection->getTableName('catalog_product_super_link')], ['parent_id', 'product_id'])
                                        ->join(['e' => $connection->getTableName('catalog_product_entity')], 'e.entity_id = l.product_id', ['sku'])
                                        ->where('l.parent_id IN (?)', $configurableIds);
                                    $rows = $connection->fetchAll($select);

                                    foreach ($rows as $row) {
                                        $childProducts[$row['sku']] = $parentMap[$row['parent_id']];
                                    }
                                }
                            }

                            foreach ($products as $product) {
                                $parent = isset($childProducts[$product->getSku()]) ? $childProducts[$product->getSku()] : false;
                                $generator->addProduct($product, $parent, $storeId);
                                $productCount++;
                            }

                            $products->clear();
                            unset($products);
                            gc_collect_cycles();

                            if ($productCount >= $batchSize) {
                                $feedData = $generator->finishFeed();
                                $totalFiles = ceil($this->totalPages / $pagesPerBatch);
                                $fileName = sprintf('%s_of_%s_store_%s_%s', $fileIndex, $totalFiles, $storeId, $feedStyle);
                                $this->feedClient->transmitFeedFile($feedData, $fileName, $feedStyle, $store->getCode());

                                $productCount = 0;
                                $fileIndex++;
                                $generator->beginFeed($store);
                            }
                        } catch (Exception $e) {
                            $this->logger->error(
                                "TurnTo catalog export error sending page $page.",
                                [
                                    'exception' => $e
                                ]
                            );
                        }
                        $page++;
                    }

                    if ($productCount > 0) {
                        $feedData = $generator->finishFeed();
                        $totalFiles = ceil($this->totalPages / $pagesPerBatch);
                        $fileName = sprintf('%s_of_%s_store_%s_%s', $fileIndex, $totalFiles, $storeId, $feedStyle);
                        $this->feedClient->transmitFeedFile($feedData, $fileName, $feedStyle, $store->getCode());
                    }
                } catch (Exception $e) {
                    $this->logger->error(
                        'Catalog export error',
                        [
                            'exception' => $e,
                            'storeId' => $storeId,
                        ]
                    );
                } finally {
                    $this->emulation->stopEnvironmentEmulation();
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
     * @return Collection|false
     * @throws LocalizedException
     */
    public function getProducts($storeId, $page, $pageCount = 500)
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

        //used to generate file name 1_of_$totalPages.xml
        $this->totalPages = $collection->getLastPageNumber();

        //stop the feed once we get to the last page
        if ($this->totalPages < $page) {
            return false;
        }

        return $collection;
    }
}
