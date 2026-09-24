<?php

declare(strict_types=1);

namespace ApiSutra\Execution\Async;

use ApiSutra\Contracts\Interfaces\Execution\ResultPromiseInterface;
use GuzzleHttp\Promise\PromiseInterface;
use Throwable;
use Closure;

/**
 * @internal Guzzle управляет цепочками, а SDK — повторно входимым ожиданием.
 * @template T
 * @implements ResultPromiseInterface<T>
 */
final class AwaitablePromise implements ResultPromiseInterface
{
    /**
     * @param null|Closure(): void $abandon
     */
    public function __construct(
        private readonly PromiseInterface $promise,
        private readonly GuzzlePromiseBridge $bridge,
        private readonly ?AsyncTask $task = null,
        private readonly ?Closure $abandon = null,
    ) {
        $this->task?->retain();
    }

    public function __destruct()
    {
        $this->task?->release();
    }

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): ResultPromiseInterface
    {
        $next = new self($this->promise->then($onFulfilled, $onRejected), $this->bridge, $this->task, $this->abandon(...));
        $this->bridge->pump();
        return $next;
    }

    public function otherwise(callable $onRejected): ResultPromiseInterface
    {
        return $this->then(null, $onRejected);
    }

    public function getState(): string
    {
        return $this->promise->getState();
    }

    public function resolve($value): void
    {
        $this->promise->resolve($value);
        $this->bridge->pump();
    }

    public function reject($reason): void
    {
        $this->promise->reject($reason);
        $this->bridge->pump();
    }

    public function cancel(): void
    {
        $this->promise->cancel();
        $this->bridge->pump();
    }

    /** @internal Освобождение без очереди rejection у уже отброшенного выполнения. */
    public function abandon(): void
    {
        if ($this->abandon !== null) {
            ($this->abandon)();
        } else {
            $this->promise->cancel();
        }
    }

    public function wait(bool $unwrap = true): mixed
    {
        try {
            $value = $this->bridge->await($this->promise);
            return $unwrap ? $value : null;
        } catch (Throwable $error) {
            if ($unwrap) {
                throw $error;
            }
            return null;
        }
    }
}
