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

class InstallData implements \Magento\Framework\Setup\InstallDataInterface
{
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
     * InstallData constructor.
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

    /**
     * Install Data Method
     *
     * @param \Magento\Framework\Setup\ModuleDataSetupInterface $setup
     * @param \Magento\Framework\Setup\ModuleContextInterface $context
     */
    public function install(
        \Magento\Framework\Setup\ModuleDataSetupInterface $setup,
        \Magento\Framework\Setup\ModuleContextInterface $context
    ) {
        $setup->startSetup();
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        $this->installHelper->sortAttributeGroup($eavSetup);

        $this->installHelper->addTurnToAttribute(
            $eavSetup,
            InstallHelper::REVIEW_COUNT_ATTRIBUTE_CODE,
            [
                'type'      => 'int',
                'label'     => InstallHelper::REVIEW_COUNT_ATTRIBUTE_LABEL,
                'default'   => 0,
                'note'      => 'Do not edit, this value is replaced nightly.'
            ]
        )->addTurnToAttribute(
            $eavSetup,
            InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE,
            [
                'type'                  => 'varchar',
                'input'                 => 'multiselect',
                'backend'               => '\Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
                'option'                => InstallHelper::AVERAGE_RATING_OPTIONS,
                'label'                 => InstallHelper::AVERAGE_RATING_ATTRIBUTE_LABEL,
                'default'               => 0,
                'filterable'            => true,
                'filterable_in_search'  => true,
                'note'                  => 'Do not edit, this value is replaced nightly.'
            ]
        )->addTurnToAttribute(
            $eavSetup,
            InstallHelper::RATING_ATTRIBUTE_CODE,
            [
                'type'      => 'decimal',
                'label'     => InstallHelper::RATING_ATTRIBUTE_LABEL,
                'default'   => 0.0,
                'note'      => 'Do not edit, this value is replaced nightly.'
            ]
        )->addTurnToAttribute(
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

        $setup->endSetup();
    }


}
