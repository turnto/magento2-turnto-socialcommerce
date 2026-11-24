<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Plugin\Catalog\Model\Layer\Filter\Item;

use Magento\Catalog\Model\Layer\Filter\Item\DataBuilder as MagentoDataBuilder;
use Magento\Framework\App\ObjectManager;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Layer\RatingFilterOption;

/**
 * Replaces TurnTo rating option labels with a plain text percentage label, e.g. "80% & Up"
 *
 * The layered navigation star markup is rendered by TurnTo_SocialCommerce::layer/filter/rating.phtml;
 * this text is used wherever the label is printed as-is, such as the "Now Shopping by" state block.
 */
class DataBuilder
{
    /**
     * Used to append & Up to rendered star rating label
     */
    const RATING_APPEND_AND_UP = RatingFilterOption::AND_UP;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var RatingFilterOption
     */
    protected $ratingFilterOption;

    /**
     * DataBuilder constructor.
     * @param Config $config
     * @param RatingFilterOption|null $ratingFilterOption
     */
    public function __construct(Config $config, ?RatingFilterOption $ratingFilterOption = null)
    {
        $this->config = $config;
        $this->ratingFilterOption = $ratingFilterOption
            ?? ObjectManager::getInstance()->get(RatingFilterOption::class);
    }

    /**
     * Converts the label to a rating summary label if it corresponds to a TurnTo Rating Filter Value
     *
     * @param $label
     * @return string
     */
    protected function getRatingLabel($label)
    {
        $percent = $this->ratingFilterOption->getPercent($label);

        return $percent === null ? $label : $this->ratingFilterOption->getTextLabel($percent);
    }

    /**
     * Replace TurnTo rating option labels before the filter item is built
     *
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
        if ($this->ratingFilterOption->isEnabled()) {
            $label = $this->getRatingLabel($label);
        }
        return [$label, $value, $count];
    }
}
