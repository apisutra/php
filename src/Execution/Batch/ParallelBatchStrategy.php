<?php

declare(strict_types=1);

namespace ApiSutra\Execution\Batch;

use ApiSutra\Execution\ConcurrentExecution;
use ApiSutra\Enums\Execution\FailStrategy;

/**
 * Параллельная стратегия выполнения batch.
 */
final class ParallelBatchStrategy implements BatchStrategyInterface
{
    /**
     * @inheritDoc
     */
    public function execute(BatchContext $context, array $requests): array
    {
        return ConcurrentExecution::collect(
            array_values($requests),
            $context->concurrency,
            $context->executeAsync(...),
            $context->failStrategy === FailStrategy::FailAll,
        );
    }
}
