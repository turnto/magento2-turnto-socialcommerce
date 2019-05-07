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

namespace TurnTo\SocialCommerce\Plugin\Product\View\Type;

use Magento\ConfigurableProduct\Block\Product\View\Type\Configurable as ConfigurableProductTypeBlock;
use TurnTo\SocialCommerce\Helper\Product;

class Configurable
{
    /**
     * @var Product
     */
    protected $productHelper;

    /**
     * @param Product $productHelper
     */
    public function __construct(Product $productHelper)
    {
        $this->productHelper = $productHelper;
    }

    public function afterGetJsonConfig(ConfigurableProductTypeBlock $subject, $result)
    {
        try {
            $data = \Zend_Json::decode($result);
        } catch (\Zend_Json_Exception $e) {
            return $result;
        }

        foreach ($subject->getAllowProducts() as $product) {
            if (isset($data['images'][$product->getId()][0])) {
                $data['images'][$product->getId()][0]['sku'] = $this->productHelper->turnToSafeEncoding(
                    $product->getSku()
                );
            }
        }

        return \Zend_Json::encode($data);
    }
}
