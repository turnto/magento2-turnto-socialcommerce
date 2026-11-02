<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Layer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Layer\RatingFilterOption;
use TurnTo\SocialCommerce\Setup\InstallHelper;

class RatingFilterOptionTest extends TestCase
{
    public static function ratingLabelProvider(): array
    {
        return [
            [InstallHelper::ONE_STAR_LABEL, 20, '20% & Up'],
            [InstallHelper::TWO_STAR_LABEL, 40, '40% & Up'],
            [InstallHelper::THREE_STAR_LABEL, 60, '60% & Up'],
            [InstallHelper::FOUR_STAR_LABEL, 80, '80% & Up'],
            [InstallHelper::FIVE_STAR_LABEL, 100, '100%'],
        ];
    }

    /**
     * @dataProvider ratingLabelProvider
     */
    #[DataProvider('ratingLabelProvider')]
    public function testMapsRatingOptionLabelsToPercentagesAndTextLabels(
        string $optionLabel,
        int $percent,
        string $textLabel
    ): void {
        $ratingFilterOption = $this->createRatingFilterOption();

        $this->assertSame($percent, $ratingFilterOption->getPercent($optionLabel));
        $this->assertSame($textLabel, $ratingFilterOption->getTextLabel($percent));
    }

    public function testReturnsNullForLabelsThatAreNotRatingOptions(): void
    {
        $ratingFilterOption = $this->createRatingFilterOption();

        $this->assertNull($ratingFilterOption->getPercent('Red'));
        $this->assertNull($ratingFilterOption->getPercent(null));
    }

    public function testSuffixIsOmittedForTheTopRating(): void
    {
        $ratingFilterOption = $this->createRatingFilterOption();

        $this->assertSame('& Up', $ratingFilterOption->getSuffix(80));
        $this->assertSame('', $ratingFilterOption->getSuffix(100));
    }

    public function testRecognisesTheAverageRatingAttribute(): void
    {
        $ratingFilterOption = $this->createRatingFilterOption();

        $this->assertTrue($ratingFilterOption->isRatingAttribute(InstallHelper::AVERAGE_RATING_ATTRIBUTE_CODE));
        $this->assertFalse($ratingFilterOption->isRatingAttribute('color'));
        $this->assertFalse($ratingFilterOption->isRatingAttribute(null));
    }

    public function testIsEnabledRequiresTheExtensionAndReviewsToBeEnabled(): void
    {
        $this->assertTrue($this->createRatingFilterOption(true, true)->isEnabled());
        $this->assertFalse($this->createRatingFilterOption(true, false)->isEnabled());
        $this->assertFalse($this->createRatingFilterOption(false, true)->isEnabled());
    }

    protected function createRatingFilterOption(
        bool $extensionEnabled = true,
        bool $reviewsEnabled = true
    ): RatingFilterOption {
        $config = $this->createStub(Config::class);
        $config->method('getIsEnabled')->willReturn($extensionEnabled);
        $config->method('getConfigBool')->willReturnCallback(function ($path) use ($reviewsEnabled) {
            return $path === Config::REVIEWS_ENABLE && $reviewsEnabled;
        });

        return new RatingFilterOption($config);
    }
}
