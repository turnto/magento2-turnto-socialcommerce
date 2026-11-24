<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use Magento\Catalog\Model\Product as CatalogProduct;
use TurnTo\SocialCommerce\Model\Export\ProductRelations;

/**
 * Answers the product relation lookups from in-memory link tables instead of the database.
 */
class TestableProductRelations extends ProductRelations
{
    /**
     * @var array[] [parent id, child id] pairs
     */
    public $configurableLinks = [];

    /**
     * @var array[] [parent id, child id] pairs
     */
    public $groupedLinks = [];

    /**
     * @var array[] [parent id, child id] pairs
     */
    public $bundleLinks = [];

    /**
     * @var int[] Parents that are not assigned to the exported website
     */
    public $otherWebsiteParentIds = [];

    /**
     * @var array<int, CatalogProduct> Products that can be loaded, keyed by id
     */
    public $loadableProducts = [];

    protected function fetchConfigurableParentIds(array $childIds): array
    {
        return $this->findParents($this->configurableLinks, $childIds, false);
    }

    protected function fetchGroupedParentIds(array $childIds, int $websiteId): array
    {
        return $this->findParents($this->groupedLinks, $childIds, true);
    }

    protected function fetchGroupedChildIds(array $groupedIds): array
    {
        $rows = [];
        foreach ($this->groupedLinks as [$parentId, $childId]) {
            if (in_array($parentId, $groupedIds, true)) {
                $rows[] = ['parent_id' => $parentId, 'child_id' => $childId];
            }
        }

        return $this->groupIds($rows, 'parent_id', 'child_id');
    }

    protected function fetchBundleParentIds(array $childIds, int $websiteId): array
    {
        return $this->findParents($this->bundleLinks, $childIds, true);
    }

    protected function loadProducts(int $storeId, array $productIds): array
    {
        return array_intersect_key($this->loadableProducts, array_flip($productIds));
    }

    private function findParents(array $links, array $childIds, bool $inWebsite): array
    {
        $rows = [];
        foreach ($links as [$parentId, $childId]) {
            if (in_array($childId, $childIds, true)
                && (!$inWebsite || !in_array($parentId, $this->otherWebsiteParentIds, true))
            ) {
                $rows[] = ['parent_id' => $parentId, 'child_id' => $childId];
            }
        }

        return $this->groupIds($rows, 'child_id', 'parent_id');
    }
}
