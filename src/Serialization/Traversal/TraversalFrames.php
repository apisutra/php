<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Traversal;

use Fiber;
use WeakMap;

/** @internal Повторный вход продолжает ветку своей Fiber, не соседнего исполнения. */
final class TraversalFrames
{
    private readonly TraversalState $main;
    /** @var WeakMap<Fiber, TraversalState>|null */
    private ?WeakMap $fibers = null;

    public function __construct()
    {
        $this->main = new TraversalState();
    }

    public function current(): TraversalState
    {
        $fiber = Fiber::getCurrent();
        if ($fiber === null) {
            return $this->main;
        }
        $this->fibers ??= new WeakMap();
        return $this->fibers[$fiber] ??= new TraversalState();
    }
}
