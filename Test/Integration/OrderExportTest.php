<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2021 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Test\Integration;


class OrderExportTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Magento\Catalog\Model\ProductRepository|mixed
     */
    public $productRepo;

    /**
     * @var mixed|\TurnTo\SocialCommerce\Model\Manager\Export\Orders
     */
    public $orderExport;

    /**
     * @var \Magento\Framework\Intl\DateTimeFactory|mixed
     */
    public $dateTimeFactory;

    /**
     * Gather and instantiate required classes for testing
     */
    protected function setUp()
    {
        $this->productRepo = $repository = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \Magento\Catalog\Model\ProductRepository::class
        );
        $this->orderExport = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \TurnTo\SocialCommerce\Model\Manager\Export\Orders::class
        );
        $this->dateTimeFactory = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \Magento\Framework\Intl\DateTimeFactory::class
        );
    }

    public function testProductLoad()
    {

        $product = $this->productRepo->get('simple');
        $this->assertEquals(
            'simple',
            $product->getSku(),
            'The simple product form the data fixture does not exist.
        Verify that the unit test is set up to import fixtures correctly'
        );
    }

    /**
     * @magentoDataFixture Magento/Sales/_files/shipment_tracks_for_search.php
     * @magentoDbIsolation disabled
     * @magentoAppIsolation disabled
     */
    public function testGetOrders()
    {
        $fromDate = '2000-01-01';
        $toDate = '2021-12-01';
        $fromDate = $this->dateTimeFactory->create($fromDate, new \DateTimeZone('UTC'));
        // A normal user would expect the "To" date to include orders on that date. However, by default the field will
        // hold a value where the time is YYYY-MM-DD 00:00:00.000000. The below code will add one day the "To" date then
        // subtract 1 second so that all orders placed before YYYY-MM-DD 23:59:59:000000 will be picked up.
        $toDate = $this->dateTimeFactory->create($toDate, new \DateTimeZone('UTC'))->add(new \DateInterval('P1D'))->sub(
            new \DateInterval('PT1S')
        );
        
        $orders = $this->orderExport->getOrders('1', $fromDate, $toDate);
        //Ensure a order collection is returned
        $this->assertInstanceOf('Magento\Sales\Model\ResourceModel\Order\Collection', $orders);
    }

    /**
     *Test order format
     */
    public function testFormatOrderData()
    {

        $dateTimeFactory = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \Magento\Framework\Intl\DateTimeFactory::class
        );

        $fromDate = '2000-01-01';
        $toDate = '2021-12-01';
        $fromDate = $this->dateTimeFactory->create($fromDate, new \DateTimeZone('UTC'));
        // A normal user would expect the "To" date to include orders on that date. However, by default the field will
        // hold a value where the time is YYYY-MM-DD 00:00:00.000000. The below code will add one day the "To" date then
        // subtract 1 second so that all orders placed before YYYY-MM-DD 23:59:59:000000 will be picked up.
        $toDate = $this->dateTimeFactory->create($toDate, new \DateTimeZone('UTC'))->add(new \DateInterval('P1D'))->sub(
            new \DateInterval('PT1S')
        );

        //$csv =  $this->orderExport->generateOrdersFeed('0',[]);

        $orders = $this->orderExport->getOrders('1', $fromDate, $toDate);
        $orderData = $this->orderExport->formatOrderData($orders);

        $this->assertTrue(is_array($orderData), 'formatOrderData: Did not return an array or order data');
        $this->assertTrue(
            !empty($orderData),
            'formatOrderData: Returned a empty array. Ensure the DB contains valid orders'
        );
        $this->assertTrue(
            !empty($orderData),
            'formatOrderData: Returned a empty array. Ensure the DB contains valid orders'
        );
        //Requires PHP 7.3+
        if (phpversion() >= 7.3) {
            //Ensure data is correctly mapped
            $this->assertEquals($orderData[0][array_key_first($orderData[0])]['ORDERID'], 100000001);
            $this->assertEquals($orderData[0][array_key_first($orderData[0])]['EMAIL'], 'customer@null.com');
            $this->assertEquals($orderData[0][array_key_first($orderData[0])]['ITEMTITLE'], 'Simple Product');
            $this->assertEquals($orderData[0][array_key_first($orderData[0])]['SKU'], 'simple');
            $this->assertEquals($orderData[0][array_key_first($orderData[0])]['ITEMLINEID'], 0);
            $this->assertEquals($orderData[0][array_key_first($orderData[0])]['PRICE'], '10.0000');
        }

    }

    /**
     * Test order feed generation
     */
    public function testGenerateOrdersFeed()
    {
        $fromDate = '2000-01-01';
        $toDate = '2099-12-01';
        $fromDate = $this->dateTimeFactory->create($fromDate, new \DateTimeZone('UTC'));
        // A normal user would expect the "To" date to include orders on that date. However, by default the field will
        // hold a value where the time is YYYY-MM-DD 00:00:00.000000. The below code will add one day the "To" date then
        // subtract 1 second so that all orders placed before YYYY-MM-DD 23:59:59:000000 will be picked up.
        $toDate = $this->dateTimeFactory->create($toDate, new \DateTimeZone('UTC'))->add(new \DateInterval('P1D'))->sub(
            new \DateInterval('PT1S')
        );

        $orders = $this->orderExport->getOrders('1', $fromDate, $toDate);
        $orderData = $this->orderExport->formatOrderData($orders);
        $csv = $this->orderExport->generateOrdersFeed('1', $orderData);

        //replace dates with a predictable date to aid in comparing putput stirng
        $csv = preg_replace(
            '/[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1]) (2[0-3]|[01][0-9]):[0-5][0-9]:[0-5][0-9]/',
            "2021-01-01 12:00:00",
            $csv
        );
        //clean URLs so this can run on any VM. Leave only image
        $csv = preg_replace('/(http|ftp|https):\/\/(www\.)?.+?(?=(?i)(.jpg))/', "image", $csv);
        $this->assertEquals(
            'ORDERID	ORDERDATE	EMAIL	ITEMTITLE	ITEMURL	FIRSTNAME	LASTNAME	SKU	ITEMLINEID	ZIP	PRICE	ITEMIMAGEURL	DELIVERYDATE
100000001	"2021-01-01 12:00:00"	customer@null.com	"Simple Product"				simple	0	11111	10.0000	image.jpg	"2021-01-01 12:00:00"
',
            $csv
        );

    }
}
