<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Pagination;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

final class TestItemCollection implements IteratorAggregate, Countable
{
    /**
     * @param array<int, mixed> $items
     */
    public function __construct(
        private array $items,
    ) {}

    /**
     * @return array<int, mixed>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
