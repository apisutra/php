<?php

declare(strict_types=1);

namespace ApiSutra\Execution\Async;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Closure;

/** @internal Единая граница ожидания и продвижения очереди Guzzle без Promise::wait(). */
final class GuzzlePromiseBridge
{
    private bool $queued = false;

    public function __construct(public readonly SchedulerInterface $scheduler = new RevoltScheduler())
    {
    }

    /**
     * @param null|Closure(): void $abandon
     * @return AwaitablePromise<mixed>
     */
    public function wrap(PromiseInterface $promise, ?AsyncTask $task = null, ?Closure $abandon = null): AwaitablePromise
    {
        if ($promise instanceof AwaitablePromise && $task === null && $abandon === null) {
            return $promise;
        }
        return new AwaitablePromise($promise, $this, $task, $abandon);
    }

    public function pump(): void
    {
        if ($this->queued || Utils::queue()->isEmpty()) {
            return;
        }
        $this->queued = true;
        $this->scheduler->queue(function (): void {
            $this->queued = false;
            Utils::queue()->run();
        });
    }

    public function await(PromiseInterface $promise, bool $cancelSource = false): mixed
    {
        $task = AsyncTask::current();
        $task?->check();
        $suspension = $this->scheduler->suspension();
        $pending = new PendingAwait($suspension);
        $unsubscribe = $task?->onCancel(static function (bool $resume) use ($pending, $promise, $cancelSource): void {
            $pending->cancel($resume);
            if ($cancelSource) {
                if (!$resume && $promise instanceof AwaitablePromise) {
                    $promise->abandon();
                } else {
                    $promise->cancel();
                }
            }
        });
        $promise->then($pending->resolve(...), $pending->reject(...));
        $this->pump();
        try {
            return $suspension->suspend();
        } finally {
            $pending->detach();
            $unsubscribe?->__invoke();
        }
    }
}
