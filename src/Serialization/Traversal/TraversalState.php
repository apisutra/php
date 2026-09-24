<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Traversal;

/** @internal Активная ветка O(depth); единицу глубины выбирает вызывающий исполнитель. */
final class TraversalState
{
    public const int MAX_DEPTH = 512;

    /** @var array<int, true> */
    public array $ancestors = [];

    public function __construct(private int $depth = 0)
    {
    }

    public function enterNode(?int $id = null): ?TraversalViolation
    {
        if ($this->depth >= self::MAX_DEPTH) {
            return TraversalViolation::Depth;
        }
        if ($id !== null && isset($this->ancestors[$id])) {
            return TraversalViolation::Cycle;
        }
        $this->depth++;
        if ($id !== null) {
            $this->ancestors[$id] = true;
        }
        return null;
    }

    public function leaveNode(?int $id = null): void
    {
        $this->depth--;
        if ($id !== null) {
            unset($this->ancestors[$id]);
        }
    }
}
