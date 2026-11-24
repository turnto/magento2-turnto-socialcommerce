<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Import;

use Magento\Catalog\Model\Indexer\Product\Flat\Processor as ProductFlatIndexerProcessor;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Product;
use TurnTo\SocialCommerce\Setup\InstallHelper;

class RatingsTest extends TestCase
{
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var Monolog
     */
    protected $logger;
    /**
     * @var ProductFactory
     */
    protected $productFactory;
    /**
     * @var ProductResource
     */
    protected $productResource;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;
    /**
     * @var Product
     */
    protected $product;
    /**
     * @var HttpClient
     */
    protected $httpClient;
    /**
     * @var ProductAction
     */
    protected $productAction;
    /**
     * @var ProductFlatIndexerProcessor
     */
    protected $productFlatIndexerProcessor;
    /**
     * @var array
     */
    protected $updateAttributesCalls = [];
    /**
     * @var array
     */
    protected $flatReindexCalls = [];
    /**
     * @var AbstractAttribute
     */
    protected $averageRatingAttribute;

    protected function setUp(): void
    {
        $this->config = $this->createStub(Config::class);
        $this->logger = $this->createStub(Monolog::class);
        $this->productFactory = $this->createStub(ProductFactory::class);
        $this->productResource = $this->createStub(ProductResource::class);
        $this->storeManager = $this->createStub(StoreManagerInterface::class);
        $this->productCollectionFactory = $this->createStub(CollectionFactory::class);
        $this->product = $this->createStub(Product::class);
        $this->httpClient = $this->createStub(HttpClient::class);
        $this->productAction = $this->createStub(ProductAction::class);
        $this->productFlatIndexerProcessor = $this->createStub(ProductFlatIndexerProcessor::class);

        $this->flatReindexCalls = [];
        $this->productFlatIndexerProcessor->method('reindexList')->willReturnCallback(
            function ($ids) {
                $this->flatReindexCalls[] = $ids;
            }
        );

        // SKUs pass through encoding/decoding unchanged for the test.
        $this->product->method('turnToSafeDecoding')->willReturnArgument(0);
        $this->product->method('turnToSafeEncoding')->willReturnArgument(0);

        // Capture every bulk attribute write so the test can assert grouping and counts.
        $this->updateAttributesCalls = [];
        $this->productAction->method('updateAttributes')->willReturnCallback(
            function ($entityIds, $attrData, $storeId) {
                $this->updateAttributesCalls[] = [
                    'ids' => $entityIds,
                    'data' => $attrData,
                    'storeId' => $storeId,
                ];

                return $this->productAction;
            }
        );

        // Resolve the average-rating option ids deterministically.
        $source = $this->createStub(AbstractSource::class);
        $source->method('getOptionId')->willReturn('opt');
        $this->averageRatingAttribute = $this->createStub(AbstractAttribute::class);
        $this->averageRatingAttribute->method('getSource')->willReturn($source);
        $this->productResource->method('getAttribute')->willReturn($this->averageRatingAttribute);
    }

    protected function createRatings(): TestableRatings
    {
        return new TestableRatings(
            $this->config,
            $this->logger,
            $this->productFactory,
            $this->productResource,
            $this->storeManager,
            $this->productCollectionFactory,
            $this->product,
            $this->httpClient,
            $this->productAction,
            $this->productFlatIndexerProcessor
        );
    }

