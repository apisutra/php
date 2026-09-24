<?php

declare(strict_types=1);

namespace ApiSutra\Execution;

use ApiSutra\Contracts\Interfaces\Execution\ResultPromiseInterface;
use ApiSutra\Execution\Async\AsyncRuntime;
use ApiSutra\Execution\Async\AsyncTask;
use ApiSutra\Exceptions\ControlFlow\ExecutorContractViolation;
use ApiSutra\Result\ResultExceptionSelector;
use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Config\PoolConfig;
use ApiSutra\Contracts\Interfaces\Concurrency\ConcurrencyResolverInterface;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Enums\Execution\PoolTerminationReason;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Execution\PoolConsumptionException;
use ApiSutra\Execution\Pool\PoolConsumptionState;
use ApiSutra\Execution\Pool\PoolRequestSource;
use ApiSutra\Localization\Message;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\PoolResult;
use ApiSutra\Result\PoolSummary;
use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Throwable;
use ApiSutra\Exceptions\ControlFlow\AdmissionRefused;

final class PoolExecutor
{
    private ?Closure $onResponse = null;
    private ?Closure $onException = null;
    private int|Closure|ConcurrencyResolverInterface $concurrency;
    private readonly ?PoolConfig $config;
    private RequestRole $role = RequestRole::Nested;
    private ?bool $stopOnFailure = null;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly iterable $requests,
        int|callable|ConcurrencyResolverInterface|null $concurrency = null,
        ?PoolConfig $config = null,
    ) {
        $this->config = $config ?? $this->client->getConfig()->pool;
        $this->concurrency = $this->normalizeConcurrency($concurrency ?? $this->config->concurrency ?? 5);
    }

    public function withResponseHandler(callable $handler): self
    {
        return $this->cloneWith(static function (self $clone) use ($handler): void {
            $clone->onResponse = Closure::fromCallable($handler);
        });
    }

    public function withExceptionHandler(callable $handler): self
    {
        return $this->cloneWith(static function (self $clone) use ($handler): void {
            $clone->onException = Closure::fromCallable($handler);
        });
    }

    public function withRole(RequestRole $role): self
    {
        return $this->cloneWith(static function (self $clone) use ($role): void {
            $clone->role = $role;
        });
    }

    public function withConcurrency(int|callable|ConcurrencyResolverInterface $concurrency): self
    {
        return $this->cloneWith(static function (self $clone) use ($concurrency): void {
            $clone->concurrency = $clone->normalizeConcurrency($concurrency);
        });
    }

    public function withStopOnFailure(bool $enabled = true): self
    {
        return $this->cloneWith(static function (self $clone) use ($enabled): void {
            $clone->stopOnFailure = $enabled;
        });
    }

    public function send(): PoolResult
    {
        $requests = iterator_to_array($this->source()->iterate(), false);
        try {
            $results = $this->executePool($requests);
        } catch (ExecutorContractViolation $exception) {
            throw $exception->cause;
        }

        $result = $this->buildPoolResult($results);
        if ($this->client->getConfig()->throwOnErrors) {
            $result->throw();
        }
        return $result;
    }

    /** @return ResultPromiseInterface<PoolResult> */
    public function sendAsync(): ResultPromiseInterface
    {
        return (new AsyncRuntime())->start(fn (): PoolResult => $this->send());
    }

    public function consume(): PoolSummary
    {
        $state = new PoolConsumptionState($this->client->getConfig()->throwOnErrors);
        $source = $this->source();
        $concurrency = $this->consumptionConcurrency($source, $state);
        $factoryFailure = null;
        $outcome = $this->executeConsumption(
            $source,
            $state,
            $concurrency,
            $this->consumptionHandler($factoryFailure),
        );
        return $this->finishConsumption($state, $outcome, $factoryFailure);
    }

    /** @return ResultPromiseInterface<PoolSummary> */
    public function consumeAsync(): ResultPromiseInterface
    {
        return (new AsyncRuntime())->start(fn (): PoolSummary => $this->consume());
    }

    private function source(): PoolRequestSource
    {
        return new PoolRequestSource($this->client, $this->requests, $this->role);
    }

    /**
     * @param array<int, RequestInterface> $requests
     * @return array<int, ExecutionResult>
     */
    private function executePool(array $requests): array
    {
        $concurrency = $this->resolveConcurrency(count($requests), 0);
        $onResult = $this->deliver(...);
        if ($concurrency === 1 && AsyncTask::current() === null) {
            $results = [];
            foreach ($requests as $request) {
                $result = ExecutionDispatch::execute($this->client->execution(), $request, $this->role);
                $results[] = $result;
                $onResult($result, $request);
                if ($this->shouldStopOnFailure() && $result->isFailed()) {
                    break;
                }
            }
            return $results;
        }
        return ConcurrentExecution::collect(
            $requests,
            $concurrency,
            fn (RequestInterface $request): PromiseInterface => ExecutionDispatch::executeAsync(
                $this->client->execution(),
                $request,
                $this->role,
            ),
            $this->shouldStopOnFailure(),
            $onResult,
        );
    }

    private function buildPoolResult(array $results): PoolResult
    {
        $collection = ResultCollection::make($results);
        $status = $collection->summarize()->status;

        return new PoolResult(
            exceptionFactory: $this->client->getConfig()->resultExceptions?->exceptionFactory,
            localization: $this->client->getConfig()->localization,
            data: null,
            status: $status,
            errors: new ErrorCollection(array_values(array_filter(array_map(
                static fn (ExecutionResult $result) => $result->errors->first(),
                $collection->failed()->all(),
            )))),
            nested: $collection->all(),
            exception: $collection->firstFailure()?->exception,
        );
    }

    private function shouldStopOnFailure(): bool
    {
        return $this->stopOnFailure ?? $this->config->stopOnFailure ?? false;
    }

    /** @param null|Closure(Throwable): void $onFactoryFailure */
    private function deliver(
        ExecutionResult $result,
        RequestInterface $request,
        ?Closure $onFactoryFailure = null,
    ): void {
        if ($result->isFailed() && $this->onException !== null) {
            try {
                $exception = ResultExceptionSelector::select($result);
            } catch (Throwable $failure) {
                $onFactoryFailure?->__invoke($failure);
                throw $failure;
            }
            ($this->onException)($exception, $request);
        } elseif ($this->onResponse !== null) {
            ($this->onResponse)($result, $request);
        }
    }

    /** @return Closure(ExecutionResult, RequestInterface): void */
    private function consumptionHandler(?Throwable &$factoryFailure): Closure
    {
        $onFactoryFailure = static function (Throwable $failure) use (&$factoryFailure): void {
            $factoryFailure = $failure;
        };
        return function (ExecutionResult $result, RequestInterface $request) use ($onFactoryFailure): void {
            $this->deliver($result, $request, $onFactoryFailure);
        };
    }

    private function consumptionConcurrency(PoolRequestSource $source, PoolConsumptionState $state): int
    {
        if (is_int($this->concurrency)) {
            return max(1, $this->concurrency);
        }
        if (!$source->hasKnownCount()) {
            throw new ConfigurationException(
                new Message('execution.pool_consumption_resolver_requires_count'),
                localization: $this->client->getConfig()->localization,
            );
        }
        try {
            $count = $source->count();
        } catch (Throwable $failure) {
            AsyncTask::current()?->check();
            throw $this->consumptionFailure($state, PoolTerminationReason::SourceFailed, $failure);
        }
        return $this->resolveConcurrency($count, 0);
    }

    /** @param callable(ExecutionResult, RequestInterface): void $onResult */
    private function executeConsumption(
        PoolRequestSource $source,
        PoolConsumptionState $state,
        int $concurrency,
        callable $onResult,
    ): ExecutionOutcome {
        if ($concurrency === 1 && AsyncTask::current() === null) {
            return $this->consumeSequentially($source, $state, $onResult);
        }
        return ConcurrentExecution::run(
            $source->iterate(),
            $concurrency,
            function (RequestInterface $request) use ($state): PromiseInterface {
                $executor = $this->client->execution();
                $state->started++;
                return ExecutionDispatch::executeAsync($executor, $request, $this->role);
            },
            $this->shouldStopOnFailure(),
            static function (ExecutionResult $result, RequestInterface $request, int $index) use ($state): void {
                $state->record($result, $index);
            },
            $onResult,
        );
    }

    /** @param callable(ExecutionResult, RequestInterface): void $onResult */
    private function consumeSequentially(
        PoolRequestSource $source,
        PoolConsumptionState $state,
        callable $onResult,
    ): ExecutionOutcome {
        $stage = 'source';
        try {
            foreach ($source->iterate() as $index => $request) {
                $stage = 'executor';
                $executor = $this->client->execution();
                $state->started++;
                $result = ExecutionDispatch::execute($executor, $request, $this->role);
                $state->record($result, $index);
                $stage = 'handler';
                $onResult($result, $request);
                if ($this->shouldStopOnFailure() && $result->isFailed()) {
                    return new ExecutionOutcome(stoppedOnFailure: true);
                }
                unset($request, $result);
                $stage = 'source';
            }
            return new ExecutionOutcome(sourceExhausted: true);
        } catch (Throwable $failure) {
            return new ExecutionOutcome(failure: $failure, failureStage: $stage);
        }
    }

    private function finishConsumption(
        PoolConsumptionState $state,
        ExecutionOutcome $outcome,
        ?Throwable $factoryFailure,
    ): PoolSummary {
        if ($outcome->failure !== null) {
            $reason = match (true) {
                $outcome->failure === $factoryFailure => PoolTerminationReason::FactoryFailed,
                $outcome->failureStage === 'source' => PoolTerminationReason::SourceFailed,
                $outcome->failureStage === 'handler' => PoolTerminationReason::HandlerFailed,
                default => PoolTerminationReason::ExecutorFailed,
            };
            throw $this->consumptionFailure($state, $reason, $outcome->failure);
        }
        $summary = $state->summarize(
            $outcome->stoppedOnFailure ? PoolTerminationReason::StopOnFailure : PoolTerminationReason::SourceExhausted,
        );
        if ($summary->isAllFailed() && $state->firstFailure !== null) {
            try {
                $selected = ResultExceptionSelector::select($state->firstFailure);
            } catch (Throwable $failure) {
                AsyncTask::current()?->check();
                throw $this->consumptionFailure($state, PoolTerminationReason::FactoryFailed, $failure);
            }
            throw $selected;
        }
        return $summary;
    }

    private function consumptionFailure(
        PoolConsumptionState $state,
        PoolTerminationReason $reason,
        Throwable $failure,
    ): PoolConsumptionException {
        if ($failure instanceof AdmissionRefused) {
            throw $failure;
        }
        return new PoolConsumptionException(
            $state->summarize($reason),
            $failure instanceof ExecutorContractViolation ? $failure->cause : $failure,
            $this->client->getConfig()->localization,
        );
    }

    private function resolveConcurrency(int $pending, int $completed): int
    {
        try {
            $value = $this->concurrencyValue($pending, $completed);
            if (!is_int($value)) {
                throw new ConfigurationException(new Message('execution.pool_resolver_invalid'));
            }
            return max(1, $value);
        } catch (Throwable $failure) {
            AsyncTask::current()?->check();
            if ($failure instanceof AdmissionRefused) {
                throw $failure;
            }
            throw new ConfigurationException(
                new Message('execution.pool_resolver_invalid'),
                previous: $failure,
                localization: $this->client->getConfig()->localization,
            );
        }
    }

    private function concurrencyValue(int $pending, int $completed): mixed
    {
        return match (true) {
            is_int($this->concurrency) => $this->concurrency,
            $this->concurrency instanceof ConcurrencyResolverInterface =>
                $this->concurrency->getConcurrency($pending, $completed),
            default => ($this->concurrency)($pending, $completed),
        };
    }

    private function normalizeConcurrency(
        int|callable|ConcurrencyResolverInterface $concurrency,
    ): int|Closure|ConcurrencyResolverInterface {
        if ($concurrency instanceof ConcurrencyResolverInterface) {
            return $concurrency;
        }

        if (is_int($concurrency)) {
            return $concurrency;
        }

        return Closure::fromCallable($concurrency);
    }

    private function cloneWith(callable $mutate): self
    {
        $clone = clone $this;
        $mutate($clone);
        return $clone;
    }
}
