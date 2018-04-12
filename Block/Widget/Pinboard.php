<?php
/**
 * TurnTo_SocialCommerce
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * @copyright  Copyright (c) 2018 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Block\Widget;

use Magento\Framework\Exception\LocalizedException;
use TurnTo\SocialCommerce\Model\Data\PinboardConfigFactory;

// Provide backwards compatibility with Magento < 2.2.x
if (!class_exists('Magento\Framework\Serialize\Serializer\Json')) {
    class Json
    {
    }
} else {
    class_alias(
        'Magento\Framework\Serialize\Serializer\Json',
        'TurnTo\SocialCommerce\Block\Widget\Json'
    );
}

/**
 * @method getContentType(): string
 * @method getTitle(): string
 * @method getLimit(): string
 * @method getMaxDaysOld(): string
 * @method getMaxCommentsPerBox(): string
 * @method getProgressiveLoading(): string
 */
class Pinboard extends \Magento\CatalogWidget\Block\Product\ProductsList
{
    /**
     * @var PinboardConfigFactory
     */
    protected $pinboardConfigFactory;

    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Catalog\Model\Product\Visibility $catalogProductVisibility,
        \Magento\Framework\App\Http\Context $httpContext,
        \Magento\Rule\Model\Condition\Sql\Builder $sqlBuilder,
        \Magento\CatalogWidget\Model\Rule $rule,
        \Magento\Widget\Helper\Conditions $conditionsHelper,
        array $data = [],
        Json $json = null,
        PinboardConfigFactory $pinboardConfigFactory
    )
    {
        // Call the parent class with the proper arguments based on the availability of a Magento 2.2.x class
        call_user_func_array(
            [__CLASS__, 'parent::__construct'],
            array_slice(
                func_get_args(),
                0,
                // -1 excludes our custom class, -2 excludes both our class and the JSON class that doesn't exist
                class_exists('Magento\Framework\Serialize\Serializer\Json') ? -1 : -2
            )
        );

        $this->pinboardConfigFactory = $pinboardConfigFactory;
    }

    /**
     * @return array
     * @throws LocalizedException
     */
    public function getProductSkus()
    {
        $productSkus = [];
        if (!empty($this->getConditions()->getConditions())) {
            foreach ($this->getProductCollection()->getItems() as $product) {
                $productSkus[] = (string)$product->getSku();
            }
        }

        return $productSkus;
    }

    /**
     * Creates a TurnTo config block and outputs its html content
     * @return string
     */
    public function getTurnToConfigHtml()
    {
        /** @var \TurnTo\SocialCommerce\Block\TurnToConfig $pinboardBlock */
        try {
            $pinboardBlock = $this->getLayout()->createBlock(
                \TurnTo\SocialCommerce\Block\TurnToConfig::class,
                'turnto.config.pinboard'
            );
        } catch (LocalizedException $e) {
            return '';
        }

        $pinboardBlock->setConfigData($this->pinboardConfigFactory->create(['pinboardBlock' => $this]));

        return $pinboardBlock->toHtml();
    }
}
