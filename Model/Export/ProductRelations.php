<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Export;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Store\Model\StoreManagerInterface;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Config\Source\GroupedReviewSharing;

/**
 * Resolves how each catalog feed product relates to its configurable, grouped and bundle parents
 */
class ProductRelations
{
    public const PARENTS = 'parents';

    public const RELATED_PRODUCT_IDS = 'related_product_ids';

    /**
     * Product data key of the product whose URL is exported instead of the product's own
     */
    public const URL_PRODUCT_FIELD = 'turnto_url_product';

    /**
     * Product data key flagging a parent that shares its own SKU as virtual parent code with its children
     */
    public const POOL_PARENT_FIELD = 'turnto_pool_parent';

    public const GROUPED_PRODUCT_TYPE = 'grouped';

    public const BUNDLE_PRODUCT_TYPE = 'bundle';

    /**
     * Magento\GroupedProduct\Model\ResourceModel\Product\Link::LINK_TYPE_GROUPED
     */
    public const GROUPED_LINK_TYPE_ID = 3;

    public const URL_VISIBILITIES = [
        Visibility::VISIBILITY_IN_CATALOG,
        Visibility::VISIBILITY_IN_SEARCH,
        Visibility::VISIBILITY_BOTH,
    ];

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var MetadataPool
     */
    protected $metadataPool;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param ResourceConnection $resourceConnection
     * @param CollectionFactory $productCollectionFactory
     * @param MetadataPool $metadataPool
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        CollectionFactory $productCollectionFactory,
        MetadataPool $metadataPool,
        Config $config,
        StoreManagerInterface $storeManager
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->metadataPool = $metadataPool;
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    /**
     * Resolve the parents of a page of feed products
     *
     * Returns the parent each product shares its virtual parent code and URL with, keyed by product id, and the ids
     * of every related product so their URL rewrites can be preloaded. Pool parents are flagged with
     * POOL_PARENT_FIELD, and products that are not visible individually and have no such parent are given the URL
     * of a grouped or bundle product they belong to through URL_PRODUCT_FIELD.
     *
     * @param int|string $storeId
     * @param iterable|CatalogProduct[] $products
     * @return array{parents: array<int, CatalogProduct>, related_product_ids: int[]}
     */
    public function apply($storeId, iterable $products): array
    {
        $storeId = (int) $storeId;
        $useChildSku = $this->config->getUseChildSku($storeId);
        $poolGrouped = $this->config->getGroupedReviewSharing($storeId) === GroupedReviewSharing::VIRTUAL_PARENT_CODE;
        $websiteId = (int) $this->storeManager->getStore($storeId)->getWebsiteId();

        $productsById = [];
        $childIds = [];
        $groupedIds = [];
        foreach ($products as $product) {
            $productId = (int) $product->getId();
            $productsById[$productId] = $product;
            $typeId = $product->getTypeId();
            if ($typeId === self::GROUPED_PRODUCT_TYPE) {
                $groupedIds[] = $productId;
            } elseif ($typeId !== Configurable::TYPE_CODE && $typeId !== self::BUNDLE_PRODUCT_TYPE) {
                $childIds[] = $productId;
            }
        }

        $parentIdsByChild = [];
        $poolParentIds = [];
        if ($useChildSku) {
            foreach ($this->fetchConfigurableParentIds($childIds) as $childId => $parentIds) {
                $parentIdsByChild[$childId] = $parentIds[0];
            }
            foreach ($productsById as $productId => $product) {
                if ($product->getTypeId() === Configurable::TYPE_CODE) {
                    $poolParentIds[] = $productId;
                }
            }
        }

        $groupedParentIdsByChild = $this->fetchGroupedParentIds($childIds, $websiteId);
        if ($poolGrouped) {
            $candidateIds = array_merge($groupedIds, ...array_values($groupedParentIdsByChild));
            $poolableIds = $this->getPoolableGroupedIds($candidateIds, $websiteId, $useChildSku);
            foreach ($groupedIds as $groupedId) {
                if (isset($poolableIds[$groupedId])) {
                    $poolParentIds[] = $groupedId;
                }
            }
            foreach ($groupedParentIdsByChild as $childId => $parentIds) {
                if (!isset($parentIdsByChild[$childId]) && isset($poolableIds[$parentIds[0]])) {
                    $parentIdsByChild[$childId] = $parentIds[0];
                }
            }
        }

        $hiddenIds = [];
        foreach ($childIds as $childId) {
            if (!isset($parentIdsByChild[$childId])
                && (int) $productsById[$childId]->getVisibility() === Visibility::VISIBILITY_NOT_VISIBLE
            ) {
                $hiddenIds[] = $childId;
            }
        }
        $urlParentIdsByChild = [];
        $bundleParentIdsByChild = $this->fetchBundleParentIds($hiddenIds, $websiteId);
        foreach ($hiddenIds as $childId) {
            $parentIds = array_merge(
                $groupedParentIdsByChild[$childId] ?? [],
                $bundleParentIdsByChild[$childId] ?? []
            );
            if ($parentIds !== []) {
                sort($parentIds);
                $urlParentIdsByChild[$childId] = $parentIds;
            }
        }

        $relatedIds = array_merge(array_values($parentIdsByChild), ...array_values($urlParentIdsByChild));
        $relatedProducts = $this->loadProducts($storeId, $relatedIds);

        $parents = [];
        foreach ($parentIdsByChild as $childId => $parentId) {
            if (isset($relatedProducts[$parentId])) {
                $parents[$childId] = $relatedProducts[$parentId];
            }
        }
        foreach ($urlParentIdsByChild as $childId => $parentIds) {
            $urlProduct = $this->getFirstProductWithUrl($parentIds, $relatedProducts);
            if ($urlProduct) {
                $productsById[$childId]->setData(self::URL_PRODUCT_FIELD, $urlProduct);
            }
        }
        foreach ($poolParentIds as $poolParentId) {
            $productsById[$poolParentId]->setData(self::POOL_PARENT_FIELD, true);
        }

        return [
            self::PARENTS => $parents,
            self::RELATED_PRODUCT_IDS => array_keys($relatedProducts),
        ];
    }

