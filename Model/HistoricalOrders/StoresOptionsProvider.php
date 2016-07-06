<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 7/5/16
 * Time: 2:03 PM
 */

namespace TurnTo\SocialCommerce\Model\HistoricalOrders;


class StoresOptionsProvider implements \Magento\Framework\Data\OptionSourceInterface
{
    protected $storeManager = null;

    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
    }

    public function toOptionArray()
    {
        $options = [];

        foreach($this->storeManager->getStores() as $store) {
            $options[] = ['label' => $store->getName(), 'value' => $store->getId()];
        }

        return $options;
    }
}
