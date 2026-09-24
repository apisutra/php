<?php

declare(strict_types=1);

namespace ApiSutra\Execution\Async;

use ApiSutra\Exceptions\ControlFlow\ExecutionCancelledException;
use GuzzleHttp\Promise\Create;

/** @internal Подписка может пережить отмену; после detach она не удерживает стек задачи. */
final class PendingAwait
{
    public function __construct(private ?Suspension $suspension)
    {
    }

    public function resolve(mixed $value): void
    {
        $suspension = $this->suspension;
        $this->suspension = null;
        $suspension?->resume($value);
    }

    public function reject(mixed $reason): void
    {
        $suspension = $this->suspension;
        $this->suspension = null;
        $suspension?->throw(Create::exceptionFor($reason));
    }

    public function cancel(bool $resume): void
    {
        if ($resume) {
            $this->reject(new ExecutionCancelledException());
        } else {
            $this->suspension = null;
        }
    }

    public function detach(): void
    {
        $this->suspension = null;
    }
}
