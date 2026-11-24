<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Layer;

use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Plugin\Review\Block\Product\ReviewRenderer;
use TurnTo\SocialCommerce\Setup\InstallHelper;

/**
 * Maps TurnTo average rating filter options to star rating percentages
 */
class RatingFilterOption
{
    public const AND_UP = '& Up';

    /**
     * @var Config
     */
    protected $config;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Whether TurnTo rating filter options should be rendered as star ratings
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->getIsEnabled() && $this->config->getConfigBool(Config::REVIEWS_ENABLE);
    }

    /**
     * Whether the attribute code is the TurnTo average rating filter attribute
     *
     * @param string|null $attributeCode
     * @return bool
     */
    public function isRatingAttribute(?string $attributeCode): bool
    {
        return $attributeCode === InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE;
    }

    /**
     * Rating percentage (20-100) for a rating filter option label, or null when the label is not a rating option
     *
     * @param mixed $optionLabel
     * @return int|null
     */
    public function getPercent($optionLabel): ?int
    {
        $index = array_search((string) $optionLabel, InstallHelper::RATING_FILTER_VALUES, true);
        if ($index === false) {
            return null;
        }

        return ($index + 1) * ReviewRenderer::RATING_TO_PERCENTILE_MULTIPLIER;
    }

    /**
     * Text shown after the stars, e.g. "& Up"; empty for the top rating
     *
     * @param int $percent
     * @return string
     */
    public function getSuffix(int $percent): string
    {
        return $percent < 100 ? trim((string) __('& Up')) : '';
    }

    /**
     * Plain text label for a rating percentage, e.g. "80% & Up"
     *
     * @param int $percent
     * @return string
     */
    public function getTextLabel(int $percent): string
    {
        return trim($percent . '% ' . $this->getSuffix($percent));
    }
}
