<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2021 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use TurnTo\SocialCommerce\Helper\Product;
use TurnTo\SocialCommerce\Model\Manager\Export\Catalog as CatalogExportManager;

class CatalogTest extends  \PHPUnit\Framework\TestCase
{
    private $catalog;

    /**
     * Is called before running a test
     */
    protected function setUp()
    {
        $this->catalog = new CatalogExportManager(
         $this->createMock(\TurnTo\SocialCommerce\Helper\Config::class),
         $this->createMock(\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory::class),
         $this->createMock(\TurnTo\SocialCommerce\Logger\Monolog::class),
         $this->createMock(\Magento\Framework\Intl\DateTimeFactory::class),
         $this->createMock(\Magento\Framework\Api\SearchCriteriaBuilder::class),
         $this->createMock(\Magento\Framework\Api\FilterBuilder::class),
         $this->createMock(\Magento\Framework\Api\SortOrderBuilder::class),
         $this->createMock(\Magento\UrlRewrite\Model\UrlFinderInterface::class),
         $this->createMock(\Magento\Store\Model\StoreManagerInterface::class),
         $this->createMock(\Magento\Catalog\Helper\Image::class),
         $this->createMock(\TurnTo\SocialCommerce\Helper\Product::class)
        );
    }



    public function testIsCatalogClass(){
        $this->assertInstanceOf(\TurnTo\SocialCommerce\Model\Manager\Export\Catalog::class,$this->catalog);
    }
}
