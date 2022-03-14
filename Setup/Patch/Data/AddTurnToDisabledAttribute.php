<?php

namespace TurnTo\SocialCommerce\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use TurnTo\SocialCommerce\Setup\InstallHelper;

class AddTurnToDisabledAttribute implements DataPatchInterface
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
     * @var \TurnTo\SocialCommerce\Setup\InstallHelper|null
     */
    private $installHelper;

    /**
     * InstallTurnToAttributes constructor.
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param InstallHelper $installHelper
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        InstallHelper $installHelper,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->installHelper = $installHelper;
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
        $attribute = $eavSetup->getAttribute(Product::ENTITY, InstallHelper::TURNTO_DISABLED_ATTRIBUTE_CODE);
        if (!$attribute) {
            $this->installHelper->addTurnToAttribute(
                $eavSetup,
                InstallHelper::TURNTO_DISABLED_ATTRIBUTE_CODE,
                [
                    'type' => 'int',
                    'input' => 'boolean',
                    'label' => InstallHelper::TURNTO_ENABLED_ATTRIBUTE_LABEL,
                    'default' => 0,
                    'note' => 'Setting this will disable the product in TurnTo regardless of status in Magento.'
                ]
            );
        }
    }
}
