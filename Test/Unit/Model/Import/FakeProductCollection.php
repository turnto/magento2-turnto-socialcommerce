<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Import;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * Lightweight iterable, chainable stand-in for the catalog product collection.
 *
 * Avoids mocking the heavy concrete collection class, which is both unnecessary for the
 * behavior under test and brittle across PHP versions.
 */
class FakeProductCollection implements IteratorAggregate
{
    /**
     * @var array
     */
    private $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Every builder-style call returns the collection for chaining.
     *
     * @param string $name
     * @param array $arguments
     * @return $this
     */
    public function __call($name, $arguments)
    {
        return $this;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
