<?php

namespace TurnTo\SocialCommerce\Block\Widget;

class Pinboard extends \Magento\CatalogWidget\Block\Product\ProductsList
{
    /**
     * @return array
     */
    public function getProductSkus()
    {
        $productSkus = [];
        
        foreach ($this->getProductCollection()->getItems() as $product) {
            array_push($productSkus, (string)$product->getSku());
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
            'skus' => $this->getProductSkus(),
            'limit' => (int)$this->getLimit(),
            'maxDaysOld' => (int)$this->getMaxDaysOld(),
            'maxCommentsPerBox' => (int)$this->getMaxCommentsPerBox(),
            'progressiveLoading' => (bool)$this->getProgressiveLoading()
        ];

        return json_encode($config, JSON_PRETTY_PRINT);
    }

}