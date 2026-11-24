<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Plugin\Block\Product;

use Magento\Review\Block\Product\Review;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Plugin\Block\Product\ReviewPlugin;

class ReviewPluginTest extends TestCase
{
    /**
     * @dataProvider tabTitleProvider
     */
    #[DataProvider('tabTitleProvider')]
    public function testReplacesTheNativeTabTitleOnlyWhenTurnToReviewsAreEnabled(
        bool $extensionEnabled,
        bool $reviewsEnabled,
        bool $expectReplaced
    ): void {
        $config = $this->createStub(Config::class);
        $config->method('getIsEnabled')->willReturn($extensionEnabled);
        $config->method('getConfigBool')->willReturnCallback(function ($path) use ($reviewsEnabled) {
            return $path === Config::REVIEWS_ENABLE && $reviewsEnabled;
        });

        $titles = [];
        $review = $this->createStub(Review::class);
        $review->method('__call')->willReturnCallback(function ($method, $args) use (&$titles) {
            if ($method === 'setTitle') {
                $titles[] = (string) $args[0];
            }
            return null;
        });

        (new ReviewPlugin($config))->afterSetTabTitle($review, null);

        $this->assertSame($expectReplaced ? ['Reviews '] : [], $titles);
    }

    public static function tabTitleProvider(): array
    {
        return [
            'extension and reviews enabled' => [true, true, true],
            'reviews disabled' => [true, false, false],
            'extension disabled' => [false, true, false],
        ];
    }
}
