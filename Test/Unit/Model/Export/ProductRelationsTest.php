<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Config\Source\GroupedReviewSharing;
use TurnTo\SocialCommerce\Model\Export\ProductRelations;

class ProductRelationsTest extends TestCase
{
    /**
     * @var bool
     */
    private $useChildSku = false;
    /**
     * @var string
     */
    private $groupedReviewSharing = GroupedReviewSharing::MEMBERS;
    /**
     * @var TestableProductRelations
     */
    private $relations;

    protected function setUp(): void
    {
        $this->useChildSku = false;
        $this->groupedReviewSharing = GroupedReviewSharing::MEMBERS;
        $this->relations = $this->createRelations();
    }

    public function testChildSkuModeSharesTheLowestConfigurableParent(): void
    {
        $this->useChildSku = true;
        $configurable = $this->createProduct(10, 'configurable');
        $otherConfigurable = $this->createProduct(11, 'configurable');
        $child = $this->createProduct(1, 'simple', Visibility::VISIBILITY_NOT_VISIBLE);
        $this->relations->configurableLinks = [[11, 1], [10, 1]];
        $this->relations->loadableProducts = [10 => $configurable, 11 => $otherConfigurable];

        $result = $this->relations->apply(1, [$child, $configurable]);

        $this->assertSame([1 => $configurable], $result[ProductRelations::PARENTS]);
        $this->assertSame([10], $result[ProductRelations::RELATED_PRODUCT_IDS]);
        $this->assertTrue($configurable->getData(ProductRelations::POOL_PARENT_FIELD));
        $this->assertNull($child->getData(ProductRelations::URL_PRODUCT_FIELD));
    }

    public function testParentSkuModeDoesNotRelateConfigurableProducts(): void
    {
        $configurable = $this->createProduct(10, 'configurable');
        $child = $this->createProduct(1, 'simple');
        $this->relations->configurableLinks = [[10, 1]];
        $this->relations->loadableProducts = [10 => $configurable];

        $result = $this->relations->apply(1, [$child, $configurable]);

        $this->assertSame([], $result[ProductRelations::PARENTS]);
        $this->assertNull($configurable->getData(ProductRelations::POOL_PARENT_FIELD));
    }

    public function testKitModeOnlyLendsTheGroupedUrlToProductsThatAreNotVisibleIndividually(): void
    {
        $grouped = $this->createProduct(20, 'grouped');
        $hiddenChild = $this->createProduct(1, 'simple', Visibility::VISIBILITY_NOT_VISIBLE);
        $visibleChild = $this->createProduct(2, 'simple');
        $this->relations->groupedLinks = [[20, 1], [20, 2]];
        $this->relations->loadableProducts = [20 => $grouped];

        $result = $this->relations->apply(1, [$hiddenChild, $visibleChild, $grouped]);

        $this->assertSame([], $result[ProductRelations::PARENTS]);
        $this->assertSame([20], $result[ProductRelations::RELATED_PRODUCT_IDS]);
        $this->assertSame($grouped, $hiddenChild->getData(ProductRelations::URL_PRODUCT_FIELD));
        $this->assertNull($visibleChild->getData(ProductRelations::URL_PRODUCT_FIELD));
        $this->assertNull($grouped->getData(ProductRelations::POOL_PARENT_FIELD));
    }

    public function testBundleSelectionsBorrowTheFirstEnabledVisibleParentUrl(): void
    {
        $disabledBundle = $this->createProduct(30, 'bundle', Visibility::VISIBILITY_BOTH, Status::STATUS_DISABLED);
        $hiddenBundle = $this->createProduct(31, 'bundle', Visibility::VISIBILITY_NOT_VISIBLE);
        $searchBundle = $this->createProduct(32, 'bundle', Visibility::VISIBILITY_IN_SEARCH);
        $otherWebsiteBundle = $this->createProduct(29, 'bundle');
        $selection = $this->createProduct(1, 'simple', Visibility::VISIBILITY_NOT_VISIBLE);
        $this->relations->bundleLinks = [[32, 1], [31, 1], [30, 1], [29, 1]];
        $this->relations->otherWebsiteParentIds = [29];
        $this->relations->loadableProducts = [
            29 => $otherWebsiteBundle,
            30 => $disabledBundle,
            31 => $hiddenBundle,
            32 => $searchBundle,
        ];

        $this->relations->apply(1, [$selection]);

        $this->assertSame($searchBundle, $selection->getData(ProductRelations::URL_PRODUCT_FIELD));
    }

    public function testPoolModeSharesTheGroupedSkuWhenEveryProductBelongsOnlyToThatGroup(): void
    {
        $this->groupedReviewSharing = GroupedReviewSharing::VIRTUAL_PARENT_CODE;
        $grouped = $this->createProduct(20, 'grouped');
        $hiddenChild = $this->createProduct(1, 'simple', Visibility::VISIBILITY_NOT_VISIBLE);
        $visibleChild = $this->createProduct(2, 'virtual');
        $this->relations->groupedLinks = [[20, 1], [20, 2], [20, 3]];
        $this->relations->loadableProducts = [20 => $grouped];

        $result = $this->relations->apply(1, [$hiddenChild, $visibleChild, $grouped]);

        $this->assertSame([1 => $grouped, 2 => $grouped], $result[ProductRelations::PARENTS]);
        $this->assertTrue($grouped->getData(ProductRelations::POOL_PARENT_FIELD));
        $this->assertNull($hiddenChild->getData(ProductRelations::URL_PRODUCT_FIELD));
    }

