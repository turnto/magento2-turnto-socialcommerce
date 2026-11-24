<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Integration\Model\Import;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Model\Import\Ratings;
use TurnTo\SocialCommerce\Setup\InstallHelper;

/**
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 *
 * Requires the Warden integration test database (see dev/tests/TESTING.md).
 */
class RatingsTest extends TestCase
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * The bulk-update path must persist the rating attributes to the database.
     *
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testUpdateProductPersistsRatingAttributes(): void
    {
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $store = $this->objectManager->get(StoreManagerInterface::class)->getStore();

        $product = $productRepository->get('simple');

        /** @var Ratings $ratings */
        $ratings = $this->objectManager->create(Ratings::class);
        $changed = $ratings->updateProduct($store, 'simple', 12, 4.0, $product);

        $this->assertTrue($changed, 'Expected the product rating data to be updated.');

        $reloaded = $productRepository->get('simple', false, (int) $store->getId(), true);
        $this->assertSame(12, (int) $reloaded->getData(InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE));
        $this->assertSame(4.0, (float) $reloaded->getData(InstallHelper::RATING_ATTRIBUTE_CODE));
        $this->assertNotEmpty($reloaded->getData(InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE));
    }

    /**
     * A second pass with unchanged data must be a no-op.
     *
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testUpdateProductIsNoOpWhenDataUnchanged(): void
    {
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $store = $this->objectManager->get(StoreManagerInterface::class)->getStore();

        /** @var Ratings $ratings */
        $ratings = $this->objectManager->create(Ratings::class);

        $product = $productRepository->get('simple');
        $ratings->updateProduct($store, 'simple', 12, 4.0, $product);

        $reloaded = $productRepository->get('simple', false, (int) $store->getId(), true);
        $changedAgain = $ratings->updateProduct($store, 'simple', 12, 4.0, $reloaded);

        $this->assertFalse($changedAgain, 'Unchanged rating data should not trigger another write.');
    }
}
