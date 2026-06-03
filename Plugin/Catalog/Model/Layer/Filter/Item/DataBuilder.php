<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Plugin\Catalog\Model\Layer\Filter\Item;

use Magento\Catalog\Model\Layer\Filter\Item\DataBuilder as MagentoDataBuilder;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Setup\InstallHelper;
use TurnTo\SocialCommerce\Plugin\Review\Block\Product\ReviewRenderer;

class DataBuilder
{
    /**
     * Used to append & Up to rendered star rating label
     */
    const RATING_APPEND_AND_UP = '& Up';

    /**
     * @var Config
     */
    protected $config;

    /**
     * DataBuilder constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Converts the label to a rating summary label if it corresponds to a TurnTo Rating Filter Value
     *
     * @param $label
     * @return string
     */
    protected function getRatingLabel($label)
    {
        $idx = array_search($label, InstallHelper::RATING_FILTER_VALUES, true);
        if ($idx === false) {
            return $label;
        }
        $rating = ($idx + 1) * ReviewRenderer::RATING_TO_PERCENTILE_MULTIPLIER;
        $andUp = $rating < 100 ? trim((string) __(self::RATING_APPEND_AND_UP)) : '';
        if ($andUp !== '') {
            return (string) $rating . '% ' . $andUp;
        }

        return (string) $rating . '%';
    }

    /**
     * @param MagentoDataBuilder $subject
     * @param string $label
     * @param string $value
     * @param int $count
     * @return array
     */
    public function beforeAddItemData(
        MagentoDataBuilder $subject,
        $label,
        $value,
        $count
    ) {
        if ($this->config->getIsEnabled() && $this->config->getConfigBool(Config::REVIEWS_ENABLE)) {
            $label = $this->getRatingLabel($label);
        }
        return [$label, $value, $count];
    }
}
