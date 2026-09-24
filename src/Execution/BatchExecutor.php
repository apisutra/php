<?php

declare(strict_types=1);

namespace ApiSutra\Execution;

use ApiSutra\Contracts\Interfaces\Execution\ResultPromiseInterface;
use ApiSutra\Execution\Async\AsyncRuntime;
use ApiSutra\Exceptions\ControlFlow\ExecutorContractViolation;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Localization\Message;
use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Collections\RequestCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Config\BatchConfig;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Execution\ExecutionMode;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Execution\Batch\BatchContext;
use ApiSutra\Execution\Batch\BatchStrategyInterface;
use ApiSutra\Execution\Batch\ParallelBatchStrategy;
use ApiSutra\Execution\Batch\RequestResolverInterface;
use ApiSutra\Execution\Batch\Resolvers\CallableRequestResolver;
use ApiSutra\Execution\Batch\Resolvers\ClassStringRequestResolver;
use ApiSutra\Execution\Batch\Resolvers\RequestInstanceResolver;
use ApiSutra\Execution\Batch\SequentialBatchStrategy;
use ApiSutra\Request\RequestExecution;
use ApiSutra\Result\BatchResult;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Errors\RequestError;
use ApiSutra\VO\Metadata\BatchMeta;
use ApiSutra\VO\Metadata\ResultSummary;
use ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Promise\PromiseInterface;

final class BatchExecutor
{
    private ExecutionMode $mode;
    private FailStrategy $failStrategy;
    private int $concurrency;
    /**
     * @var array<int, RequestResolverInterface> Цепочка резолверов входных запросов.
     */
    private readonly array $requestResolvers;
    private readonly ExecutionErrorFactory $errorFactory;
    private readonly BatchStrategyInterface $sequentialStrategy;
    private readonly BatchStrategyInterface $parallelStrategy;

    public function __construct(
        private readonly ?ClientInterface $client,
        private readonly RequestCollection|array $requests,
        ExecutionMode $mode = ExecutionMode::Sequential,
        FailStrategy $failStrategy = FailStrategy::FailAll,
        int $concurrency = 5,
        private readonly ?PipelineContext $parent = null,
        private readonly RequestRole $role = RequestRole::Nested,
    ) {
        $this->mode = $mode;
        $this->failStrategy = $failStrategy;
        $this->concurrency = $concurrency;
        $this->requestResolvers = [
            new RequestInstanceResolver(),
            new CallableRequestResolver(),
            new ClassStringRequestResolver(),
        ];
        $this->errorFactory = new ExecutionErrorFactory($this->client?->getConfig()->localization ?? new LocalizationConfig());
        $this->sequentialStrategy = new SequentialBatchStrategy();
        $this->parallelStrategy = new ParallelBatchStrategy();
    }

    public static function fromConfig(
        ClientInterface $client,
        RequestCollection|array $requests,
        BatchConfig $config,
        ?PipelineContext $parent = null,
        RequestRole $role = RequestRole::Nested,
    ): self {
        return new self(
            client: $client,
            requests: $requests,
            mode: $config->mode,
            failStrategy: $config->failStrategy,
            concurrency: $config->concurrency,
            parent: $parent,
            role: $role,
        );
    }

    public function parallel(): static
    {
        return $this->withMode(ExecutionMode::Parallel);
    }

    public function sequential(): static
    {
        return $this->withMode(ExecutionMode::Sequential);
    }

    public function withMode(ExecutionMode $mode): static
    {
        return $this->cloneWith(static function (self $clone) use ($mode): void {
            $clone->mode = $mode;
        });
    }

    public function withFailStrategy(FailStrategy $strategy): static
    {
        return $this->cloneWith(static function (self $clone) use ($strategy): void {
            $clone->failStrategy = $strategy;
        });
    }

    public function withConcurrency(int $value): static
    {
        return $this->cloneWith(static function (self $clone) use ($value): void {
            $clone->concurrency = $value;
        });
    }

    /**
     * @return array<int, ExecutionResult>
     */
    public function execute(): array
    {
        $context = $this->buildContext();
        $requests = $this->resolveRequests($context)->all();
        $this->assertClient($context);
        $strategy = $this->resolveStrategy($context->mode);

        try {
            return $this->executeWithStrategy($strategy, $context, $requests);
        } catch (ExecutorContractViolation $exception) {
            throw $this->parent === null ? $exception->cause : $exception;
        }
    }

