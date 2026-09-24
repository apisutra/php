<?php

declare(strict_types=1);

namespace ApiSutra\Result;

use ApiSutra\Enums\Execution\PoolTerminationReason;
use ApiSutra\VO\Metadata\ResultSummary;

/** Итог consume без удержания запросов, ответов и исключений. */
final readonly class PoolSummary extends ResultSummary
{
    public function __construct(
        public int $started,
        int $successful,
        int $failed,
        int $partial,
        public ?int $firstFailedIndex,
        public PoolTerminationReason $terminationReason,
    ) {
        $counts = ResultSummary::fromCounts($successful, $failed, $partial);
        parent::__construct($counts->total, $successful, $failed, $partial, $counts->status);
    }
}
