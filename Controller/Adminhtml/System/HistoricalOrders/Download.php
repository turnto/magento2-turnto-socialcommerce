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

class Download extends \Magento\Backend\App\Action
{
    /**
     * @var null|\TurnTo\SocialCommerce\Logger\Monolog
     */
    protected $logger = null;

    /**
     * @var \Magento\Framework\Intl\DateTimeFactory|null
     */
    protected $dateTimeFactory = null;

    /**
     * @var null|\TurnTo\SocialCommerce\Model\Export\Orders
     */
    protected $ordersModel = null;

    /**
     * Download constructor.
     *
     * @param \Magento\Backend\App\Action\Context     $context
     * @param Orders                                  $ordersModel
     * @param \TurnTo\SocialCommerce\Logger\Monolog   $logger
     * @param \Magento\Framework\Intl\DateTimeFactory $dateTimeFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \TurnTo\SocialCommerce\Model\Export\Orders $ordersModel,
        \TurnTo\SocialCommerce\Logger\Monolog $logger,
        \Magento\Framework\Intl\DateTimeFactory $dateTimeFactory
    ) {
        parent::__construct($context);

        $this->ordersModel = $ordersModel;
        $this->logger = $logger;
        $this->dateTimeFactory = $dateTimeFactory;
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
            $feedData = $this->ordersModel->getOrdersFeed($storeId, $fromDate, $toDate, true, true);
            $this->messageManager->addSuccessMessage('Orders exported successfully.');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage('There was an issue processing your request. Please try again later.');
            $this->logger->error($e->getMessage());
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('*/*/');
    }
}
