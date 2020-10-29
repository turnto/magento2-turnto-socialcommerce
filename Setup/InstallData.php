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

    const ONE_STAR_LABEL = '1 Star & Up';

    const TWO_STAR_LABEL = '2 Stars & Up';

    const THREE_STAR_LABEL = '3 Stars & Up';

    const FOUR_STAR_LABEL = '4 Stars & Up';

    const FIVE_STAR_LABEL = '5 Stars';

    const RATING_FILTER_VALUES = [
        self::ONE_STAR_LABEL,
        self::TWO_STAR_LABEL,
        self::THREE_STAR_LABEL,
        self::FOUR_STAR_LABEL,
        self::FIVE_STAR_LABEL
    ];

    /**#@-*/

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
     * @var \Magento\Framework\App\Config\Storage\WriterInterface
     */
    protected $configWriter;

    /**
     * InstallData constructor.
     *
     * @param \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->configWriter = $configWriter;
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
            foreach ($groupCollection->setAttributeSetFilter($setId) as $group) {
                if ($group->getAttributeGroupCode() === 'image-management') {
                    $sortOrder = (int)$group->getSortOrder();
                    break;
                }
            }
            $eavSetup->addAttributeGroup(Product::ENTITY, $setId, self::ATTRIBUTE_GROUP_NAME, $sortOrder + 1);
        }

        $averageRatingOption = [
            'attribute_id' => null,
            'value' => [
                'star_rating_1' => [self::ONE_STAR_LABEL],
                'star_rating_2' => [self::TWO_STAR_LABEL],
                'star_rating_3' => [self::THREE_STAR_LABEL],
                'star_rating_4' => [self::FOUR_STAR_LABEL],
                'star_rating_5' => [self::FIVE_STAR_LABEL]
            ],
            'order' => [
                'star_rating_1' => 4,
                'star_rating_2' => 3,
                'star_rating_3' => 2,
                'star_rating_4' => 1,
                'star_rating_5' => 0
            ]
        ];

        $eavSetup->addAttribute(
            Product::ENTITY,
            self::REVIEW_COUNT_ATTRIBUTE_CODE,
            [
                    'visible' => true,
                    'group' => self::ATTRIBUTE_GROUP_NAME,
                    'type' => 'int',
                    'label' => self::REVIEW_COUNT_ATTRIBUTE_LABEL,
                    'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_GLOBAL,
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
                    'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_GLOBAL,
                    'required' => false,
                    'used_in_product_listing' => true,
                    'is_visible_on_front' => true,
                    'user_defined' => false,
                    'filterable' => true,
                    'filterable_in_search' => true,
                    'default' => 0,
                    'option' => $averageRatingOption,
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
                    'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_GLOBAL,
                    'required' => false,
                    'default' => 0.0,
                    'used_in_product_listing' => true,
                    'is_visible_on_front' => true,
                    'user_defined' => false,
                    'note' => 'Do not edit, this value is replaced nightly.'
                ]
            );

       // use turnto's remote teaser code rather then local code for new installs
        $this->configWriter->save('turnto_socialcommerce_configuration/teaser/use_local_teaser_code', 0);

        $setup->endSetup();
    }
}
