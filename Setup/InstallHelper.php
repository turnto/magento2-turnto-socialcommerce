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

use Magento\Catalog\Model\Product;

class InstallHelper {

    /**
     * TurnTo related Magento Product attribute keys
     */
    const ATTRIBUTE_GROUP_NAME = 'TurnTo Social Commerce';

    // Custom enabled/disable setting for TUrnTo products
    const TURNTO_DISABLED_ATTRIBUTE_CODE = 'turnto_disabled';
    const TURNTO_ENABLED_ATTRIBUTE_LABEL = 'Disabled (in TurnTo)';

    // Keeps track of the number of reviews
    const REVIEW_COUNT_ATTRIBUTE_CODE = 'turnto_review_count';
    const REVIEW_COUNT_ATTRIBUTE_LABEL = 'Review Count';

    // Average Rating Decimal
    const RATING_ATTRIBUTE_CODE = 'turnto_rating';
    const RATING_ATTRIBUTE_LABEL = 'Rating';

    // Uses labels to record average rating
    const AVERAGE_RATING_ATTRIBUTE_CODE = 'turnto_average_rating';
    const AVERAGE_RATING_ATTRIBUTE_LABEL = 'Average Rating';

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

    const AVERAGE_RATING_OPTIONS = [
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
     * InstallHelper constructor.
     *
     * @param \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param InstallHelper $installHelper
     */
    public function __construct(
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
    }

    /**
     * Used to simplify attribute creation
     *
     * @param $eavSetup
     * @param $attributeCode
     * @param $config
     * @return $this
     */
    public function addTurnToAttribute($eavSetup, $attributeCode, $config) {

        $defaultConfig = [
            'visible' => true,
            'group' => self::ATTRIBUTE_GROUP_NAME,
            'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_GLOBAL,
            'required' => false,
            'user_in_product_listing' => true,
            'is_visible_on_front' => true
        ];

        $attributeConfig = array_merge($defaultConfig, $config);

        $eavSetup->addAttribute(
            Product::ENTITY,
            $attributeCode,
            $attributeConfig);

        return $this;
    }

    /**
     * This function makes sure that the TurnTo Attribute group comes after the "image-management" attribute group.
     * Not entirely sure why this specific sort order was picked, but it was
     *
     * @param $eavSetup
     */
    public function sortAttributeGroup($eavSetup) {
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
    }
}