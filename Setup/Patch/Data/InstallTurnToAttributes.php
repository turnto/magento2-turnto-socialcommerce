<?php

namespace TurnTo\SocialCommerce\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use TurnTo\SocialCommerce\Setup\InstallHelper;

class InstallTurnToAttributes implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var \TurnTo\SocialCommerce\Setup\InstallHelper|null
     */
    private $installHelper;

    /**
     * InstallTurnToAttributes constructor.
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param InstallHelper $installHelper
     * @param WriterInterface $configWriter
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        InstallHelper $installHelper,
        WriterInterface $configWriter,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->installHelper = $installHelper;
        $this->configWriter = $configWriter;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function apply()
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
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

        // use turnto's remote teaser code rather then local code for new installs
        $this->configWriter->save('turnto_socialcommerce_configuration/teaser/use_local_teaser_code', 0);
    }
}
