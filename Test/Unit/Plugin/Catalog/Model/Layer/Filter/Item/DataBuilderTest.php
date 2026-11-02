<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Plugin\Catalog\Model\Layer\Filter\Item;

use Magento\Catalog\Model\Layer\Filter\Item\DataBuilder as MagentoDataBuilder;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Layer\RatingFilterOption;
use TurnTo\SocialCommerce\Plugin\Catalog\Model\Layer\Filter\Item\DataBuilder;
use TurnTo\SocialCommerce\Setup\InstallHelper;

class DataBuilderTest extends TestCase
{
    public function testReplacesRatingOptionLabelsWithPlainTextPercentages(): void
    {
        $this->assertSame(
            ['80% & Up', '17', 4],
            $this->createPlugin(true)->beforeAddItemData(
                $this->createStub(MagentoDataBuilder::class),
                InstallHelper::FOUR_STAR_LABEL,
                '17',
                4
            )
        );
    }

    public function testLeavesOtherLabelsUntouched(): void
    {
        $this->assertSame(
            ['Red', '5', 2],
            $this->createPlugin(true)->beforeAddItemData($this->createStub(MagentoDataBuilder::class), 'Red', '5', 2)
        );
    }

    public function testLeavesRatingLabelsUntouchedWhenReviewsAreDisabled(): void
    {
        $this->assertSame(
            [InstallHelper::FOUR_STAR_LABEL, '17', 4],
            $this->createPlugin(false)->beforeAddItemData(
                $this->createStub(MagentoDataBuilder::class),
                InstallHelper::FOUR_STAR_LABEL,
                '17',
                4
            )
        );
    }

    protected function createPlugin(bool $enabled): DataBuilder
    {
        $config = $this->createStub(Config::class);
        $config->method('getIsEnabled')->willReturn($enabled);
        $config->method('getConfigBool')->willReturn($enabled);

        return new DataBuilder($config, new RatingFilterOption($config));
    }
}
