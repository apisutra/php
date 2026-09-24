<?php

declare(strict_types=1);

namespace ApiSutra\Execution\Async;

use Closure;
use Revolt\EventLoop;

/** @internal Единственный адаптер зависимости от Revolt. */
final readonly class RevoltScheduler implements SchedulerInterface
{
    public function queue(Closure $callback): void
    {
        EventLoop::queue($callback);
    }

    public function delay(int $milliseconds, Closure $callback): Closure
    {
        $id = EventLoop::delay($milliseconds / 1000, $callback);
        return static fn () => EventLoop::cancel($id);
    }

    public function suspension(): Suspension
    {
        $suspension = EventLoop::getSuspension();
        return new Suspension($suspension->suspend(...), $suspension->resume(...), $suspension->throw(...));
    }
}
