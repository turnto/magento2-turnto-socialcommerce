<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ResourceConnection;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Model\Config as ConfigModel;
use TurnTo\SocialCommerce\Model\Config\Gtin;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Export\Catalog;
use TurnTo\SocialCommerce\Model\Export\Product;
use TurnTo\SocialCommerce\Service\Feed\FeedGeneratorFactory;

class CatalogTest extends TestCase
{
    /**
     * @var Catalog
     */
    protected $catalog;

    protected function setUp(): void
    {
        $config = $this->createMock(ConfigModel::class);
        $gtin = $this->createMock(Gtin::class);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $feedClient = $this->createMock(FeedClient::class);
        $emulation = $this->createMock(Emulation::class);
        $logger = $this->createMock(Monolog::class);
        $feedGeneratorFactory = $this->createMock(FeedGeneratorFactory::class);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $exportProduct = $this->createMock(Product::class);

        $this->catalog = new Catalog(
            $config,
            $gtin,
            $storeManager,
            $collectionFactory,
            $feedClient,
            $emulation,
            $logger,
            $feedGeneratorFactory,
            $resourceConnection,
            $exportProduct
        );
    }

    public function testIsCatalogClass()
    {
        $this->assertInstanceOf(Catalog::class, $this->catalog);
    }
}
