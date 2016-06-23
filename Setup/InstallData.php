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
    const ATTRIBUTE_GROUP_NAME = 'TurnTo Social Commerce';
    
    const REVIEW_COUNT_ATTRIBUTE_CODE = 'turnto_review_count';

    const REVIEW_COUNT_ATTRIBUTE_LABEL = 'Review Count';

    const AVERAGE_RATING_ATTRIBUTE_CODE = 'turnto_average_rating';

    const AVERAGE_RATING_ATTRIBUTE_LABEL = 'Average Rating';

    const RATING_ATTRIBUTE_CODE = 'turnto_rating';

    const RATING_ATTRIBUTE_LABEL = 'Rating';

    const ONE_STAR_LABEL = '1 Star and Above';

    const TWO_STAR_LABEL = '2 Star and Above';

    const THREE_STAR_LABEL = '3 Star and Above';

    const FOUR_STAR_LABEL = '4 Star and Above';

    const FIVE_STAR_LABEL = '5 Star and Above';

    const RATING_FILTER_VALUES = [
        self::ONE_STAR_LABEL,
        self::TWO_STAR_LABEL,
        self::THREE_STAR_LABEL,
        self::FOUR_STAR_LABEL,
        self::FIVE_STAR_LABEL
    ];
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

        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        foreach ($eavSetup->getAllAttributeSetIds(Product::ENTITY) as $setId) {
            $groupCollection = $eavSetup->getAttributeGroupCollectionFactory();
            $sortOrder = 0;
            foreach($groupCollection->setAttributeSetFilter($setId) as $group) {
                if ($group->getAttributeGroupCode() === 'image-management') {
                    $sortOrder = (int)$group->getSortOrder();
                    break;
                }
            }
            $eavSetup->addAttributeGroup(Product::ENTITY, $setId, self::ATTRIBUTE_GROUP_NAME, $sortOrder + 1);
        }
        
        $eavSetup->addAttribute(
                Product::ENTITY,
                self::REVIEW_COUNT_ATTRIBUTE_CODE,
                [
                    'visible' => true,
                    'group' => self::ATTRIBUTE_GROUP_NAME,
                    'type' => 'int',
                    'label' => self::REVIEW_COUNT_ATTRIBUTE_LABEL,
                    'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_STORE,
                    'required' => false,
                    'user_defined' => false,
                    'default' => 0,
                    'used_in_product_listing' => true,
                    'is_visible_on_front' => true,
                    'note' => 'Do not edit, this value is replaced nightly.'
                ]
            )
            ->addAttribute(
                Product::ENTITY,
                self::AVERAGE_RATING_ATTRIBUTE_CODE,
                [
                    'visible' => true,
                    'group' => self::ATTRIBUTE_GROUP_NAME,
                    'type' => 'varchar',
                    'input' => 'multiselect',
                    'backend' => '\Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
                    'label' => self::AVERAGE_RATING_ATTRIBUTE_LABEL,
                    'used_in_product_listing' => true,
                    'is_visible_on_front' => true,
                    'user_defined' => false,
                    'filterable' => true,
                    'filterable_in_search' => true,
                    'default' => 0,
                    'option' => [
                        'values' => self::RATING_FILTER_VALUES
                    ],
                    'note' => 'Do not edit, this value is replaced nightly.'
                ]
            )
            ->addAttribute(
                Product::ENTITY,
                self::RATING_ATTRIBUTE_CODE,
                [
                    'visible' => true,
                    'group' => self::ATTRIBUTE_GROUP_NAME,
                    'type' => 'decimal',
                    'label' => self::RATING_ATTRIBUTE_LABEL,
                    'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_STORE,
                    'required' => false,
                    'default' => 0.0,
                    'used_in_product_listing' => true,
                    'is_visible_on_front' => true,
                    'user_defined' => false,
                    'note' => 'Do not edit, this value is replaced nightly.'
                ]
            );

        $setup->endSetup();
    }
}
