<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2018 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Model\Data;

use Magento\Framework\Exception\LocalizedException;
use TurnTo\SocialCommerce\Api\TurnToConfigDataSourceInterface;
use TurnTo\SocialCommerce\Block\Widget\Pinboard as PinboardBlock;
use TurnTo\SocialCommerce\Helper\Config as TurnToConfigHelper;

class PinboardConfig implements TurnToConfigDataSourceInterface
{
    /**
     * @var TurnToConfigHelper
     */
    protected $configHelper;

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
     * @param TurnToConfigHelper $configHelper
     * @param PinboardBlock      $pinboardBlock
     */
    public function __construct(
        TurnToConfigHelper $configHelper,
        PinboardBlock $pinboardBlock
    )
    {
        $this->configHelper = $configHelper;
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
