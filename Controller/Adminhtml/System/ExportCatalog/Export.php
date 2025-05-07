<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Controller\Adminhtml\System\ExportCatalog;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Export\Catalog;

class Export extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var Catalog
     */
    protected $catalogExport;

    /**
     * @var Monolog
     */
    protected $logger;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param Catalog $catalogExport
     * @param Monolog $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Catalog $catalogExport,
        Monolog $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->catalogExport = $catalogExport;
        $this->logger = $logger;
    }

    /**
     * Collect relations data
     *
     * @return Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $this->catalogExport->cronUploadFeed();
        } catch (Exception $e) {
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
