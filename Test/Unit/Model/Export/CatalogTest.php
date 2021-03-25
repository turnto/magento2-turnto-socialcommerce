<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2021 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use TurnTo\SocialCommerce\Model\Manager\Export\Catalog as CatalogExportManager;

class CatalogTest extends \PHPUnit\Framework\TestCase
{
    const TURNTO_FEED_URL = 'https://www.turnto.com/feedUpload/postfile';
    const TURNTO_SITE_KEY = '';
    const TURNTO_AUTH_KEY = '';

    private $catalog;

    private $store;

    /**
     * Is called before running a test
     */
    protected function setUp()
    {
        $this->mockStore();
        $dateTimeFactory = $this->mockDateTime();
        $config = $this->mockConfig();

        $this->catalog = new CatalogExportManager(
            $config,
            $this->mockCollection(),
            $this->createMock(\TurnTo\SocialCommerce\Logger\Monolog::class),
            $dateTimeFactory,
            $this->createMock(\Magento\Framework\Api\SearchCriteriaBuilder::class),
            $this->createMock(\Magento\Framework\Api\FilterBuilder::class),
            $this->createMock(\Magento\Framework\Api\SortOrderBuilder::class),
            $this->createMock(\Magento\UrlRewrite\Model\UrlFinderInterface::class),
            $this->createMock(\Magento\Store\Model\StoreManagerInterface::class),
            $this->createMock(\Magento\Catalog\Helper\Image::class),
            $this->createMock(\TurnTo\SocialCommerce\Helper\Product::class),
            $this->createMock(\TurnTo\SocialCommerce\Helper\Export::class)

        );
    }

    public function testIsCatalogClass()
    {
        $this->assertInstanceOf(\TurnTo\SocialCommerce\Model\Manager\Export\Catalog::class, $this->catalog);
    }

    public function testCreateFeed()
    {
        $feed = $this->catalog->createFeed($this->store);
        $this->assertEquals(
            $feed,
            new \SimpleXMLElement(
                '<?xml version="1.0" encoding="UTF-8" ?><root><title/><link/><updated>2021-03-19</updated><author><name>TurnTo</name></author><id/></root>'
            )
        );
    }

    public function testTransmitFeed()
    {
        //Do not test if keys are blank
        if (empty(self::TURNTO_SITE_KEY) || empty(self::TURNTO_AUTH_KEY)) {
            $this->markTestSkipped('Modify auth and site key constants to test transmission');

        }

        $this->catalog->transmitFeed(
            new \SimpleXMLElement(
                '<?xml version="1.0" encoding="UTF-8" ?><root><title/><link/><updated>2021-03-19</updated><author><name>TurnTo</name></author><id/></root>'
            ),
            $this->store
        );
    }

    public function testPopulateFeed()
    {
        $feed = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8" ?><root><title/><link/><updated>2021-03-19</updated><author><name>TurnTo</name></author><id/></root>'
        );
        $product = $this->mockProduct();

        $feed = $this->catalog->populateProductFeed($this->store, $feed, [$product]);

        $this->assertEquals(
            $feed,
            new \SimpleXMLElement(
                '<?xml version="1.0" encoding="UTF-8"?>
<root><title/><link/><updated>2021-03-19</updated><author><name>TurnTo</name></author><id/><entry/></root>'
            )
        );
    }

    public function testGetProducts()
    {
        $results = $this->catalog->getProducts($this->store, 1, 1);
        $this->assertInstanceOf(\Magento\Catalog\Model\ResourceModel\Product\Collection::class, $results);

    }

    private function mockStore()
    {
        $this->store = $this->getMockBuilder('\Magento\Store\Api\Data\StoreInterface')->setMethods(
            [
                'getBaseUrl',
                'getName',
                'getId',
                'setId',
                'getCode',
                'setCode',
                'setName',
                'getWebsiteId',
                'setWebsiteId',
                'getStoreGroupId',
                'setIsActive',
                'getIsActive',
                'setStoreGroupId',
                'getExtensionAttributes',
                'setExtensionAttributes'

            ]
        )->getMock();
        $this->store->expects($this->any())->method('getBaseUrl')->willReturn('turnto.lan');
    }

    private function mockDateTime()
    {
        $dateTimeFactory = $this->getMockBuilder('\Magento\Framework\Intl\DateTimeFactory')->setMethods(
            ['format', 'create']
        )->getMock();
        $dateTime = $this->getMockBuilder('\DateTime')->setMethods(['format'])->getMock();
        $dateTime->expects($this->any())->method('format')->willReturn('2021-03-19');
        $dateTimeFactory->expects($this->any())->method('create')->willReturn($dateTime);

        return $dateTimeFactory;
    }

    private function mockConfig()
    {
        $config = $this->getMockBuilder('\TurnTo\SocialCommerce\Helper\Config')
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getFeedUploadAddress',
                    'getSiteKey',
                    'getAuthorizationKey',
                    'getUseChildSku',
                    'getTypeInstance',
                    'getCode',
                    'getGtinAttributesMap',
                    'getStore'
                ]
            )
            ->getMock();
        $config->expects($this->any())->method('getFeedUploadAddress')->willReturn(self::TURNTO_FEED_URL);
        $config->expects($this->any())->method('getSiteKey')->willReturn(self::TURNTO_SITE_KEY);
        $config->expects($this->any())->method('getAuthorizationKey')->willReturn(self::TURNTO_AUTH_KEY);
        $config->expects($this->any())->method('getUseChildSku')->willReturn('1');
        $config->expects($this->any())->method('getTypeInstance')->willReturn('configurable');
        $config->expects($this->any())->method('getCode')->willReturn('1');
        $config->expects($this->any())->method('getGtinAttributesMap')->willReturn([]);
        $config->expects($this->any())->method('getStore')->willReturn('1');

        return $config;
    }

    private function mockProduct()
    {

        $product = $this->getMockBuilder('Magento\ConfigurableProduct\Model\Product')
            ->disableOriginalConstructor()
            ->setMethods(['getValue', 'getTypeId', 'getTypeInstance', 'getUsedProducts', 'getSku'])
            ->getMock();
        $product->expects($this->any())->method('getValue')->willReturn('ok');
        $product->expects($this->any())->method('getTypeId')->willReturn($product);
        $product->expects($this->any())->method('getTypeInstance')->willReturn('simple');
        $product->expects($this->any())->method('getUsedProducts')->willReturn('');
        $product->expects($this->any())->method('getSku')->willReturn('testSkuSimple');

        return $product;
    }

    private function mockCollection()
    {
        $collection = $this->getMockBuilder('\Magento\Catalog\Model\ResourceModel\Product\Collection')
            ->disableOriginalConstructor()
            ->setMethods(['addAttributeToSelect', 'getLastPageNumber', 'addStoreFilter'])
            ->getMock();
        $collection->expects($this->any())->method('addAttributeToSelect')->willReturn($collection);
        $collection->expects($this->any())->method('getLastPageNumber')->willReturn(1);
        $collection->expects($this->any())->method('addStoreFilter')->willReturn(1);

        $collectionFactory = $this->getMockBuilder('\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory')
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $collectionFactory->expects($this->any())->method('create')->willReturn($collection);

        return $collectionFactory;
    }

}
