<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Plugin\Product\View\Type;

use InvalidArgumentException;
use Magento\ConfigurableProduct\Block\Product\View\Type\Configurable as ConfigurableProductTypeBlock;
use Magento\Framework\Serialize\Serializer\Json;
use TurnTo\SocialCommerce\Model\Product;

class Configurable
{
    /**
     * @var Product
     */
    protected $product;
    /**
     * @var Json
     */
    protected $json;

    /**
     * @param Product $product
     * @param Json $json
     */
    public function __construct(
        Product $product,
        Json $json
    ) {
        $this->product = $product;
        $this->json = $json;
    }

    /**
     * @param ConfigurableProductTypeBlock $subject
     * @param $result
     * @return bool|string
     */
    public function afterGetJsonConfig(
        ConfigurableProductTypeBlock $subject,
        $result
    ) {
        try {
            $data = $this->json->unserialize($result);
        } catch (InvalidArgumentException $e) {
            return $result;
        }

        if (!empty($data['images'])) {
            foreach ($subject->getAllowProducts() as $product) {
                if (isset($data['images'][$product->getId()][0])) {
                    $data['images'][$product->getId()][0]['sku'] = $this->product->turnToSafeEncoding(
                        $product->getSku()
                    );
                }
            }
        }

        return $this->json->serialize($data);
    }
}
