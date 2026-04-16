<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Service\Feed;

use InvalidArgumentException;
use TurnTo\SocialCommerce\Api\FeedGeneratorInterface;

class FeedGeneratorFactory
{
    /**
     * @var FeedGeneratorInterface[]
     */
    protected $generators;

    /**
     * @param FeedGeneratorInterface[] $generators
     */
    public function __construct(array $generators = [])
    {
        $this->generators = $generators;
    }

    /**
     * @param string $format
     * @return FeedGeneratorInterface
     * @throws InvalidArgumentException
     */
    public function create(string $format)
    {
        if (isset($this->generators[$format])) {
            return $this->generators[$format];
        }

        throw new InvalidArgumentException('Invalid feed format: ' . $format);
    }
}
