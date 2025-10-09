<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use Magento\Catalog\Model\Product as CatalogProduct;
use TurnTo\SocialCommerce\Model\Export\Catalog;

/**
 * Testable subclass to expose protected methods and stub category tree logic
 */
class TestableCatalog extends Catalog
{
    public function callAddProductToAtomFeed($entry, $product, $storeId, $parent)
    {
        $this->addProductToAtomFeed($entry, $product, $storeId, $parent);
    }

    // Avoids needing a real category collection in unit test
    protected function getCategoryTreeString(CatalogProduct $product, $storeId)
    {
        return '';
    }
}


