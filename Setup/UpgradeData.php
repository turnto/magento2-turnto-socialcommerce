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

namespace TurnTo\SocialCommerce\Setup;

use \Magento\Catalog\Model\Product;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeData implements \Magento\Framework\Setup\UpgradeDataInterface {

    /**
     * @var \Magento\Eav\Setup\EavSetupFactory|null
     */
    protected $eavSetupFactory = null;

    /**
     * @var null|\Psr\Log\LoggerInterface
     */
    protected $logger = null;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface|null
     */
    protected $storeManager = null;

    /**
     * @var \TurnTo\SocialCommerce\Setup\InstallHelper|null
     */
    protected $installHelper = null;

    /**
     * UpgradeData constructor.
     *
     * @param \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param InstallHelper $installHelper
     */
    public function __construct(
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        InstallHelper $installHelper
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->installHelper = $installHelper;
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context) {

        if (version_compare($context->getVersion(), '3.5.3', '<')) {
            $setup->startSetup();
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

            $this->installHelper->addTurnToAttribute(
                $eavSetup,
                InstallHelper::TURNTO_DISABLED_ATTRIBUTE_CODE,
                [
                    'type'      => 'int',
                    'input'     => 'boolean',
                    'label'     => InstallHelper::TURNTO_ENABLED_ATTRIBUTE_LABEL,
                    'default'   => 0,
                    'note'      => 'Setting this will disable the product in TurnTo regardless of status in Magento.'
                ]
            );
        }
    }
}