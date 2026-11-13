<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Export;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use TurnTo\SocialCommerce\Model\Config;

/**
 * Product attribution and SKU rules shared by every order line sent to TurnTo
 */
class OrderLineItem
{
    public const CONFIGURABLE_PRODUCT_TYPE = 'configurable';

    public const GROUPED_PRODUCT_TYPE = 'grouped';

    /**
     * @var Config
     */
    protected $config;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Id of the product the line is reviewed as
     *
     * Grouped products are ordered as separate lines for each associated product, so those lines are attributed to
     * the grouped parent recorded in the buy request.
     *
     * @param OrderItemInterface $item
     * @return int
     */
    public function getFeedProductId($item): int
    {
        $productOptions = $item->getProductOptions();
        $superProductConfig = $productOptions['info_buyRequest']['super_product_config']
            ?? $productOptions['super_product_config']
            ?? [];
        if (is_array($superProductConfig)
            && ($superProductConfig['product_type'] ?? null) === self::GROUPED_PRODUCT_TYPE
            && !empty($superProductConfig['product_id'])
        ) {
            return (int) $superProductConfig['product_id'];
        }

        return (int) $item->getProductId();
    }

    /**
     * Raw SKU to send for a line, before TurnTo encoding
     *
     * Line item SKUs can carry custom option suffixes or, for dynamic-SKU bundles, every selection's SKU, so they are
     * only used for configurable lines when the simple SKU was not recorded.
     *
     * @param OrderItemInterface $lineItem
     * @param ProductInterface $product Product the line is reviewed as
     * @param int|string $storeId
     * @param ProductInterface|null $orderedProduct Product on the line item itself
     * @return string
     */
    public function getFeedSku($lineItem, $product, $storeId, $orderedProduct = null): string
    {
        if (!$this->config->getUseChildSku($storeId)) {
            return (string) $product->getSku();
        }

        if ($lineItem->getProductType() === self::CONFIGURABLE_PRODUCT_TYPE) {
            $productOptions = $lineItem->getProductOptions();

            return (string) ($productOptions['simple_sku'] ?? $lineItem->getSku());
        }

        return (string) ($orderedProduct ? $orderedProduct->getSku() : $product->getSku());
    }
}
