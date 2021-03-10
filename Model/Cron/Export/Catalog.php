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
class Catalog
{

    /**
     * @var \TurnTo\SocialCommerce\Model\Manager\Export\Catalog
     */
    protected $catalogExportManager;

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

    public function __construct(
        \TurnTo\SocialCommerce\Model\Manager\Export\Catalog $catalogExportManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \TurnTo\SocialCommerce\Helper\Config $turnToConfig,
        \TurnTo\SocialCommerce\Logger\Monolog $logger
    )
    {
        $this->catalogExportManager = $catalogExportManager;
        $this->storeManager = $storeManager;
        $this->turnToConfig = $turnToConfig;
        $this->logger = $logger;
    }

   public function execute() {
       foreach ($this->storeManager->getStores() as $store) {
           if ($this->turnToConfig->getIsEnabled($store->getCode()) && $this->turnToConfig->getIsProductFeedSubmissionEnabled(
                   $store->getCode()
               )) {
               $page = 1;

               $products = $this->catalogExportManager->getProducts($store, $page, 100);
               while ($products) {
                   try {
                       $feed = $this->catalogExportManager->populateProductFeed($store, $this->catalogExportManager->createFeed($store), $products);
                       $page++;
                       $this->catalogExportManager->transmitFeed($feed, $store, $page);
                       $products = $this->catalogExportManager->getProducts($store, $page, 100);
                   }catch(\Exception $e){
                       $this->logger->error(
                           "TurnTo catalog export error on page number $page.",
                           [
                               'exception' => $e
                           ]
                       );
                       $page++;
                       $products = $this->catalogExportManager->getProducts($store, $page, 100);
                   }
               }
           }
       }
   }

}
