<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Diagnostics;

use ApiSutra\VO\Http\TransferProgress;
use ApiSutra\Diagnostics\ExecutionTrace;

/** @internal Состояние одной передачи: только последний снимок, без истории. */
final class TransferAttempt
{
    private bool $active = true;
    private ?TransferProgress $last = null;

    public function __construct(
        private readonly TransferObservation $observation,
        private readonly ExecutionTrace $trace,
        private readonly int $number,
    ) {
    }

    public function notify(float $downloadTotal, float $downloaded, float $uploadTotal, float $uploaded): void
    {
        if (!$this->active || !$this->observation->active()) {
            return;
        }
        $up = (int) $uploaded;
        $down = (int) $downloaded;
        $upTotal = $uploadTotal > 0 ? (int) $uploadTotal : null;
        $downTotal = $downloadTotal > 0 ? (int) $downloadTotal : null;
        if (
            $this->last !== null && $this->last->uploaded === $up && $this->last->downloaded === $down
            && $this->last->uploadTotal === $upTotal && $this->last->downloadTotal === $downTotal
        ) {
            return;
        }
        $this->last = new TransferProgress($this->trace, $this->number, $up, $upTotal, $down, $downTotal);
        $this->observation->emit($this->last);
    }

    public function close(): void
    {
        $this->active = false;
        $this->last = null;
    }
}
