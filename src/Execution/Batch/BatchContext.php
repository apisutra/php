<?php

declare(strict_types=1);

namespace ApiSutra\Execution\Batch;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Execution\ClientExecutorInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Enums\Execution\ExecutionMode;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Execution\ExecutionDispatch;
use GuzzleHttp\Promise\PromiseInterface;
use ApiSutra\VO\Pipeline\PipelineContext;

/**
 * Контекст выполнения batch.
 */
readonly class BatchContext
{
    public function __construct(
        public ?ClientInterface $client,
        public ExecutionMode $mode,
        public FailStrategy $failStrategy,
        public int $concurrency,
        public ?PipelineContext $parent,
        public RequestRole $role,
    ) {
    }

    public function execute(RequestInterface $request): ExecutionResult
    {
        return ExecutionDispatch::execute($this->executor(), $request, $this->role, $this->parent);
    }

    /** @return PromiseInterface */
    public function executeAsync(RequestInterface $request): PromiseInterface
    {
        return ExecutionDispatch::executeAsync($this->executor(), $request, $this->role, $this->parent);
    }

    private function executor(): ClientExecutorInterface
    {
        return $this->parent->executor ?? $this->client?->execution()
            ?? throw new ConfigurationException(new Message('execution.no_client_specified_for_batch_execution'));
    }
}
