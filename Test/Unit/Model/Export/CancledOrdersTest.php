<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2021 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use TurnTo\SocialCommerce\Model\Manager\Export\CancelledOrders as CancelledOrders;

/**
 * Class OrdersTest
 * @package TurnTo\SocialCommerce\Test\Unit\Model\Export
 */
class CancledOrdersTest extends \PHPUnit\Framework\TestCase
{
    /**
     * URL to send feed uploads to
     */
    const TURNTO_FEED_URL = 'https://www.turnto.com/feedUpload/postfile';
    /**
     * Site key found in turnto portal
     */
    const TURNTO_SITE_KEY = 'h4reAaJjYWi7Q85site';
    /**
     * Auth key in turnto portal
     */
    const TURNTO_AUTH_KEY = '';

    /**
     * Is called before running a test
     */
    protected function setUp()
    {
        $config = $this->mockConfig();

        $this->cancledOrdersExportManager = new CancelledOrders(
            $config,
            $this->createMock(\TurnTo\SocialCommerce\Logger\Monolog::class),
            $this->mockShipmentRepo(),
            $this->mockProductRepo(),
            $this->createMock(\Magento\Catalog\Helper\Product::class),
            $this->mockDirList(),
            $this->createMock(\TurnTo\SocialCommerce\Helper\Product::class),
            $this->createMock(\Magento\Framework\Filesystem\Io\File::class),
            $this->mockOrderCollection(),
            $this->mockExportHelper()
        );
    }

    /**
     * Test that the class can initialize successfully
     */
    public function testIsCanceledOrderExportClass()
    {
        $this->assertInstanceOf(\TurnTo\SocialCommerce\Model\Manager\Export\CancelledOrders::class, $this->cancledOrdersExportManager);
    }

    /**
     *Test that the feed can be transmitted successfully.
     * If no keys are set up at the top of the class, then this test is skipped.
     */
    public function testTransmitFeed()
    {

        //Do not test if keys are blank
        if (empty(self::TURNTO_SITE_KEY) || empty(self::TURNTO_AUTH_KEY)) {
            $this->markTestSkipped('Modify auth and site key constants to test transmission');
        }
        $store = $this->mockStore();
        $outputHandle = fopen('tuntoexport.csv', 'w+');
        fputcsv($outputHandle, ['Test Header'], "\t");
        $this->cancledOrdersExportManager->transmitFeed($outputHandle, $store);
    }

    /**
     *Test that order collection and select methods will return the proper class.
     * This method is better suited for an integration test so were simply testing
     * to ensure the method can execute and that the correct return value type is received.
     */
    public function testGetCanceledOrders()
    {
        $this->assertInstanceOf(
            \Magento\Sales\Model\ResourceModel\Order\Collection::class,
            $this->cancledOrdersExportManager->getCanceledOrders(1, $this->mockDateTime(), $this->mockDateTime())
        );
    }

    /**
     * @throws \ReflectionException
     */
    public function testFormatCancelledOrderData()
    {
        //Test that correct type is returned (array).
        $this->assertTrue(is_array($this->cancledOrdersExportManager->formatCancelledOrderData([$this->mockOrder()])));
    }