    /**
     * Grouped products that can share one virtual parent code with all of their products
     *
     * A product can only share one code, so every product of the group must belong to no other grouped product and,
     * when child SKUs are used, to no configurable product.
     *
     * @param int[] $groupedIds
     * @param int $websiteId
     * @param bool $useChildSku
     * @return array<int, true>
     */
    protected function getPoolableGroupedIds(array $groupedIds, int $websiteId, bool $useChildSku): array
    {
        $childIdsByGrouped = $this->fetchGroupedChildIds(array_values(array_unique($groupedIds)));
        $groupChildIds = array_values(array_unique(array_merge([], ...array_values($childIdsByGrouped))));
        $groupedParentIdsByChild = $this->fetchGroupedParentIds($groupChildIds, $websiteId);
        $configurableParentIdsByChild = $useChildSku ? $this->fetchConfigurableParentIds($groupChildIds) : [];

        $poolableIds = [];
        foreach ($childIdsByGrouped as $groupedId => $childIds) {
            $isPoolable = true;
            foreach ($childIds as $childId) {
                if (count($groupedParentIdsByChild[$childId] ?? []) > 1
                    || isset($configurableParentIdsByChild[$childId])
                ) {
                    $isPoolable = false;
                    break;
                }
            }
            if ($isPoolable) {
                $poolableIds[$groupedId] = true;
            }
        }

        return $poolableIds;
    }

    /**
     * @param int[] $productIds Ordered by preference
     * @param CatalogProduct[] $productsById
     * @return CatalogProduct|null
     */
    protected function getFirstProductWithUrl(array $productIds, array $productsById)
    {
        foreach ($productIds as $productId) {
            $product = $productsById[$productId] ?? null;
            if ($product
                && (int) $product->getStatus() === Status::STATUS_ENABLED
                && in_array((int) $product->getVisibility(), self::URL_VISIBILITIES, true)
            ) {
                return $product;
            }
        }

        return null;
    }

    /**
     * @param int $storeId
     * @param int[] $productIds
     * @return array<int, CatalogProduct>
     */
    protected function loadProducts(int $storeId, array $productIds): array
    {
        $productIds = array_values(array_unique($productIds));
        if ($productIds === []) {
            return [];
        }

        $collection = $this->productCollectionFactory->create()
            ->setStoreId($storeId)
            ->addAttributeToSelect(['url_key', 'url_path', 'status', 'visibility'])
            ->addIdFilter($productIds);

        $products = [];
        foreach ($collection as $product) {
            $products[(int) $product->getId()] = $product;
        }

        return $products;
    }

