<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use Exception;
use ArrayIterator;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Area;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Framework\App\ResourceConnection;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Api\FeedGeneratorInterface;
use TurnTo\SocialCommerce\Model\Config as ConfigModel;
use TurnTo\SocialCommerce\Model\Config\Source\FeedFormat;
use TurnTo\SocialCommerce\Model\Config\Gtin;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Export\Catalog;
use TurnTo\SocialCommerce\Model\Export\CategoryPathResolver;
use TurnTo\SocialCommerce\Model\Export\Product;
use TurnTo\SocialCommerce\Service\Feed\FeedGeneratorFactory;

class CatalogTest extends TestCase
{
    /**
     * @var Catalog
     */
    protected $catalog;
    /**
     * @var ConfigModel
     */
    protected $config;
    /**
     * @var Gtin
     */
    protected $gtin;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var array
     */
    protected $configValues = [];
    /**
     * @var string
     */
    protected $feedFormat = FeedFormat::COMMERCE;
    /**
     * @var string
     */
    protected $siteKey = 'site-key';
    /**
     * @var string
     */
    protected $authorizationKey = 'auth-key';
    /**
     * @var bool
     */
    protected $useChildSku = false;
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;
    /**
     * @var FeedClient
     */
    protected $feedClient;
    /**
     * @var Emulation
     */
    protected $emulation;
    /**
     * @var Monolog
     */
    protected $logger;
    /**
     * @var FeedGeneratorFactory
     */
    protected $feedGeneratorFactory;
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;
    /**
     * @var Product
     */
    protected $exportProduct;
    /**
     * @var CategoryPathResolver
     */
    protected $categoryPathResolver;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigModel::class);
        $this->gtin = $this->createMock(Gtin::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->feedClient = $this->createMock(FeedClient::class);
        $this->emulation = $this->createMock(Emulation::class);
        $this->logger = $this->createMock(Monolog::class);
        $this->feedGeneratorFactory = $this->createMock(FeedGeneratorFactory::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->exportProduct = $this->createMock(Product::class);
        $this->categoryPathResolver = $this->createMock(CategoryPathResolver::class);

        $this->configValues = [
            ConfigModel::PRODUCT_ENABLE_AUTOMATIC_SUBMISSION => true,
            ConfigModel::PRODUCT_FEED_SUBMISSION_URL => 'https://feed.test/upload'
        ];

        $this->config->method('getConfigValue')->willReturnCallback(
            function ($path, $scopeCode = null) {
                return $this->configValues[$path] ?? null;
            }
        );
        $this->config->method('getFeedFormat')->willReturnCallback(function ($storeId = null) {
            return $this->feedFormat;
        });
        $this->config->method('getSiteKey')->willReturnCallback(function ($storeId = null) {
            return $this->siteKey;
        });
        $this->config->method('getAuthorizationKey')->willReturnCallback(function ($storeId = null) {
            return $this->authorizationKey;
        });
        $this->config->method('getUseChildSku')->willReturnCallback(function ($storeId = null) {
            return $this->useChildSku;
        });
        $this->config->method('getIsEnabled')->willReturn(true);

        $this->gtin->method('getGtinAttributesMap')->willReturn([]);

        $this->catalog = new Catalog(
            $this->config,
            $this->gtin,
            $this->storeManager,
            $this->collectionFactory,
            $this->feedClient,
            $this->emulation,
            $this->logger,
            $this->feedGeneratorFactory,
            $this->resourceConnection,
            $this->exportProduct,
            $this->categoryPathResolver
        );
    }

    protected function createCatalog()
    {
        return new Catalog(
            $this->config,
            $this->gtin,
            $this->storeManager,
            $this->collectionFactory,
            $this->feedClient,
            $this->emulation,
            $this->logger,
            $this->feedGeneratorFactory,
            $this->resourceConnection,
            $this->exportProduct,
            $this->categoryPathResolver
        );
    }

    protected function createProductCollection(array $products, int $totalPages = 1)
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('setStoreId')->willReturnSelf();
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addUrlRewrite')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('setPage')->willReturnSelf();
        $collection->method('joinField')->willReturnSelf();
        $collection->method('addStoreFilter')->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new ArrayIterator($products));
        $collection->method('clear')->willReturnSelf();
        $collection->method('getLastPageNumber')->willReturn($totalPages);

        return $collection;
    }

    protected function createStore(int $storeId = 1, string $storeCode = 'default')
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);
        $store->method('getCode')->willReturn($storeCode);
        return $store;
    }

    public function testIsCatalogClass()
    {
        $this->assertInstanceOf(Catalog::class, $this->catalog);
    }

    public function testCronUploadFeedDoesNotTransmitEmptyBatchWhenNoProductsAreAdded()
    {
        $storeId = 1;
        $store = $this->createStore($storeId);

        $this->storeManager->method('getStores')->willReturn([$store]);

        $this->configValues[ConfigModel::PRODUCT_FEED_SUBMISSION_URL] = 'https://feed.test/upload';

        $generator = $this->createMock(FeedGeneratorInterface::class);
        $generator->expects($this->once())->method('getFeedStyle')->willReturn(FeedFormat::COMMERCE);
        $generator->expects($this->never())->method('beginFeed');
        $generator->expects($this->once())->method('addProduct')->willReturn(false);
        $generator->expects($this->never())->method('finishFeed');
        $this->feedGeneratorFactory->method('create')->with(FeedFormat::COMMERCE)->willReturn($generator);

        $product = $this->createMock(CatalogProduct::class);
        $product->method('getId')->willReturn(1);
        $product->method('getSku')->willReturn('SKU1');
        $product->method('getTypeId')->willReturn('simple');

        $this->collectionFactory->method('create')->willReturn($this->createProductCollection([$product]));

        $this->exportProduct->expects($this->once())->method('preloadRewriteUrls')->with($storeId, [1]);
        $this->categoryPathResolver->expects($this->once())->method('preloadCategoryPaths')->with($storeId, [1]);
        $this->feedClient->expects($this->never())->method('transmitFeedFile');
        $this->logger->expects($this->never())->method('error');
        $this->emulation->expects($this->once())
            ->method('startEnvironmentEmulation')
            ->with($storeId, Area::AREA_FRONTEND, true);
        $this->emulation->expects($this->once())->method('stopEnvironmentEmulation');

        $this->catalog = $this->createCatalog();
        $this->catalog->cronUploadFeed();
    }

    public function testCronUploadFeedLogsAndStopsWhenTransmitFails()
    {
        $storeId = 1;
        $store = $this->createStore($storeId);

        $this->storeManager->method('getStores')->willReturn([$store]);

        $this->configValues[ConfigModel::PRODUCT_FEED_SUBMISSION_URL] = 'https://feed.test/upload';
        $this->gtin->method('getGtinAttributesMap')->willReturn([]);

        $generator = $this->createMock(FeedGeneratorInterface::class);
        $generator->method('getFeedStyle')->willReturn(FeedFormat::COMMERCE);
        $generator->expects($this->once())->method('beginFeed')->with($store)->willReturn(null);
        $generator->expects($this->once())->method('addProduct')->willReturn(true);
        $generator->method('finishFeed')->willReturn('feed');
        $this->feedGeneratorFactory->method('create')->with(FeedFormat::COMMERCE)->willReturn($generator);

        $product = $this->createMock(CatalogProduct::class);
        $product->method('getId')->willReturn(1);
        $product->method('getSku')->willReturn('SKU1');
        $product->method('getTypeId')->willReturn('simple');

        $this->collectionFactory->method('create')->willReturn($this->createProductCollection([$product]));

        $this->exportProduct->expects($this->once())->method('preloadRewriteUrls')->with($storeId, [1]);
        $this->categoryPathResolver->expects($this->once())->method('preloadCategoryPaths')->with($storeId, [1]);
        $this->feedClient->expects($this->once())
            ->method('transmitFeedFile')
            ->willThrowException(new Exception('transmit failed'));
        $this->logger->expects($this->atLeastOnce())->method('error');
        $this->emulation->expects($this->once())
            ->method('startEnvironmentEmulation')
            ->with($storeId, Area::AREA_FRONTEND, true);
        $this->emulation->expects($this->once())->method('stopEnvironmentEmulation');

        $this->catalog = $this->createCatalog();
        $this->catalog->cronUploadFeed();
    }

    public function testGetProductsAddsDeterministicOrder()
    {
        $productCollection = $this->createProductCollection([], 3);
        $productCollection->expects($this->once())->method('setOrder')->with('entity_id', 'ASC');
        $this->collectionFactory->method('create')->willReturn($productCollection);

        $this->catalog = $this->createCatalog();
        $result = $this->catalog->getProducts(1, 1, 500);

        $this->assertSame($productCollection, $result);
    }

    public function testGetProductsReturnsFalseWhenPageExceedsLastPage()
    {
        $productCollection = $this->createProductCollection([], 1);
        $this->collectionFactory->method('create')->willReturn($productCollection);

        $this->catalog = $this->createCatalog();
        $result = $this->catalog->getProducts(1, 3, 500);

        $this->assertFalse($result);
    }

    public function testCronUploadFeedSkipsStoreIfFeedFormatInvalid()
    {
        $storeId = 1;
        $store = $this->createStore($storeId);

        $this->storeManager->method('getStores')->willReturn([$store]);
        $this->configValues[ConfigModel::PRODUCT_FEED_SUBMISSION_URL] = 'https://feed.test/upload';
        $this->feedFormat = 'invalid_format';

        $this->feedGeneratorFactory->expects($this->never())->method('create');
        $this->collectionFactory->expects($this->never())->method('create');
        $this->feedClient->expects($this->never())->method('transmitFeedFile');
        $this->emulation->expects($this->never())->method('startEnvironmentEmulation');
        $this->emulation->expects($this->never())->method('stopEnvironmentEmulation');
        $this->logger->expects($this->once())->method('error');

        $this->catalog->cronUploadFeed();
    }

    public function testCronUploadFeedSkipsStoreIfCredentialsIncomplete()
    {
        $storeId = 1;
        $store = $this->createStore($storeId);

        $this->storeManager->method('getStores')->willReturn([$store]);
        $this->configValues[ConfigModel::PRODUCT_FEED_SUBMISSION_URL] = 'https://feed.test/upload';
        $this->siteKey = '';

        $this->feedGeneratorFactory->expects($this->never())->method('create');
        $this->collectionFactory->expects($this->never())->method('create');
        $this->feedClient->expects($this->never())->method('transmitFeedFile');
        $this->logger->expects($this->once())->method('error');
        $this->emulation->expects($this->never())->method('startEnvironmentEmulation');
        $this->emulation->expects($this->never())->method('stopEnvironmentEmulation');

        $this->catalog->cronUploadFeed();
    }

    public function testCronUploadFeedTransmitsFeedFileOnce()
    {
        $storeId = 1;
        $store = $this->createStore($storeId);

        $this->storeManager->method('getStores')->willReturn([$store]);
        $this->configValues[ConfigModel::PRODUCT_FEED_SUBMISSION_URL] = 'https://feed.test/upload';

        $generator = $this->createMock(FeedGeneratorInterface::class);
        $generator->method('getFeedStyle')->willReturn(FeedFormat::COMMERCE);
        $generator->expects($this->once())->method('beginFeed')->with($store)->willReturn(null);
        $generator->expects($this->once())->method('addProduct')->willReturn(true);
        $generator->method('finishFeed')->willReturn('feed');
        $this->feedGeneratorFactory->method('create')->with(FeedFormat::COMMERCE)->willReturn($generator);

        $product = $this->createMock(CatalogProduct::class);
        $product->method('getId')->willReturn(1);
        $product->method('getSku')->willReturn('SKU1');
        $product->method('getTypeId')->willReturn('simple');

        $this->collectionFactory->method('create')->willReturn($this->createProductCollection([$product]));

        $this->exportProduct->expects($this->once())->method('preloadRewriteUrls')->with($storeId, [1]);
        $this->categoryPathResolver->expects($this->once())->method('preloadCategoryPaths')->with($storeId, [1]);
        $this->feedClient->expects($this->once())
            ->method('transmitFeedFile')
            ->willReturn(null);
        $this->logger->expects($this->never())->method('error');

        $this->catalog = $this->createCatalog();
        $this->catalog->cronUploadFeed();
    }
}
