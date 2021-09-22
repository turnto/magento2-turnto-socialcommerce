<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2021 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Test\Integration;


class OrderExportTest extends \PHPUnit\Framework\TestCase
{
    public function testProductLoad()
    {

        $repository = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \Magento\Catalog\Model\ProductRepository::class
        );
        $product = $repository->get('24-MB01');
        $this->assertEquals('24-MB01', $product->getSku());
    }

    public function testGetItemData()
    {
        $orderExport = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \TurnTo\SocialCommerce\Model\Manager\Export\Orders::class
        );
        //$csv = $orderExport->generateOrdersFeed('0',[]);
        //$this->assertEquals('ORDERID       ORDERDATE       EMAIL   ITEMTITLE       ITEMURL FIRSTNAME       LASTNAME
        //        SKU     ITEMLINEID      ZIP     PRICE   ITEMIMAGEURL    DELIVERYDATE',$csv);

    }

    /**
     * @magentoDataFixture Magento/Sales/_files/shipment_tracks_for_search.php
     * @magentoDbIsolation disabled
     * @magentoAppIsolation disabled
     */
    public function testGetOrders(){
        $orderExport = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \TurnTo\SocialCommerce\Model\Manager\Export\Orders::class
        );
        $dateTimeFactory = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \Magento\Framework\Intl\DateTimeFactory::class
        );

        $fromDate = '2000-01-01';
        $toDate = '2021-12-01';
        $fromDate = $dateTimeFactory->create($fromDate, new \DateTimeZone('UTC'));
        // A normal user would expect the "To" date to include orders on that date. However, by default the field will
        // hold a value where the time is YYYY-MM-DD 00:00:00.000000. The below code will add one day the "To" date then
        // subtract 1 second so that all orders placed before YYYY-MM-DD 23:59:59:000000 will be picked up.
        $toDate = $dateTimeFactory
            ->create($toDate, new \DateTimeZone('UTC'))
            ->add(new \DateInterval('P1D'))
            ->sub(new \DateInterval('PT1S'));

        $orderExport = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \TurnTo\SocialCommerce\Model\Manager\Export\Orders::class
        );
        //$csv = $orderExport->generateOrdersFeed('0',[]);

        $orders = $orderExport->getOrders('1',$fromDate, $toDate);
        $csv = $orderExport->generateOrdersFeed('1',$orders);
        $this->assertEquals('24-MB01',$csv);


    }
}