    /**
     * @param int[] $childIds
     * @return array<int, int[]> Configurable parent ids of each child, ascending
     */
    protected function fetchConfigurableParentIds(array $childIds): array
    {
        if ($childIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->distinct()
            ->from(
                ['link' => $this->resourceConnection->getTableName('catalog_product_super_link')],
                ['child_id' => 'product_id']
            )
            ->join(
                ['parent' => $this->resourceConnection->getTableName('catalog_product_entity')],
                sprintf('parent.%s = link.parent_id', $this->getLinkField()),
                ['parent_id' => 'entity_id']
            )
            ->where('link.product_id IN (?)', $childIds);

        return $this->groupIds($connection->fetchAll($select), 'child_id', 'parent_id');
    }

    /**
     * @param int[] $childIds
     * @param int $websiteId
     * @return array<int, int[]> Grouped parent ids of each child in the website, ascending
     */
    protected function fetchGroupedParentIds(array $childIds, int $websiteId): array
    {
        if ($childIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->distinct()
            ->from(
                ['link' => $this->resourceConnection->getTableName('catalog_product_link')],
                ['child_id' => 'linked_product_id']
            )
            ->join(
                ['parent' => $this->resourceConnection->getTableName('catalog_product_entity')],
                sprintf('parent.%s = link.product_id', $this->getLinkField()),
                ['parent_id' => 'entity_id']
            )
            ->join(
                ['website' => $this->resourceConnection->getTableName('catalog_product_website')],
                'website.product_id = parent.entity_id',
                []
            )
            ->where('link.link_type_id = ?', self::GROUPED_LINK_TYPE_ID)
            ->where('link.linked_product_id IN (?)', $childIds)
            ->where('website.website_id = ?', $websiteId);

        return $this->groupIds($connection->fetchAll($select), 'child_id', 'parent_id');
    }

    /**
     * @param int[] $groupedIds
     * @return array<int, int[]> Child ids of each grouped product, ascending
     */
    protected function fetchGroupedChildIds(array $groupedIds): array
    {
        if ($groupedIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->distinct()
            ->from(
                ['link' => $this->resourceConnection->getTableName('catalog_product_link')],
                ['child_id' => 'linked_product_id']
            )
            ->join(
                ['parent' => $this->resourceConnection->getTableName('catalog_product_entity')],
                sprintf('parent.%s = link.product_id', $this->getLinkField()),
                ['parent_id' => 'entity_id']
            )
            ->where('link.link_type_id = ?', self::GROUPED_LINK_TYPE_ID)
            ->where('parent.entity_id IN (?)', $groupedIds);

        return $this->groupIds($connection->fetchAll($select), 'parent_id', 'child_id');
    }

    /**
     * @param int[] $childIds
     * @param int $websiteId
     * @return array<int, int[]> Bundle parent ids of each selection in the website, ascending
     */
    protected function fetchBundleParentIds(array $childIds, int $websiteId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $selectionTable = $this->resourceConnection->getTableName('catalog_product_bundle_selection');
        if ($childIds === [] || !$connection->isTableExists($selectionTable)) {
            return [];
        }

        $select = $connection->select()
            ->distinct()
            ->from(['selection' => $selectionTable], ['child_id' => 'product_id'])
            ->join(
                ['parent' => $this->resourceConnection->getTableName('catalog_product_entity')],
                sprintf('parent.%s = selection.parent_product_id', $this->getLinkField()),
                ['parent_id' => 'entity_id']
            )
            ->join(
                ['website' => $this->resourceConnection->getTableName('catalog_product_website')],
                'website.product_id = parent.entity_id',
                []
            )
            ->where('selection.product_id IN (?)', $childIds)
            ->where('website.website_id = ?', $websiteId);

        return $this->groupIds($connection->fetchAll($select), 'child_id', 'parent_id');
    }

    /**
     * @param array[] $rows
     * @param string $keyColumn
     * @param string $valueColumn
     * @return array<int, int[]>
     */
    protected function groupIds(array $rows, string $keyColumn, string $valueColumn): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row[$keyColumn]][] = (int) $row[$valueColumn];
        }
        foreach ($grouped as $key => $ids) {
            $ids = array_values(array_unique($ids));
            sort($ids);
            $grouped[$key] = $ids;
        }

        return $grouped;
    }

    /**
     * Column that parent ids in product relation tables refer to (row_id with content staging)
     *
     * @return string
     */
    protected function getLinkField(): string
    {
        return $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
    }
}
