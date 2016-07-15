<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 7/5/16
 * Time: 9:50 AM
 */

namespace TurnTo\SocialCommerce\Controller\Adminhtml\System\HistoricalOrders;

use Magento\Framework\App\Filesystem\DirectoryList;
use TurnTo\SocialCommerce\Model\Export\Orders;

class Download extends \Magento\Backend\App\Action
{
    /**
     * Filename used for the client side download file name
     */
    const DOWNLOAD_FILENAME = 'historical_orders.tsv';
    
    /**
     * @var \Magento\Framework\Controller\Result\RawFactory|null
     */
    protected $resultRawFactory = null;

    /**
     * @var \Magento\Framework\App\Response\Http\FileFactory|null
     */
    protected $fileFactory = null;

    /**
     * @var DirectoryList|null
     */
    protected $directoryList = null;

    /**
     * @var null|\TurnTo\SocialCommerce\Logger\Monolog
     */
    protected $logger = null;

    /**
     * @var null|\TurnTo\SocialCommerce\Model\Export\Orders
     */
    protected $ordersModel = null;

    /**
     * Get constructor.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Controller\Result\RawFactory $resultRawFactory
     * @param \Magento\Framework\App\Response\Http\FileFactory $fileFactory
     * @param DirectoryList $directoryList
     * @param \TurnTo\SocialCommerce\Model\Export\Orders $ordersModel
     * @param \TurnTo\SocialCommerce\Logger\Monolog $logger
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList,
        \TurnTo\SocialCommerce\Model\Export\Orders $ordersModel,
        \TurnTo\SocialCommerce\Logger\Monolog $logger
    ) {
        parent::__construct($context);

        $this->resultRawFactory = $resultRawFactory;
        $this->fileFactory = $fileFactory;
        $this->directoryList = $directoryList;
        $this->ordersModel = $ordersModel;
        $this->logger = $logger;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Exception
     */
    public function execute()
    {
        $date = $this->getRequest()->getParam('from_date');
        $storeId = $this->getRequest()->getParam('store_ids');
        $feedData = $this->ordersModel->getOrdersFeed($storeId, $date);

        return $this->fileFactory->create(
            self::DOWNLOAD_FILENAME,
            $feedData,
            DirectoryList::TMP,
            Orders::FEED_MIME
        );
    }
}
