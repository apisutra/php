<?php

declare(strict_types=1);

namespace ApiSutra\Execution;

use ApiSutra\VO\Pipeline\PipelineContext;
use Closure;
use Fiber;
use WeakMap;

/** @internal Стек одного request; разные Fiber не меняют активный контекст друг друга. */
final class ExecutionContextStack
{
    /** @var list<PipelineContext> */
    private array $main = [];
    /** @var WeakMap<Fiber, list<PipelineContext>> */
    private WeakMap $fibers;

    public function __construct()
    {
        $this->fibers = new WeakMap();
    }

    public function current(): ?PipelineContext
    {
        $fiber = Fiber::getCurrent();
        $stack = $fiber === null ? $this->main : ($this->fibers[$fiber] ?? []);
        return $stack === [] ? null : $stack[array_key_last($stack)];
    }

    /** @return Closure(): void */
    public function enter(PipelineContext $context): Closure
    {
        $fiber = Fiber::getCurrent();
        if ($fiber === null) {
            $this->main[] = $context;
        } else {
            $stack = $this->fibers[$fiber] ?? [];
            $stack[] = $context;
            $this->fibers[$fiber] = $stack;
        }
        return function () use ($fiber): void {
            if ($fiber === null) {
                array_pop($this->main);
            } else {
                $stack = $this->fibers[$fiber] ?? [];
                array_pop($stack);
                if ($stack === []) {
                    unset($this->fibers[$fiber]);
                } else {
                    $this->fibers[$fiber] = $stack;
                }
            }
        };
    }
}
