<?php

declare(strict_types=1);

namespace ApiSutra\Execution\Batch;

use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Result\ExecutionResult;

/**
 * Контракт стратегии batch выполнения.
 */
interface BatchStrategyInterface
{
    /**
     * @param array<int, RequestInterface> $requests
     * @return array<int, ExecutionResult>
     */
    public function execute(BatchContext $context, array $requests): array;
}
