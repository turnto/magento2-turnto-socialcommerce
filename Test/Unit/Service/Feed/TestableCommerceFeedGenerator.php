<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Service\Feed;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product as CatalogProduct;
use TurnTo\SocialCommerce\Service\Feed\CommerceFeedGenerator;

/**
 * Exposes protected feed helpers for unit testing.
 */
class TestableCommerceFeedGenerator extends CommerceFeedGenerator
{
    /**
     * @param CatalogProduct $product
     * @param int|string|null $storeId
     * @param bool|CatalogProduct|null $parent
     * @return string
     */
    public function callGenerateProductLine($product, $storeId, $parent)
    {
        return $this->generateProductLine($product, $storeId, $parent);
    }

    /**
     * @param CatalogProduct $product
     * @return string
     */
    public function callGetMembers($product)
    {
        return $this->getMembers($product);
    }

    /**
     * @inheritdoc
     */
    protected function getCategoryTreeString(CatalogProduct $product, $storeId)
    {
        return 'Category 1 > Category 2';
    }

    /**
     * @param CatalogProduct $product
     * @param int|string|null $storeId
     * @return array
     */
    protected function getDeepestCategoryTree(CatalogProduct $product, $storeId)
    {
        return [
            $this->getCategoryFixture('Category 1', 100),
            $this->getCategoryFixture('Category 2', 200)
        ];
    }

    /**
     * @param string $name
     * @param int $id
     * @return Category
     */
    protected function getCategoryFixture(string $name, int $id)
    {
        $category = new class($name, $id) {
            /**
             * @var string
             */
            private $name;
            /**
             * @var int
             */
            private $id;

            /**
             * @param string $name
             * @param int $id
             */
            public function __construct(string $name, int $id)
            {
                $this->name = $name;
                $this->id = $id;
            }

            /**
             * @return string
             */
            public function getName(): string
            {
                return $this->name;
            }

            /**
             * @return int
             */
            public function getId(): int
            {
                return $this->id;
            }
        };
        return $category;
    }
}