    public function testPoolModeFallsBackToKitModeForTheWholeGroupWhenAProductBelongsToAnotherGroup(): void
    {
        $this->groupedReviewSharing = GroupedReviewSharing::VIRTUAL_PARENT_CODE;
        $grouped = $this->createProduct(20, 'grouped');
        $otherGrouped = $this->createProduct(21, 'grouped');
        $exclusiveChild = $this->createProduct(1, 'simple', Visibility::VISIBILITY_NOT_VISIBLE);
        $sharedChild = $this->createProduct(2, 'simple', Visibility::VISIBILITY_NOT_VISIBLE);
        $this->relations->groupedLinks = [[20, 1], [20, 2], [21, 2]];
        $this->relations->loadableProducts = [20 => $grouped, 21 => $otherGrouped];

        $result = $this->relations->apply(1, [$exclusiveChild, $sharedChild, $grouped]);

        $this->assertSame([], $result[ProductRelations::PARENTS]);
        $this->assertNull($grouped->getData(ProductRelations::POOL_PARENT_FIELD));
        $this->assertSame($grouped, $exclusiveChild->getData(ProductRelations::URL_PRODUCT_FIELD));
        $this->assertSame($grouped, $sharedChild->getData(ProductRelations::URL_PRODUCT_FIELD));
    }

    public function testPoolModeIgnoresGroupedProductsInOtherWebsites(): void
    {
        $this->groupedReviewSharing = GroupedReviewSharing::VIRTUAL_PARENT_CODE;
        $grouped = $this->createProduct(20, 'grouped');
        $child = $this->createProduct(1, 'simple');
        $this->relations->groupedLinks = [[20, 1], [21, 1]];
        $this->relations->otherWebsiteParentIds = [21];
        $this->relations->loadableProducts = [20 => $grouped];

        $result = $this->relations->apply(1, [$child, $grouped]);

        $this->assertSame([1 => $grouped], $result[ProductRelations::PARENTS]);
        $this->assertTrue($grouped->getData(ProductRelations::POOL_PARENT_FIELD));
    }

    public function testPoolModeFallsBackToKitModeWhenAProductSharesAConfigurableParentsCodeInChildSkuMode(): void
    {
        $this->useChildSku = true;
        $this->groupedReviewSharing = GroupedReviewSharing::VIRTUAL_PARENT_CODE;
        $configurable = $this->createProduct(10, 'configurable');
        $grouped = $this->createProduct(20, 'grouped');
        $variant = $this->createProduct(1, 'simple', Visibility::VISIBILITY_NOT_VISIBLE);
        $sibling = $this->createProduct(2, 'simple');
        $this->relations->configurableLinks = [[10, 1]];
        $this->relations->groupedLinks = [[20, 1], [20, 2]];
        $this->relations->loadableProducts = [10 => $configurable, 20 => $grouped];

        $result = $this->relations->apply(1, [$variant, $sibling, $grouped]);

        $this->assertSame([1 => $configurable], $result[ProductRelations::PARENTS]);
        $this->assertNull($grouped->getData(ProductRelations::POOL_PARENT_FIELD));
    }

    public function testPoolModeChecksGroupsOfProductsOnOtherPages(): void
    {
        $this->groupedReviewSharing = GroupedReviewSharing::VIRTUAL_PARENT_CODE;
        $grouped = $this->createProduct(20, 'grouped');
        $child = $this->createProduct(1, 'simple');
        $this->relations->groupedLinks = [[20, 1], [20, 2], [21, 2]];
        $this->relations->loadableProducts = [20 => $grouped];

        $result = $this->relations->apply(1, [$child]);

        $this->assertSame([], $result[ProductRelations::PARENTS], 'Product 2 of the group also belongs to group 21.');
    }

    private function createRelations(): TestableProductRelations
    {
        $config = $this->createStub(Config::class);
        $config->method('getUseChildSku')->willReturnCallback(function () {
            return $this->useChildSku;
        });
        $config->method('getGroupedReviewSharing')->willReturnCallback(function () {
            return $this->groupedReviewSharing;
        });
        $store = $this->createStub(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new TestableProductRelations(
            $this->createStub(ResourceConnection::class),
            $this->createStub(CollectionFactory::class),
            $this->createStub(MetadataPool::class),
            $config,
            $storeManager
        );
    }

    /**
     * Product double that keeps the data set on it, like the real model
     *
     * @param int $productId
     * @param string $typeId
     * @param int $visibility
     * @param int $status
     * @return CatalogProduct
     */
    private function createProduct(
        int $productId,
        string $typeId,
        int $visibility = Visibility::VISIBILITY_BOTH,
        int $status = Status::STATUS_ENABLED
    ): CatalogProduct {
        $data = [];
        $product = $this->createStub(CatalogProduct::class);
        $product->method('getId')->willReturn($productId);
        $product->method('getTypeId')->willReturn($typeId);
        $product->method('getVisibility')->willReturn($visibility);
        $product->method('getStatus')->willReturn($status);
        $product->method('setData')->willReturnCallback(function ($key, $value = null) use (&$data, $product) {
            $data[$key] = $value;

            return $product;
        });
        $product->method('getData')->willReturnCallback(function ($key = '') use (&$data) {
            return $data[$key] ?? null;
        });

        return $product;
    }
}
