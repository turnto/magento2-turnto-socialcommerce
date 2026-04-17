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
     * @var array
     */
    protected $generators;

    /**
     * @param array $generators
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
            $factory = $this->generators[$format];
            $generator = $factory->create();
            if (!$generator instanceof FeedGeneratorInterface) {
                throw new InvalidArgumentException(
                    get_class($generator) . ' doesn\'t implement \TurnTo\SocialCommerce\Api\FeedGeneratorInterface'
                );
            }
            return $generator;
        }

        throw new InvalidArgumentException('Invalid feed format: ' . $format);
    }
}
