<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 6/8/16
 * Time: 12:00 PM
 */

namespace TurnTo\SocialCommerce\Setup;

use \Magento\Catalog\Model\Product;

class InstallData implements \Magento\Framework\Setup\InstallDataInterface
{
    /**#@+
     * TurnTo related Magento Product attribute keys
     */
    const ATTRIBUTE_SET_ID = 'turnto_socialcommerce';

    const ATTRIBUTE_GROUP_NAME = 'TurnTo Social Commerce';

    const REVIEW_COUNT_ATTRIBUTE_CODE = 'turnto_review_count';

    const REVIEW_COUNT_ATTRIBUTE_LABEL = 'Review Count';

    const AVERAGE_RATING_ATTRIBUTE_CODE = 'turnto_average_rating';

    const AVERAGE_RATING_ATTRIBUTE_LABEL = 'Average Rating';
    /**#@-*/

    protected $eavSetupFactory = null;

    protected $logger = null;

    /**
     * InstallData constructor.
     *
     * @param \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->logger = $logger;
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

        $this->eavSetupFactory->create(['setup' => $setup])
            ->addAttributeSet(Product::ENTITY, self::ATTRIBUTE_SET_ID)
            ->addAttributeGroup(Product::ENTITY, self::ATTRIBUTE_SET_ID, self::ATTRIBUTE_GROUP_NAME)
            ->addAttribute(
                Product::ENTITY,
                self::REVIEW_COUNT_ATTRIBUTE_CODE,
                [
                    'group' => self::ATTRIBUTE_GROUP_NAME,
                    'type' => 'int',
                    'label' => self::REVIEW_COUNT_ATTRIBUTE_LABEL,
                    'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_STORE,
                    'visible' => false,
                    'required' => false,
                    'default' => 0
                ]
            )
            ->addAttribute(
                Product::ENTITY,
                self::AVERAGE_RATING_ATTRIBUTE_CODE,
                [
                    'group' => self::ATTRIBUTE_GROUP_NAME,
                    'type' => 'decimal',
                    'label' => self::AVERAGE_RATING_ATTRIBUTE_LABEL,
                    'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_STORE,
                    'visible' => false,
                    'required' => false,
                    'default' => 0.0
                ]
            );

        $setup->endSetup();
    }
}
