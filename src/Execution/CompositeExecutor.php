<?php

declare(strict_types=1);

namespace ApiSutra\Execution;

use ApiSutra\Collections\RequestCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Enums\Execution\ExecutionMode;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\VO\Pipeline\PipelineContext;

final class CompositeExecutor
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestCollection $requests,
        private readonly ?PipelineContext $parent = null,
    ) {
    }

    public function execute(ExecutionMode $mode, FailStrategy $strategy): ResultCollection
    {
        $batch = new BatchExecutor(
            client: $this->client,
            requests: $this->requests,
            mode: $mode,
            failStrategy: $strategy,
            parent: $this->parent,
            role: RequestRole::Nested,
        );

        return ResultCollection::make($batch->execute());
    }
}
