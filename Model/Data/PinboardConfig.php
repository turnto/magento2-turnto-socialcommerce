<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Data;

use Magento\Framework\Exception\LocalizedException;
use TurnTo\SocialCommerce\Api\TurnToConfigDataSourceInterface;
use TurnTo\SocialCommerce\Block\Widget\Pinboard as PinboardBlock;

class PinboardConfig implements TurnToConfigDataSourceInterface
{
    /**
     * @var PinboardBlock
     */
    protected $pinboardBlock;

    /**
     * Used to fetch the proper page ID based on the pinboard type
     *
     * @var array
     */
    protected $pageIdTranslation = [
      'vcPinboard' => 'vc-pinboard-page',
      'commentsPinboard' => 'comments-pinboard-page',
      'commentsPinboardTeaser' => 'comments-pinboard-teaser-page'
    ];

    /**
     * @param PinboardBlock $pinboardBlock
     */
    public function __construct(
        PinboardBlock $pinboardBlock
    ) {
        $this->pinboardBlock = $pinboardBlock;
    }

    /**
     * {@inheritdoc}
     */
    public function getData()
    {
        try {
            $pinboardType = $this->pinboardBlock->getContentType();
            $config = [];

            // Set 'skus', 'tags', 'brands' config options if they exist in the pinboard widget
            $pinboardConfig = [];

            if ($skus = $this->pinboardBlock->getProductSkus()) {
                $pinboardConfig['skus'] = $skus;
            }
            if ($brands = $this->pinboardBlock->getProductBrands()) {
                $pinboardConfig['brands'] = $brands;
            }
            if ($tags = $this->pinboardBlock->getProductTags()) {
                $pinboardConfig['tags'] = $tags;
            }
            $config[$pinboardType] = $pinboardConfig;

            // set pageId in config
            $config['pageId'] = $this->pageIdTranslation[$pinboardType];

            return $config;
        } catch (LocalizedException $localizedException) {
            return [];
        }
    }
}
