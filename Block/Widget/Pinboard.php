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
 * @copyright  Copyright (c) 2016 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Block\Widget;

class Pinboard extends \Magento\CatalogWidget\Block\Product\ProductsList
{
    /**
     * @return array
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
     * @return mixed
     */
    public function getPinboardConfig()
    {
        $config = [
            'contentType' => $this->getContentType(),
            'title' => $this->getTitle(),
            'limit' => (int)$this->getLimit(),
            'maxDaysOld' => (int)$this->getMaxDaysOld(),
            'maxCommentsPerBox' => (int)$this->getMaxCommentsPerBox(),
            'progressiveLoading' => (bool)$this->getProgressiveLoading()
        ];
        $skus = $this->getProductSkus();
        if (!empty($skus)) {
            $config['skus'] = $skus;
        }

        return json_encode($config, JSON_PRETTY_PRINT);
    }
}
