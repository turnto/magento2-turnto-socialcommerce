<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Export;

use Exception;
use Magento\Framework\App\ResourceConnection;
use TurnTo\SocialCommerce\Logger\Monolog;

/**
 * Resolves category paths for products in a page-sized batch.
 */
class CategoryPathResolver
{
    /**
     * @var int|null
     */
    protected $cachedStoreId;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var Monolog
     */
    protected $logger;

    /**
     * @var array<int, array<int, array{id:string,name:string}>>
     */
    protected $categoryPathByProductId = [];

    /**
     * @var int|null
     */
    protected $nameAttributeId;

    /**
     * @var int|null
     */
    protected $categoryEntityTypeId;

    /**
     * @param ResourceConnection $resourceConnection
     * @param Monolog $logger
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        Monolog $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    /**
     * Cache the deepest category path for each product for one store/page cycle.
     *
     * @param int|string $storeId
     * @param int[] $productIds
     * @return void
     */
    public function preloadCategoryPaths($storeId, array $productIds): void
    {
        $storeId = (int) $storeId;
        $this->cachedStoreId = $storeId;
        $this->categoryPathByProductId = [];

        $productIds = array_values(array_filter(array_unique(array_map('intval', $productIds)), function ($id) {
            return $id > 0;
        }));

        foreach ($productIds as $productId) {
            $this->categoryPathByProductId[$productId] = [];
        }

        if (empty($productIds)) {
            return;
        }

        try {
            $connection = $this->resourceConnection->getConnection();

            $categoryProductRows = $connection->fetchAll(
                $connection->select()
                    ->from(
                        $connection->getTableName('catalog_category_product'),
                        ['product_id', 'category_id']
                    )
                    ->where('product_id IN (?)', $productIds)
                    ->order('category_id ASC')
            );

            if (empty($categoryProductRows)) {
                return;
            }

            $categoriesByProduct = [];
            $categoryIds = [];
            foreach ($categoryProductRows as $row) {
                $productId = (int) $row['product_id'];
                $categoryId = (int) $row['category_id'];
                if ($productId <= 0 || $categoryId <= 0) {
                    continue;
                }

                $categoriesByProduct[$productId][] = $categoryId;
                $categoryIds[] = $categoryId;
            }

            if (empty($categoryIds)) {
                return;
            }

            $categoryIds = array_values(array_unique($categoryIds));
            $entityTypeId = $this->getCatalogCategoryEntityTypeId($connection);
            if ($entityTypeId === null) {
                return;
            }
            $nameAttributeId = $this->getCatalogCategoryNameAttributeId($connection, $entityTypeId);
            if ($nameAttributeId === null) {
                return;
            }

            $pathRows = $connection->fetchAll(
                $connection->select()
                    ->from(
                        $connection->getTableName('catalog_category_entity'),
                        ['entity_id', 'path']
                    )
                    ->where('entity_id IN (?)', $categoryIds)
            );
            $pathByCategory = [];
            $requiredCategoryIds = [];
            foreach ($pathRows as $row) {
                $entityId = (int) $row['entity_id'];
                $path = (string) $row['path'];
                $pathIds = [];
                if ($path !== '') {
                    foreach (explode('/', $path) as $pathCategoryId) {
                        $categoryId = (int) $pathCategoryId;
                        if ($categoryId > 0) {
                            $pathIds[] = $categoryId;
                            $requiredCategoryIds[$categoryId] = true;
                        }
                    }
                }
                if (empty($pathIds)) {
                    $pathIds = [$entityId];
                    $requiredCategoryIds[$entityId] = true;
                }

                $pathByCategory[$entityId] = $pathIds;
            }
            foreach ($categoryIds as $categoryId) {
                $requiredCategoryIds[$categoryId] = true;
            }
            $allCategoryIds = array_map('intval', array_keys($requiredCategoryIds));
            if (empty($allCategoryIds)) {
                return;
            }

            $nameRows = $connection->fetchAll(
                $connection->select()
                    ->from(
                        $connection->getTableName('catalog_category_entity_varchar'),
                        ['entity_id', 'store_id', 'value']
                    )
                    ->where('attribute_id = ?', $nameAttributeId)
                    ->where('entity_id IN (?)', $allCategoryIds)
                    ->where('store_id IN (?)', [0, $storeId])
            );
            $nameByCategory = [];
            foreach ($nameRows as $row) {
                $categoryId = (int) $row['entity_id'];
                $categoryStoreId = (int) $row['store_id'];
                $categoryName = $row['value'];
                if ($categoryName === null || $categoryName === '') {
                    continue;
                }

                if ($categoryStoreId === $storeId || !isset($nameByCategory[$categoryId])) {
                    $nameByCategory[$categoryId] = (string) $categoryName;
                }
            }

            foreach ($categoriesByProduct as $productId => $categoriesForProduct) {
                $bestDepth = -1;
                $bestPath = [];

                foreach ($categoriesForProduct as $categoryId) {
                    $pathCategoryIds = $pathByCategory[$categoryId] ?? [$categoryId];
                    $pathDepth = count($pathCategoryIds);
                    if ($pathDepth <= $bestDepth) {
                        continue;
                    }
                    $bestDepth = $pathDepth;
                    $bestPath = $pathCategoryIds;
                }

                $categoryPath = [];
                foreach ($bestPath as $pathCategoryId) {
                    if (isset($nameByCategory[$pathCategoryId])) {
                        $categoryPath[] = [
                            'id' => (string) $pathCategoryId,
                            'name' => (string) $nameByCategory[$pathCategoryId]
                        ];
                    }
                }
                $this->categoryPathByProductId[$productId] = $categoryPath;
            }
        } catch (Exception $e) {
            $this->logger->error(
                'Could not preload category paths for product feed',
                [
                    'exception' => $e,
                    'store_id' => $storeId,
                    'product_count' => count($productIds)
                ]
            );
        }
    }

    /**
     * @param int $storeId
     * @param int $productId
     * @return array{id:string,name:string}|null
     */
    public function getCategoryPath(int $storeId, int $productId): ?array
    {
        if ($this->cachedStoreId !== $storeId) {
            return null;
        }
        if (!array_key_exists($productId, $this->categoryPathByProductId)) {
            return null;
        }

        return $this->categoryPathByProductId[$productId];
    }

    /**
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @return int|null
     */
    protected function getCatalogCategoryEntityTypeId($connection)
    {
        if ($this->categoryEntityTypeId !== null) {
            return $this->categoryEntityTypeId;
        }

        $entityType = $connection->fetchOne(
            $connection->select()
                ->from(
                    $connection->getTableName('eav_entity_type'),
                    ['entity_type_id']
                )
                ->where('entity_type_code = ?', 'catalog_category')
        );
        if (empty($entityType)) {
            return null;
        }
        $this->categoryEntityTypeId = (int) $entityType;
        return $this->categoryEntityTypeId;
    }

    /**
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @param int $entityTypeId
     * @return int|null
     */
    protected function getCatalogCategoryNameAttributeId($connection, int $entityTypeId)
    {
        if ($this->nameAttributeId !== null) {
            return $this->nameAttributeId;
        }

        $attributeId = $connection->fetchOne(
            $connection->select()
                ->from(
                    $connection->getTableName('eav_attribute'),
                    ['attribute_id']
                )
                ->where('attribute_code = ?', 'name')
                ->where('entity_type_id = ?', $entityTypeId)
        );
        if (empty($attributeId)) {
            return null;
        }
        $this->nameAttributeId = (int) $attributeId;
        return $this->nameAttributeId;
    }
}
