<?php
/**
 * TurnTo_SocialCommerce
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright  Copyright (c) 2018 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Controller\Adminhtml\System\HistoricalOrders;


use TurnTo\SocialCommerce\Model\Export\Orders;
use Magento\Framework\Controller\ResultFactory;

class Export extends \Magento\Backend\App\Action
{
    /**
     * @var \TurnTo\SocialCommerce\Logger\Monolog
     */
    protected $logger;

    /**
     * @var \Magento\Framework\Intl\DateTimeFactory
     */
    protected $dateTimeFactory;

    /**
     * @var \TurnTo\SocialCommerce\Model\Manager\Export\Orders
     */
    protected $orderExportManager;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager = null;

    /**
     * Export constructor.
     * @param \Magento\Backend\App\Action\Context $context
     * @param Orders $ordersModel
     * @param \TurnTo\SocialCommerce\Logger\Monolog $logger
     * @param \Magento\Framework\Intl\DateTimeFactory $dateTimeFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \TurnTo\SocialCommerce\Model\Export\Orders $ordersModel,
        \TurnTo\SocialCommerce\Logger\Monolog $logger,
        \Magento\Framework\Intl\DateTimeFactory $dateTimeFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->orderExportManager = $ordersModel;
        $this->logger = $logger;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Exception
     */
    public function execute()
    {
        $fromDate = $this->getRequest()->getParam('from_date');
        $toDate = $this->getRequest()->getParam('to_date');
        $storeId = $this->getRequest()->getParam('store_ids');

        $fromDate = $this->dateTimeFactory->create($fromDate, new \DateTimeZone('UTC'));
        // A normal user would expect the "To" date to include orders on that date. However, by default the field will
        // hold a value where the time is YYYY-MM-DD 00:00:00.000000. The below code will add one day the "To" date then
        // subtract 1 second so that all orders placed before YYYY-MM-DD 23:59:59:000000 will be picked up.
        $toDate = $this->dateTimeFactory
            ->create($toDate, new \DateTimeZone('UTC'))
            ->add(new \DateInterval('P1D'))
            ->sub(new \DateInterval('PT1S'));

        try {
            $orders = $this->orderExportManager->getOrders($storeId, $fromDate, $toDate);
            $feedData = $this->orderExportManager->generateOrdersFeed($storeId, $orders, true);
            $store = $this->storeManager->getStore($storeId);
            $this->orderExportManager->transmitFeed($feedData, $store);
            $this->messageManager->addSuccessMessage('Orders exported successfully.');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage('There was an issue processing your request. Please try again later.');
            $this->logger->error($e->getMessage());
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('*/*/');
    }
}
