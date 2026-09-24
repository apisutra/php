<?php

declare(strict_types=1);

namespace ApiSutra\Collections;

use ArrayIterator;
use ApiSutra\VO\Errors\RequestError;
use IteratorAggregate;
use Override;
use Traversable;

final class ErrorCollection implements IteratorAggregate
{
    /**
     * @param array<int, RequestError> $items
     */
    public function __construct(
        private array $items,
    ) {
    }

    public function first(): ?RequestError
    {
        return $this->items[0] ?? null;
    }

    /**
     * @return array<int, RequestError>
     */
    public function all(): array
    {
        return $this->items;
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
