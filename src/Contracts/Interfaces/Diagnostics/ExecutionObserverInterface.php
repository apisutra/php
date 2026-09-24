<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Diagnostics;

use ApiSutra\Diagnostics\ExecutionSnapshot;
use ApiSutra\Diagnostics\ExecutionTrace;

/** Приём без I/O, приостановок Fiber и запуска event loop; доставка выполняется отдельно. */
interface ExecutionObserverInterface
{
    public function started(ExecutionTrace $trace): void;

    public function completed(ExecutionSnapshot $snapshot): void;

    /** Фактическое освобождение исполнения, в том числе после раннего терминала отмены. */
    public function released(ExecutionTrace $trace): void;

    public function includeAttempts(): bool;
}
