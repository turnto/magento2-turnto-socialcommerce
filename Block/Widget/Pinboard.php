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

namespace TurnTo\SocialCommerce\Block\Widget;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use TurnTo\SocialCommerce\Model\Data\PinboardConfigFactory;

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
        PinboardConfigFactory $pinboardConfigFactory,
        array $data = [],
        Json $json = null
    )
    {
        parent::__construct(
            $context,
            $productCollectionFactory,
            $catalogProductVisibility,
            $httpContext,
            $sqlBuilder,
            $rule,
            $conditionsHelper,
            $data,
            $json
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
    public function getTurnToConfigHtml(): string
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
