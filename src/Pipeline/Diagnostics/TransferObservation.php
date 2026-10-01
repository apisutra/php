<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Diagnostics;

use ApiSutra\VO\Http\TransferProgress;
use ApiSutra\Diagnostics\ExecutionTrace;
use Closure;
use Throwable;

/** @internal Получатель одного исполнения; ошибка отключает только это наблюдение. */
final class TransferObservation
{
    /** @param Closure(TransferProgress): void|null $callback */
    public function __construct(private ?Closure $callback)
    {
    }

    public function active(): bool
    {
        return $this->callback !== null;
    }

    public function attempt(ExecutionTrace $trace, int $number): ?TransferAttempt
    {
        return $this->callback === null ? null : new TransferAttempt($this, $trace, $number);
    }

    public function emit(TransferProgress $snapshot): void
    {
        try {
            ($this->callback)?->__invoke($snapshot);
        } catch (Throwable) {
            $this->close();
        }
    }

    public function close(): void
    {
        $this->callback = null;
    }
}
