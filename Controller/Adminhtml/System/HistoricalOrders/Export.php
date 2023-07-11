<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Controller\Adminhtml\System\HistoricalOrders;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Store\Model\StoreManagerInterface;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Export\Orders;
use Magento\Framework\Controller\ResultFactory;

class Export extends Action
{
    /**
     * @var Orders
     */
    protected $ordersExport;
    /**
     * @var Monolog
     */
    protected $logger;

    /**
     * @var DateTimeFactory
     */
    protected $dateTimeFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var FeedClient
     */
    protected $feedClient;

    /**
     * @param Context $context
     * @param Orders $ordersExport
     * @param Monolog $logger
     * @param DateTimeFactory $dateTimeFactory
     * @param StoreManagerInterface $storeManager
     * @param FeedClient $feedClient
     */
    public function __construct(
        Context $context,
        Orders $ordersExport,
        Monolog $logger,
        DateTimeFactory $dateTimeFactory,
        StoreManagerInterface $storeManager,
        FeedClient $feedClient
    ) {
        parent::__construct($context);
        $this->ordersExport = $ordersExport;
        $this->logger = $logger;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->storeManager = $storeManager;
        $this->feedClient = $feedClient;
    }

    /**
     * @return ResultInterface
     */
    public function execute()
    {
        $fromDate = $this->getRequest()->getParam('from_date');
        $toDate = $this->getRequest()->getParam('to_date');
        $storeId = $this->getRequest()->getParam('store_ids');

        try {
            $fromDate = $this->dateTimeFactory->create($fromDate, new \DateTimeZone('UTC'));
            // A normal user would expect the "To" date to include orders on that date. However, by default the field will
            // hold a value where the time is YYYY-MM-DD 00:00:00.000000. The below code will add one day the "To" date then
            // subtract 1 second so that all orders placed before YYYY-MM-DD 23:59:59:000000 will be picked up.
            $toDate = $this->dateTimeFactory
                ->create($toDate, new \DateTimeZone('UTC'))
                ->add(new \DateInterval('P1D'))
                ->sub(new \DateInterval('PT1S'));

            $feedData = $this->ordersExport->getOrdersFeed($storeId, $fromDate, $toDate, true);
            $store = $this->storeManager->getStore($storeId);
            $this->feedClient->transmitFeedFile($feedData, Orders::FEED_NAME, Orders::FEED_STYLE, $store->getCode());
            $this->messageManager->addSuccessMessage('Orders exported successfully.');
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage('There was an issue processing your request. Please try again later.');
            $this->logger->error($e->getMessage());
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('*/*/');
    }
}
