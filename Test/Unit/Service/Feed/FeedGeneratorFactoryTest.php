<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Service\Feed;

use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Model\Config\Source\FeedFormat;
use TurnTo\SocialCommerce\Service\Feed\CommerceFeedGenerator;
use TurnTo\SocialCommerce\Service\Feed\FeedGeneratorFactory;
use TurnTo\SocialCommerce\Service\Feed\GoogleFeedGenerator;

class FeedGeneratorFactoryTest extends TestCase
{
    /**
     * @var FeedGeneratorFactory
     */
    protected $factory;

    /**
     * @var GoogleFeedGenerator
     */
    protected $googleGenerator;

    /**
     * @var CommerceFeedGenerator
     */
    protected $commerceGenerator;

    protected function setUp(): void
    {
        $this->googleGenerator = $this->createMock(GoogleFeedGenerator::class);
        $this->commerceGenerator = $this->createMock(CommerceFeedGenerator::class);

        $generators = [
            FeedFormat::GOOGLE_PRODUCT => $this->googleGenerator,
            FeedFormat::COMMERCE => $this->commerceGenerator
        ];

        $this->factory = new FeedGeneratorFactory($generators);
    }

    public function testCreateGoogleFeedGenerator()
    {
        $result = $this->factory->create(FeedFormat::GOOGLE_PRODUCT);
        $this->assertSame($this->googleGenerator, $result);
    }

    public function testCreateCommerceFeedGenerator()
    {
        $result = $this->factory->create(FeedFormat::COMMERCE);
        $this->assertSame($this->commerceGenerator, $result);
    }

    public function testCreateThrowsExceptionForInvalidFormat()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid feed format: invalid_format');

        $this->factory->create('invalid_format');
    }
}
