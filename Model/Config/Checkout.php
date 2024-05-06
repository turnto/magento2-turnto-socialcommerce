<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TurnTo\SocialCommerce\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Checkout
{
    public const CUSTOMER_NAME_FALLBACK = 'turnto_socialcommerce_configuration/checkout_comments/js_order_feed_customer_name_fallback';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * Gets the Comments Teaser Enabled configuration value
     * @param null|string $scopeType
     * @param int|null|string $scopeCode
     * @return string
     * @throws NoSuchEntityException
     */
    public function getJSOrderFeedCustomerNameFallback(
        $scopeType = ScopeInterface::SCOPE_STORES,
        $scopeCode = null
    ) {
        return $this->scopeConfig->getValue(
            self::CUSTOMER_NAME_FALLBACK,
            $scopeType,
            $scopeCode ?: $this->getStoreCode()
        );
    }

    /**
     * Gets the store code from the currently set/scoped store
     * @return string
     * @throws NoSuchEntityException
     */
    public function getStoreCode()
    {
        return $this->storeManager->getStore()->getCode();
    }
}
