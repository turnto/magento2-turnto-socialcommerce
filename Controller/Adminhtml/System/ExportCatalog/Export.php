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

namespace TurnTo\SocialCommerce\Controller\Adminhtml\System\ExportCatalog;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Export extends \Magento\Backend\App\Action
{
    protected $resultJsonFactory;

    /**
     * @var \TurnTo\SocialCommerce\Model\Export\Catalog
     */
    protected $catalogExport;

    /**
     * @var \Psr\Log\LoggerInterface $logger
     */
    protected $logger;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param Data $helper
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        \TurnTo\SocialCommerce\Model\Export\Catalog $catalogExport,
        \TurnTo\SocialCommerce\Logger\Monolog $logger
    )
    {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->catalogExport = $catalogExport;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * Collect relations data
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultJsonFactory->create();

        try {
            $this->catalogExport->cronUploadFeed();
        } catch (\Exception $e) {
            $this->logger->error("An error occurred while transmitting the catalog feed to TurnTo. [Manual Export]",
                [
                    'exception' => $e,
                    'message' => $e->getMessage()
                ]
            );
            return $result->setData(['success' => false]);
        }

        return $result->setData(['success' => true]);
    }
}