    protected function createStore(int $storeId = 1, string $storeCode = 'default'): StoreInterface
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);
        $store->method('getCode')->willReturn($storeCode);

        return $store;
    }

    /**
     * @param int $id
     * @param int $reviewCount
     * @param float $rating
     * @return CatalogProduct
     */
    protected function createProduct(int $id, int $reviewCount = 0, float $rating = 0.0): CatalogProduct
    {
        $product = $this->createStub(CatalogProduct::class);
        $product->method('getId')->willReturn($id);
        $product->method('getData')->willReturnCallback(
            function ($key) use ($reviewCount, $rating) {
                if ($key === InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE) {
                    return $reviewCount;
                }
                if ($key === InstallHelper::RATING_ATTRIBUTE_CODE) {
                    return $rating;
                }

                return null;
            }
        );

        return $product;
    }

    public function testUpdateProductWritesSingleBulkCallAndNeverUsesSaveAttribute(): void
    {
        $store = $this->createStore();
        $product = $this->createProduct(42, 0, 0.0);

        // The per-attribute write path must be gone entirely.
        $this->productResource = $this->createMock(ProductResource::class);
        $this->productResource->method('getAttribute')->willReturn($this->averageRatingAttribute);
        $this->productResource->expects($this->never())->method('saveAttribute');

        $ratings = $this->createRatings();
        $result = $ratings->updateProduct($store, 'SKU42', 7, 4.0, $product);

        $this->assertTrue($result);
        $this->assertCount(1, $this->updateAttributesCalls);
        $call = $this->updateAttributesCalls[0];
        $this->assertSame([42], $call['ids']);
        $this->assertSame(1, $call['storeId']);
        $this->assertSame(7, $call['data'][InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE]);
        $this->assertSame(4.0, $call['data'][InstallHelper::RATING_ATTRIBUTE_CODE]);
        $this->assertSame('opt,opt,opt,opt', $call['data'][InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE]);
        $this->assertSame([[42]], $this->flatReindexCalls);
    }

    public function testUpdateProductSkipsWhenDataUnchanged(): void
    {
        $store = $this->createStore();
        $product = $this->createProduct(42, 7, 4.0);

        $ratings = $this->createRatings();
        $result = $ratings->updateProduct($store, 'SKU42', 7, 4.0, $product);

        $this->assertFalse($result);
        $this->assertCount(0, $this->updateAttributesCalls);
        $this->assertCount(0, $this->flatReindexCalls);
    }

    public function testUpdateProductSetsAverageRatingToZeroWhenRatingIsZero(): void
    {
        $store = $this->createStore();
        $product = $this->createProduct(42, 5, 4.0);

        $ratings = $this->createRatings();
        $ratings->updateProduct($store, 'SKU42', 0, 0.0, $product);

        $this->assertCount(1, $this->updateAttributesCalls);
        $this->assertSame('0', $this->updateAttributesCalls[0]['data'][InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE]);
    }

    public function testCronDownloadFeedGroupsIdenticalUpdatesIntoBulkCalls(): void
    {
        $store = $this->createStore(1, 'default');
        $this->storeManager->method('getStores')->willReturn([$store]);

        $this->config->method('getIsEnabled')->willReturn(true);
        $this->config->method('getSiteKey')->willReturn('site-key');
        $this->config->method('getAuthorizationKey')->willReturn('auth-key');
        $this->config->method('getConfigBool')->willReturnCallback(
            function ($path, $scopeCode = null) {
                return $path === Config::AVERAGE_RATING_IMPORT_ENABLED;
            }
        );

        // SKU1 and SKU3 share identical rating data, SKU2 differs by review count.
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<feed><products>'
            . '<product sku="SKU1" review_count="5">5</product>'
            . '<product sku="SKU2" review_count="3">5</product>'
            . '<product sku="SKU3" review_count="5">5</product>'
            . '</products></feed>';

        $this->productCollectionFactory->method('create')->willReturnCallback(
            function () {
                return $this->createEmptyResetCollection();
            }
        );

        $ratings = $this->createRatings();
        $ratings->setFeedBody($xml);
        $ratings->setProductsBySku([
            'SKU1' => $this->createProduct(1, 0, 0.0),
            'SKU2' => $this->createProduct(2, 0, 0.0),
            'SKU3' => $this->createProduct(3, 0, 0.0),
        ]);

        $ratings->cronDownloadFeed();

        // Two distinct value groups => two bulk writes (instead of one write per product/attribute).
        $this->assertCount(2, $this->updateAttributesCalls);
        $this->assertSame([1, 3], $this->updateAttributesCalls[0]['ids']);
        $this->assertSame(5, $this->updateAttributesCalls[0]['data'][InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE]);
        $this->assertSame([2], $this->updateAttributesCalls[1]['ids']);
        $this->assertSame(3, $this->updateAttributesCalls[1]['data'][InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE]);
    }

    public function testFetchFeedUsesHttpClientWithTimeouts(): void
    {
        $this->httpClient = $this->createMock(HttpClient::class);
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://export.turnto.com/feed.xml',
                $this->callback(
                    function (array $options) {
                        return $options[RequestOptions::TIMEOUT] > 0
                            && $options[RequestOptions::CONNECT_TIMEOUT] > 0
                            && $options[RequestOptions::HTTP_ERRORS] === true;
                    }
                )
            )
            ->willReturn(new Response(200, [], '<feed/>'));

        $body = $this->createRatings()->callRealFetchAggregateRatingsFeedBody('https://export.turnto.com/feed.xml');

        $this->assertSame('<feed/>', $body);
    }

    public function testCronDownloadFeedLogsAndSkipsStoreWhenDownloadFails(): void
    {
        $store = $this->createStore(1, 'default');
        $this->storeManager->method('getStores')->willReturn([$store]);
        $this->config->method('getIsEnabled')->willReturn(true);
        $this->config->method('getConfigBool')->willReturnCallback(
            function ($path) {
                return $path === Config::AVERAGE_RATING_IMPORT_ENABLED;
            }
        );

        $this->logger = $this->createMock(Monolog::class);
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to retrieve TurnTo aggregate rating feed for store from TurnTo',
                $this->callback(
                    function (array $context) {
                        return $context['storeCode'] === 'default'
                            && $context['exception'] instanceof \UnexpectedValueException;
                    }
                )
            );

        $ratings = $this->createRatings();
        $ratings->setFeedException(new TransferException('timeout'));
        $ratings->cronDownloadFeed();

        $this->assertCount(0, $this->updateAttributesCalls);
    }

    public function testResetProductsWritesASingleBulkCallForMissingProducts(): void
    {
        $store = $this->createStore(1, 'default');

        $items = [
            $this->createProductWithSku(10, 'SKU_KEEP', 4, 4.0),
            $this->createProductWithSku(11, 'SKU_GONE_1', 4, 4.0),
            $this->createProductWithSku(12, 'SKU_GONE_2', 2, 3.0),
            $this->createProductWithSku(13, 'SKU_ALREADY_ZERO', 0, 0.0),
        ];
        $this->productCollectionFactory->method('create')->willReturn($this->createResetCollection($items));

        $ratings = $this->createRatings();
        $ratings->callResetProducts([1 => ['SKU_KEEP' => true]], $store);

        // Only the two products missing from the feed and not already zeroed are reset, in one call.
        $this->assertCount(1, $this->updateAttributesCalls);
        $this->assertSame([11, 12], $this->updateAttributesCalls[0]['ids']);
        $this->assertSame(0, $this->updateAttributesCalls[0]['data'][InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE]);
        $this->assertSame('0', $this->updateAttributesCalls[0]['data'][InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE]);
    }

    protected function createProductWithSku(int $id, string $sku, int $reviewCount, float $rating): CatalogProduct
    {
        $product = $this->createStub(CatalogProduct::class);
        $product->method('getId')->willReturn($id);
        $product->method('getSku')->willReturn($sku);
        $product->method('getData')->willReturnCallback(
            function ($key) use ($reviewCount, $rating) {
                if ($key === InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE) {
                    return $reviewCount;
                }
                if ($key === InstallHelper::RATING_ATTRIBUTE_CODE) {
                    return $rating;
                }

                return null;
            }
        );

        return $product;
    }

    protected function createResetCollection(array $items): FakeProductCollection
    {
        return new FakeProductCollection($items);
    }

    protected function createEmptyResetCollection(): FakeProductCollection
    {
        return $this->createResetCollection([]);
    }
}
