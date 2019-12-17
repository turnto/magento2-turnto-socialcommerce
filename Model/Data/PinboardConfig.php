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

            if ($pinboardType === 'vcPinboard') {
                $config['pageId'] = 'vc-pinboard-page';
                $config['vcPinboard']= $this->pinboardBlock->getProductSkus() ? ['skus' => $this->pinboardBlock->getProductSkus()] : new \stdClass();
            } else {
                $config['pageId'] = 'comments-pinboard-page';
                $config['commentsPinboard'] = $this->pinboardBlock->getProductSkus() ? ['skus' => $this->pinboardBlock->getProductSkus()] : new \stdClass();
            }

            return $config;
        } catch (LocalizedException $localizedException) {
            return [];
        }
    }
}
