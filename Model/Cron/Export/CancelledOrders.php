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
class CancelledOrders
{

    /**
     * @var \TurnTo\SocialCommerce\Model\Manager\Export\CancelledOrders
     */
    protected $cancelledOrderExportManager;

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
        \TurnTo\SocialCommerce\Model\Manager\Export\CancelledOrders $cancelledOrderExportManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \TurnTo\SocialCommerce\Helper\Config $turnToConfig,
        \TurnTo\SocialCommerce\Logger\Monolog $logger,
        \Magento\Framework\Intl\DateTimeFactory $dateTimeFactory
    )
    {
        $this->cancelledOrderExportManager = $cancelledOrderExportManager;
        $this->storeManager = $storeManager;
        $this->turnToConfig = $turnToConfig;
        $this->logger = $logger;
        $this->dateTimeFactory = $dateTimeFactory;
    }

   public function execute() {
       foreach ($this->storeManager->getStores() as $store) {
           if ($this->turnToConfig->getIsEnabled($store->getCode()) && $this->turnToConfig->getIsCancelledOrdersFeedEnabled(
                   $store->getCode()
               )) {
               try {
                   $fromDate = $this->dateTimeFactory->create('now', new \DateTimeZone('UTC'))->sub(new \DateInterval('P80D'));
                   $toDate = $this->dateTimeFactory->create('now', new \DateTimeZone('UTC'));
                   $cancelledOrders = $this->cancelledOrderExportManager->getCanceledOrders($store->getId(), $fromDate, $toDate);
                   $cancelledOrderData = $this->cancelledOrderExportManager->formatCancelledOrderData($cancelledOrders);
                   $feedData = $this->cancelledOrderExportManager->getCanceledOrdersFeed(
                       $store->getId(),
                       $cancelledOrderData
                   );
                   $this->transmitFeed($feedData, $store);
               } catch (\Exception $e) {
                   $this->logger->error(
                       'An error occurred while processing or transmitting canceled Orders Feed Cron',
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
