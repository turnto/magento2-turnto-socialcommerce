<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Model\Config as ConfigModel;
use TurnTo\SocialCommerce\Model\Config\Gtin;
use TurnTo\SocialCommerce\Model\Product;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Export\Catalog;

class CatalogTest extends TestCase
{
    /**
     * @var Catalog
     */
    protected $catalog;

    /**
     * Is called before running a test
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->catalog = new Catalog(
            $this->createMock(ConfigModel::class),
            $this->createMock(Gtin::class),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(CollectionFactory::class),
            $this->createMock(DateTimeFactory::class),
            $this->createMock(Image::class),
            $this->createMock(Product::class),
            $this->createMock(FeedClient::class),
            $this->createMock(Monolog::class)
        );
    }

    public function testIsCatalogClass(){
        $this->assertInstanceOf(Catalog::class, $this->catalog);
    }
}
