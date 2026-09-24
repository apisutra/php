<?php

declare(strict_types=1);

namespace ApiSutra\Execution;

use ApiSutra\Localization\Message;
use ApiSutra\Collections\RequestCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Enums\Execution\ExecutionMode;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\VO\Pipeline\PipelineContext;

/**
 * Выполняет зависимости в строгом порядке (sequential).
 *
 * Нюансы:
 * - Зависимости часто требуют последовательности, поэтому Parallel запрещён.
 * - Для параллельного выполнения используйте Composite/Batch.
 * - В будущем, при подтверждённой безопасной семантике, можно реализовать Parallel.
 */
final class DependsOnExecutor
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestCollection $requests,
        private readonly ?PipelineContext $parent = null,
    ) {
    }

    public function execute(ExecutionMode $mode, FailStrategy $strategy): ResultCollection
    {
        if ($mode !== ExecutionMode::Sequential) {
            // Решение: сохраняем API, но запрещаем Parallel для безопасности зависимостей.
            throw new ConfigurationException(new Message('execution.dependsonexecutor_supports_only_sequential'));
        }
        $mode = ExecutionMode::Sequential;
        $batch = new BatchExecutor(
            client: $this->client,
            requests: $this->requests,
            mode: $mode,
            failStrategy: $strategy,
            parent: $this->parent,
            role: RequestRole::Dependency,
        );

        return ResultCollection::make($batch->execute());
    }
}
