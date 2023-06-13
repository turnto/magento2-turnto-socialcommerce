<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Helper\Config as ConfigHelper;
use TurnTo\SocialCommerce\Helper\Product as ProductHelper;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Export\Catalog;
use TurnTo\SocialCommerce\Model\Export\Product;

class CatalogTest extends TestCase
{
    /**
     * @var Catalog
     */
    private $catalog;

    /**
     * Is called before running a test
     */
    protected function setUp(): void
    {
        $this->catalog = new Catalog(
            $this->createMock(ConfigHelper::class),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(CollectionFactory::class),
            $this->createMock(DateTimeFactory::class),
            $this->createMock(Image::class),
            $this->createMock(ProductHelper::class),
            $this->createMock(Product::class),
            $this->createMock(FeedClient::class),
            $this->createMock(Filesystem::class),
            $this->createMock(Monolog::class),
        );
    }

    public function testIsCatalogClass(){
        $this->assertInstanceOf(Catalog::class, $this->catalog);
    }
}
