<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Service\Feed;

use Magento\Catalog\Model\Product as CatalogProduct;
use SimpleXMLElement;
use TurnTo\SocialCommerce\Service\Feed\GoogleFeedGenerator;

/**
 * Exposes protected feed helpers for unit testing.
 */
class TestableGoogleFeedGenerator extends GoogleFeedGenerator
{
    /**
     * @param SimpleXMLElement $entry
     * @param CatalogProduct $product
     * @param int|string $storeId
     * @param bool|CatalogProduct $parent
     * @return void
     */
    public function callAddProductToAtomFeed($entry, $product, $storeId, $parent)
    {
        $this->addProductToAtomFeed($entry, $product, $storeId, $parent);
    }

    /**
     * @inheritdoc
     */
    protected function getCategoryTreeString(CatalogProduct $product, $storeId)
    {
        return '';
    }
}