    public function send(): BatchResult
    {
        try {
            $results = $this->execute();
        } catch (ExecutorContractViolation $exception) {
            throw $exception->cause;
        }
        $collection = ResultCollection::make($results);
        $result = $this->buildBatchResult($collection);
        if ($this->client?->getConfig()->throwOnErrors) {
            $result->throw();
        }
        return $result;
    }

    /** @return ResultPromiseInterface<BatchResult> */
    public function sendAsync(): ResultPromiseInterface
    {
        return (new AsyncRuntime())->start(fn (): BatchResult => $this->send());
    }

    private function cloneWith(callable $mutate): static
    {
        $clone = clone $this;
        $mutate($clone);
        return $clone;
    }

    private function resolveRequests(BatchContext $context): RequestCollection
    {
        $collection = $this->normalizeRequests();
        $resolved = [];
        foreach ($collection->all() as $index => $item) {
            $resolvedRequest = $this->resolveRequestItem($item, $context, $index);
            $resolved[] = $this->prepareRequest($resolvedRequest, $context);
        }

        return RequestCollection::make($resolved);
    }

    private function prepareRequest(RequestInterface $request, BatchContext $context): RequestInterface
    {
        if ($request instanceof RequestExecutionInterface) {
            $inner = $request->getRequest();
            if ($inner instanceof AbstractRequest && $context->client !== null) {
                $inner->setClient($context->client);
            }

            return new RequestExecution(
                request: $inner,
                options: $request->getOptions()->withRole($context->role),
                paginationOptions: $request->getPaginationOptions(),
            );
        }

        if ($request instanceof AbstractRequest && $context->client !== null) {
            $request->setClient($context->client);
            $request = $request->withRole($context->role);
        }

        return $request;
    }

    private function normalizeRequests(): RequestCollection
    {
        return $this->requests instanceof RequestCollection
            ? $this->requests
            : RequestCollection::make($this->requests);
    }

    private function resolveRequestItem(mixed $item, BatchContext $context, int $index): RequestInterface
    {
        foreach ($this->requestResolvers as $resolver) {
            if (!$resolver->supports($item)) {
                continue;
            }

            $resolvedRequest = $resolver->resolve($item, $context);
            if (!$resolvedRequest instanceof RequestInterface) {
                throw $this->errorFactory->invalidItemException('batch', $index, $item);
            }

            return $resolvedRequest;
        }

        throw $this->errorFactory->unsupportedItemException('batch', $index, $item);
    }

    private function buildContext(): BatchContext
    {
        return new BatchContext(
            client: $this->client,
            mode: $this->mode,
            failStrategy: $this->failStrategy,
            concurrency: $this->concurrency,
            parent: $this->parent,
            role: $this->role,
        );
    }

    private function resolveStrategy(ExecutionMode $mode): BatchStrategyInterface
    {
        return $mode === ExecutionMode::Sequential
            ? $this->sequentialStrategy
            : $this->parallelStrategy;
    }

    private function assertClient(BatchContext $context): void
    {
        if ($context->client === null) {
            throw new ConfigurationException(new Message('execution.no_client_specified_for_batch_execution'));
        }
    }

    /**
     * @param array<int, RequestInterface> $requests
     * @return array<int, ExecutionResult>
     */
    private function executeWithStrategy(
        BatchStrategyInterface $strategy,
        BatchContext $context,
        array $requests,
    ): array {
        return $strategy->execute(
            $context,
            $requests,
        );
    }

    private function buildBatchResult(ResultCollection $collection): BatchResult
    {
        $summary = $collection->summarize();
        $errors = $this->collectErrors($collection);
        $meta = $this->buildBatchMeta($summary);

        return new BatchResult(
            exceptionFactory: $this->client?->getConfig()->resultExceptions?->exceptionFactory,
            localization: $this->client?->getConfig()->localization ?? new LocalizationConfig(),
            data: null,
            status: $summary->status,
            errors: new ErrorCollection($errors),
            meta: $meta,
            nested: $collection->all(),
            exception: $collection->firstFailure()?->exception,
        );
    }

    /**
     * @return array<int, RequestError>
     */
    private function collectErrors(ResultCollection $collection): array
    {
        $errors = [];
        foreach ($collection->failed()->all() as $result) {
            if ($result->errors->first() !== null) {
                $errors[] = $result->errors->first();
            }
        }

        return $errors;
    }

    private function buildBatchMeta(ResultSummary $summary): BatchMeta
    {
        return new BatchMeta(
            total: $summary->total,
            successful: $summary->successful,
            failed: $summary->failed,
            partial: $summary->partial,
        );
    }
}
