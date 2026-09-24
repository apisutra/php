<?php

declare(strict_types=1);

namespace ApiSutra\Execution\Pool;

use ApiSutra\Enums\Execution\PoolTerminationReason;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\PoolSummary;

/** @internal Состояние одного consume, без истории обработанных элементов. */
final class PoolConsumptionState
{
    public int $started = 0;
    public private(set) ?ExecutionResult $firstFailure = null;
    private ?int $firstFailedIndex = null;
    private int $successful = 0;
    private int $failed = 0;
    private int $partial = 0;

    public function __construct(private readonly bool $retainFailure)
    {
    }

    public function record(ExecutionResult $result, int $index): void
    {
        match ($result->status) {
            ResultStatus::SUCCESS => $this->successful++,
            ResultStatus::FAILED => $this->failed++,
            ResultStatus::PARTIAL => $this->partial++,
        };
        if ($result->isFailed() && ($this->firstFailedIndex === null || $index < $this->firstFailedIndex)) {
            $this->firstFailedIndex = $index;
            if ($this->retainFailure) {
                $this->firstFailure = $result;
            }
        }
    }

    public function summarize(PoolTerminationReason $reason): PoolSummary
    {
        return new PoolSummary(
            started: $this->started,
            successful: $this->successful,
            failed: $this->failed,
            partial: $this->partial,
            firstFailedIndex: $this->firstFailedIndex,
            terminationReason: $reason,
        );
    }
}
