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

class General
{
    public const USE_CHILD_SKU = 'turnto_socialcommerce_configuration/general/use_child_sku';

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
     * Gets the Use Child SKU configuration value
     *
     * @param null|string $scopeType
     * @param int|null|string $scopeCode
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getUseChildSku(
        $scopeType = ScopeInterface::SCOPE_STORES,
        $scopeCode = null
    ) {
        return $this->scopeConfig->getValue(
            self::USE_CHILD_SKU,
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
