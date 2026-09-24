<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Tracing;

use ApiSutra\Contracts\Interfaces\Diagnostics\ExecutionObserverInterface;
use ApiSutra\Diagnostics\ExecutionSnapshot;
use ApiSutra\Diagnostics\ExecutionTrace;
use RuntimeException;

final class RecordingObserver implements ExecutionObserverInterface
{
    /** @var list<ExecutionSnapshot> */
    public array $snapshots = [];
    public int $active = 0;
    public bool $fail = false;

    public function started(ExecutionTrace $trace): void { $this->active++; }
    public function released(ExecutionTrace $trace): void { $this->active--; }
    public function includeAttempts(): bool { return true; }
    public function completed(ExecutionSnapshot $snapshot): void
    {
        $this->snapshots[] = $snapshot;
        if ($this->fail) { throw new RuntimeException('observer failed'); }
    }
}
