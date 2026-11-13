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
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Store\Model\StoreManagerInterface;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Export\Orders;
use TurnTo\SocialCommerce\Model\Export\TemporaryFeedFile;

class Export extends Action implements HttpPostActionInterface
{
    /**
     * Matches the resource of the Historical Orders Export menu item that opens this form.
     */
    public const ADMIN_RESOURCE = 'Magento_Backend::content';

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
     * @var TemporaryFeedFile
     */
    protected $temporaryFeedFile;

    /**
     * @param Context $context
     * @param Orders $ordersExport
     * @param Monolog $logger
     * @param DateTimeFactory $dateTimeFactory
     * @param StoreManagerInterface $storeManager
     * @param TemporaryFeedFile $temporaryFeedFile
     */
    public function __construct(
        Context $context,
        Orders $ordersExport,
        Monolog $logger,
        DateTimeFactory $dateTimeFactory,
        StoreManagerInterface $storeManager,
        TemporaryFeedFile $temporaryFeedFile
    ) {
        parent::__construct($context);
        $this->ordersExport = $ordersExport;
        $this->logger = $logger;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->storeManager = $storeManager;
        $this->temporaryFeedFile = $temporaryFeedFile;
    }

    /**
     * Export and transmit the orders placed in the requested date range
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $fromDate = $this->getRequest()->getParam('from_date');
        $toDate = $this->getRequest()->getParam('to_date');
        $storeId = $this->getRequest()->getParam('store_ids');

        try {
            $store = $this->storeManager->getStore($storeId);
            $fromDate = $this->dateTimeFactory->create($fromDate, new DateTimeZone('UTC'));
            // Subtract one second
            $toDate = $this->dateTimeFactory
                ->create($toDate, new DateTimeZone('UTC'))
                ->add(new DateInterval('P1D'))
                ->sub(new DateInterval('PT1S'));

            $feedPath = $this->ordersExport->createOrdersFeedFile($storeId, $fromDate, $toDate, true);
            $this->temporaryFeedFile->transmitAndDelete(
                $feedPath,
                Orders::FEED_NAME,
                Orders::FEED_STYLE,
                $store->getCode()
            );
            $this->messageManager->addSuccessMessage(__('Orders exported successfully.'));
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(
                __('There was an issue processing your request. Please try again later.')
            );
            $this->logger->error(
                'Historical orders export request failed',
                [
                    'store_id' => $storeId,
                    'from_date' => $this->getRequest()->getParam('from_date'),
                    'to_date' => $this->getRequest()->getParam('to_date'),
                    'exception' => $e
                ]
            );
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $resultRedirect->setPath('*/*/');
    }
}
