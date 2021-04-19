<?php
/**
 * TurnTo_SocialCommerce
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright  Copyright (c) 2018 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Model\Cron\Export;

/**
 * Class Catalog
 * @package TurnTo\SocialCommerce\Model\Cron\Export
 */
class Orders
{

    /**
     * @var \TurnTo\SocialCommerce\Model\Manager\Export\Orders
     */
    protected $orderExportManager;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \TurnTo\SocialCommerce\Helper\Config
     */
    protected $turnToConfig;

    /**
     * @var \TurnTo\SocialCommerce\Logger\Monolog
     */
    protected $logger;

    /**
     * @var \Magento\Framework\Intl\DateTimeFactory
     */
    protected $dateTimeFactory;

    public function __construct(
        \TurnTo\SocialCommerce\Model\Manager\Export\Orders $orderExportManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \TurnTo\SocialCommerce\Helper\Config $turnToConfig,
        \TurnTo\SocialCommerce\Logger\Monolog $logger,
        \Magento\Framework\Intl\DateTimeFactory $dateTimeFactory
    )
    {
        $this->orderExportManager = $orderExportManager;
        $this->storeManager = $storeManager;
        $this->turnToConfig = $turnToConfig;
        $this->logger = $logger;
        $this->dateTimeFactory = $dateTimeFactory;
    }

   public function execute() {
       foreach ($this->storeManager->getStores() as $store) {
           if ($this->turnToConfig->getIsEnabled($store->getCode())
               && $this->turnToConfig->getIsHistoricalOrdersFeedEnabled($store->getCode())
           ) {
               try {
                   $fromDate =  $this->dateTimeFactory->create('now', new \DateTimeZone('UTC'))->sub(new \DateInterval('P2D'));
                   $toDate = $this->dateTimeFactory->create('now',new \DateTimeZone('UTC'));
                   $orders = $this->orderExportManager->getOrders($store->getId(), $fromDate, $toDate);
                   $orderData = $this->orderExportManager->formatOrderData($orders);
                   $orderFeed =$this->orderExportManager->generateOrdersFeed($store->getId(), $orderData);
                   $this->orderExportManager->transmitFeed($orderFeed,$store);
               } catch (\Exception $e) {
                   $this->logger->error(
                       'An error occurred while sending the Historical Orders Feed report to TurnTo. Error:',
                       [
                           'storeId' => $store->getId(),
                           'exception' => $e
                       ]
                   );
               }
           }
       }
   }

}
