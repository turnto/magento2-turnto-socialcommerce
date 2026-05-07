<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Plugin\Block\Product\View\Type;

use InvalidArgumentException;
use Magento\ConfigurableProduct\Block\Product\View\Type\Configurable;
use Magento\Framework\Serialize\Serializer\Json;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Product as TurnToProduct;

class ConfigurablePlugin
{
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var TurnToProduct
     */
    protected $turnToProduct;
    /**
     * @var Json
     */
    protected $json;
    /**
     * @var Monolog
     */
    protected $logger;

    /**
     * ConfigurablePlugin constructor.
     *
     * @param Config $config
     * @param Json $json
     * @param Monolog $logger
     * @param TurnToProduct $turnToProduct
     */
    public function __construct(
        Config $config,
        Json $json,
        Monolog $logger,
        TurnToProduct $turnToProduct
    ) {
        $this->config = $config;
        $this->json = $json;
        $this->logger = $logger;
        $this->turnToProduct = $turnToProduct;
    }

    /**
     * @param Configurable $subject
     * @param $result
     * @return string
     */
    public function afterGetJsonConfig(
        Configurable $subject,
        $result
    ): string {
        try {
            $decodedResult = $this->json->unserialize($result);
        } catch (InvalidArgumentException $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return $result;
        }

        if (!is_array($decodedResult) || empty($decodedResult['productId'])) {
            return $result;
        }

        $result = $decodedResult;
        $result['useChild'] = $this->config->getUseChildSku();

        $parentProduct = $subject->getProduct();
        if (!$parentProduct || !$parentProduct->getId()) {
            return $this->json->serialize($result);
        }

        $result['parentSku'] = $this->turnToProduct->turnToSafeEncoding($parentProduct->getSku());

        if ($result['useChild']) {
            $childSkuMap = $this->buildChildSkuMap($result, $subject);
            if (!empty($childSkuMap)) {
                $result['childSkuMap'] = $childSkuMap;
            }
        }

        return $this->json->serialize($result);
    }

    /**
     * @param array       $jsonConfig
     * @param Configurable $subject
     * @return array
     */
    protected function buildChildSkuMap(array $jsonConfig, Configurable $subject): array
    {
        $childSkuMap = [];
        if (!empty($jsonConfig['sku']) && is_array($jsonConfig['sku'])) {
            foreach ($jsonConfig['sku'] as $productId => $sku) {
                if (!is_scalar($sku)) {
                    continue;
                }
                $childSkuMap[$productId] = $this->turnToProduct->turnToSafeEncoding((string)$sku);
            }
            return $childSkuMap;
        }

        foreach ($subject->getAllowProducts() as $childProduct) {
            $childSkuMap[$childProduct->getId()] = $this->turnToProduct->turnToSafeEncoding($childProduct->getSku());
        }

        return $childSkuMap;
    }
}