    /**
     * Test that getCanceledOrderFeed and the protected functions it relies on.
     */
    public function testGetCanceledOrderFeed()
    {
        $cleanedOutput = trim(preg_replace('/\s+/', ' ', $this->cancledOrdersExportManager->getCanceledOrdersFeed('1', [])));
        $this->assertEquals($cleanedOutput, utf8_encode('ORDERID SKU'));
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|\TurnTo\SocialCommerce\Helper\Config
     */
    private function mockConfig()
    {
        $config = $this->getMockBuilder('\TurnTo\SocialCommerce\Helper\Config')->disableOriginalConstructor()
            ->setMethods(
                [
                    'getFeedUploadAddress',
                    'getSiteKey',
                    'getAuthorizationKey',
                    'getUseChildSku',
                    'getTypeInstance',
                    'getCode',
                    'getGtinAttributesMap',
                    'getStore',
                    'getExcludeDeliveryDateUntilAllItemsShipped',
                    'getExcludeItemsWithoutDeliveryDate',
                    'getValue'
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
        $config->expects($this->any())->method('getExcludeDeliveryDateUntilAllItemsShipped')->willReturn(0);
        $config->expects($this->any())->method('getValue')->willReturn(0);
        $config->expects($this->any())->method('getExcludeItemsWithoutDeliveryDate')->willReturn(1);

        return $config;
    }

    /**
     * @return \Magento\Store\Api\Data\StoreInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private function mockStore()
    {
        return $this->getMockBuilder('\Magento\Store\Api\Data\StoreInterface')->setMethods(
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
        $store->expects($this->any())->method('getBaseUrl')->willReturn('turnto.lan');
        $store->expects($this->any())->method('getCode')->willReturn('turnto.lan');

        return $store;
    }

    /**
     * @return \Magento\Framework\Intl\DateTimeFactory|\PHPUnit\Framework\MockObject\MockObject
     */
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

    /**
     * @return \Magento\Sales\Model\ResourceModel\Order\CollectionFactory|\PHPUnit\Framework\MockObject\MockObject
     * @throws \ReflectionException
     */
    private function mockOrderCollection()
    {
        $orderCollectionMock = $this->createMock(\Magento\Sales\Model\ResourceModel\Order\Collection::class);
        $orderCollectionMock->expects($this->any())->method('addAttributeToFilter')->willReturn($orderCollectionMock);

        $orderCollectionFactoryMock = $this->createMock(
            \Magento\Sales\Model\ResourceModel\Order\CollectionFactory::class
        );
        $orderCollectionFactoryMock->expects($this->any())->method('create')->willReturn($orderCollectionMock);

        return $orderCollectionFactoryMock;
    }

    /**
     * @return \Magento\Sales\Model\Order|\PHPUnit\Framework\MockObject\MockObject
     * @throws \ReflectionException
     */
    private function mockOrder()
    {
        $order = $this->createMock(\Magento\Sales\Model\Order::class, ['getItems', 'getEntityId','getStore']);
        $order->expects($this->any())->method('getItems')->willReturn([$this->mockProduct()]);
        $order->expects($this->any())->method('getEntityId')->willReturn('1');
        $order->expects($this->any())->method('getStore')->willReturn($this->mockStore());

        return $order;
    }

    private function mockProduct()
    {
        $product = $this->getMockBuilder('Magento\ConfigurableProduct\Model\Product')
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getValue',
                    'getTypeId',
                    'getTypeInstance',
                    'getUsedProducts',
                    'getSku',
                    'isDeleted',
                    'getParentItemId',
                    'getItemId',
                    'getProductId',
                    'getName',
                    'getProductUrl',
                    'getOriginalPrice',
                    'getLastPageNumber',
                    'getPageSize',
                    'setPageSize',
                    'clear',
                    'load',
                    'count',
                    'setCurPage'
                ]
            )->getMock();
        $product->expects($this->any())->method('getValue')->willReturn('ok');
        $product->expects($this->any())->method('isDeleted')->willReturn(false);
        $product->expects($this->any())->method('getParentItemId')->willReturn(false);
        $product->expects($this->any())->method('getProductId')->willReturn(123);
        $product->expects($this->any())->method('isDeleted')->willReturn(false);
        $product->expects($this->any())->method('getItemId')->willReturn(123);
        $product->expects($this->any())->method('getTypeId')->willReturn($product);
        $product->expects($this->any())->method('getTypeInstance')->willReturn('simple');
        $product->expects($this->any())->method('getUsedProducts')->willReturn('');
        $product->expects($this->any())->method('getSku')->willReturn('testSkuSimple');
        $product->expects($this->any())->method('getName')->willReturn('TestName');
        $product->expects($this->any())->method('getProductUrl')->willReturn('turnto.lan/product');
        $product->expects($this->any())->method('getOriginalPrice')->willReturn('1.00');
        $product->expects($this->any())->method('getLastPageNumber')->willReturn('1');
        $product->expects($this->any())->method('getPageSize')->willReturn('1');
        $product->expects($this->any())->method('setPageSize')->willReturnSelf();
        $product->expects($this->any())->method('setCurPage')->willReturnSelf();
        $product->expects($this->any())->method('load')->willReturnSelf();
        $product->expects($this->any())->method('clear')->willReturn('1');
        $product->expects($this->any())->method('count')->willReturn(0);

        return $product;
    }

    public function mockProductRepo()
    {
        $productRepo = $this->getMockBuilder('\Magento\Catalog\Model\ProductRepository')
            ->disableOriginalConstructor()
            ->setMethods(['getById'])
            ->getMock();
        $productRepo->expects($this->any())->method('getById')->willReturn($this->mockProduct());

        return $productRepo;
    }

    public function mockShipmentRepo()
    {
        $shipmentRepo = $this->getMockBuilder('\Magento\Sales\Api\ShipmentRepositoryInterface')
            ->disableOriginalConstructor()
            ->getMock();
        return $shipmentRepo;
    }

    public function mockExportHelper()
    {
        $searchCriteria = $this->getMockBuilder('\Magento\Framework\Api\SearchCriteriaInterface')->disableOriginalConstructor()->getMock();

        $shipmentRepo = $this->getMockBuilder('\TurnTo\SocialCommerce\Helper\Export\Order')
            ->disableOriginalConstructor()
            ->getMock();

        return $shipmentRepo;
    }

    public function mockDirList()
    {
        $dirList = $this->getMockBuilder('\Magento\Framework\App\Filesystem\DirectoryList')
            ->disableOriginalConstructor()
            ->setMethods(['getPath'])
            ->getMock();
        $dirList->expects($this->any())->method('getPath')->willReturn('var/tmp/');

        return $dirList;
    }
}
