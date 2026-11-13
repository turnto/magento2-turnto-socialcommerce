<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * Minimal iterable stand-in for a page of a sales order collection used by the order feed writers.
 */
class FakeOrderCollection implements IteratorAggregate
{
    /**
     * @var array
     */
    protected $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function getSize(): int
    {
        return count($this->items);
    }

    public function getFirstItem()
    {
        return $this->items[0] ?? null;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
