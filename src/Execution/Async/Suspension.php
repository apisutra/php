<?php

declare(strict_types=1);

namespace ApiSutra\Execution\Async;

use Closure;
use Throwable;

/** @internal Не экспортирует типы конкретного планировщика. */
final readonly class Suspension
{
    /** @param Closure(): mixed $suspend
     * @param Closure(mixed): void $resume
     * @param Closure(Throwable): void $throw
     */
    public function __construct(private Closure $suspend, private Closure $resume, private Closure $throw)
    {
    }

    public function suspend(): mixed
    {
        return ($this->suspend)();
    }

    public function resume(mixed $value = null): void
    {
        ($this->resume)($value);
    }

    public function throw(Throwable $error): void
    {
        ($this->throw)($error);
    }
}
