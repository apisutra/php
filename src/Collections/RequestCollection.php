<?php

declare(strict_types=1);

namespace ApiSutra\Collections;

use ArrayIterator;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use IteratorAggregate;
use Override;
use Traversable;

final class RequestCollection implements IteratorAggregate
{
    /**
     * @param array<int, RequestInterface|string|callable> $items
     */
    public function __construct(
        private array $items,
    ) {
    }

    /**
     * @param array<int, RequestInterface|string|callable> $items
     */
    public static function make(array $items): self
    {
        return new self($items);
    }

    public function get(string $class): ?RequestInterface
    {
        foreach ($this->items as $item) {
            if ($item instanceof RequestInterface && $item::class === $class) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array<int, RequestInterface|string|callable>
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
