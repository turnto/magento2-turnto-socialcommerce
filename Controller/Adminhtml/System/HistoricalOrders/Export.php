<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Controller\Adminhtml\System\HistoricalOrders;

use DateInterval;
use DateTimeZone;
use Exception;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Export\Orders;

class Export implements HttpPostActionInterface
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var ResultFactory
     */
    protected $resultFactory;

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
     * @param RequestInterface $request
     * @param ManagerInterface $messageManager
     * @param ResultFactory $resultFactory
     * @param Orders $ordersExport
     * @param Monolog $logger
     * @param DateTimeFactory $dateTimeFactory
     * @param StoreManagerInterface $storeManager
     * @param FeedClient $feedClient
     */
    public function __construct(
        RequestInterface $request,
        ManagerInterface $messageManager,
        ResultFactory $resultFactory,
        Orders $ordersExport,
        Monolog $logger,
        DateTimeFactory $dateTimeFactory,
        StoreManagerInterface $storeManager,
        FeedClient $feedClient
    ) {
        $this->request = $request;
        $this->messageManager = $messageManager;
        $this->resultFactory = $resultFactory;
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
        $fromDate = $this->request->getParam('from_date');
        $toDate = $this->request->getParam('to_date');
        $storeId = $this->request->getParam('store_ids');

        try {
            $fromDate = $this->dateTimeFactory->create($fromDate, new DateTimeZone('UTC'));
            // A normal user would expect the "To" date to include orders on that date. However, by default the field will
            // hold a value where the time is YYYY-MM-DD 00:00:00.000000. The below code will add one day the "To" date then
            // subtract 1 second so that all orders placed before YYYY-MM-DD 23:59:59:000000 will be picked up.
            $toDate = $this->dateTimeFactory
                ->create($toDate, new DateTimeZone('UTC'))
                ->add(new DateInterval('P1D'))
                ->sub(new DateInterval('PT1S'));

            $feedPath = $this->ordersExport->getOrdersFeed($storeId, $fromDate, $toDate, true);
            $store = $this->storeManager->getStore($storeId);
            try {
                $this->feedClient->transmitFeedFile(
                    $feedPath,
                    Orders::FEED_NAME,
                    Orders::FEED_STYLE,
                    $store->getCode(),
                    true
                );
                $this->messageManager->addSuccessMessage('Orders exported successfully.');
            } finally {
                if (is_string($feedPath) && is_file($feedPath)) {
                    unlink($feedPath);
                }
            }
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage('There was an issue processing your request. Please try again later.');
            $this->logger->error(
                'Historical orders export request failed',
                ['exception' => $e]
            );
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $resultRedirect->setPath('*/*/');
    }
}
