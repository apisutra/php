<?php

declare(strict_types=1);

namespace ApiSutra\Pagination;

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Pagination\PaginableInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\ControlFlow\ExecutorContractViolation;
use ApiSutra\Execution\ExecutionDispatch;
use ApiSutra\Localization\Message;
use ApiSutra\Pagination\Traversal\PaginationFailureFactory;
use ApiSutra\Pagination\Traversal\PaginationResultAccumulator;
use ApiSutra\Pagination\Traversal\PaginationTraversal;
use ApiSutra\Pipeline\Diagnostics\ExecutionScope;
use ApiSutra\Request\PaginationOptions;
use ApiSutra\Request\RequestExecution;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\PaginatedResult;
use ApiSutra\Result\ResultExceptionSelector;
use IteratorAggregate;
use Override;
use Throwable;
use ApiSutra\Exceptions\ControlFlow\AdmissionRefused;
use Traversable;

/** Публичный обход страниц: сбор результата отделён от его выдачи. */
final class Paginator implements IteratorAggregate
{
    private readonly ClientInterface $client;
    private readonly AbstractRequest&PaginableInterface $request;
    private RequestOptions $options;
    private PaginationOptions $paginationOptions;
    private FailStrategy $failStrategy;
    private int $concurrency;

    public function __construct(
        AbstractRequest $request,
        ?RequestOptions $options = null,
        ?PaginationOptions $paginationOptions = null,
        ?ClientInterface $client = null,
    ) {
        if (!$request instanceof PaginableInterface) {
            throw new ConfigurationException(new Message('request.request_does_not_support_pagination'));
        }
        $this->request = $request;
        $this->client = $client ?? $request->getClient();
        $this->options = $options ?? $request->getOptions();
        $this->paginationOptions = $paginationOptions ?? PaginationOptions::empty();
        $rule = $this->options->getPaginationRuleOverride() ?? $this->client->getConfig()->getPaginationRule();
        $this->failStrategy = $rule->failStrategy;
        $this->concurrency = $rule->concurrency;
    }

    public function withPerPage(int $limit): self
    {
        if ($limit < 1) {
            throw new ConfigurationException(new Message('pagination.page_size_must_be_positive'));
        }
        $copy = clone $this;
        $copy->paginationOptions = $this->paginationOptions->withLimit($limit);
        return $copy;
    }

    public function withFailStrategy(FailStrategy $strategy): self
    {
        $copy = clone $this;
        $copy->failStrategy = $strategy;
        return $copy;
    }

    public function withConcurrency(int $concurrency): self
    {
        $rule = PaginationRule::all(concurrency: $concurrency);
        $copy = clone $this;
        $copy->concurrency = $rule->concurrency;
        return $copy;
    }

    public function all(): PaginatedResult
    {
        return $this->collect(PaginationRule::all($this->failStrategy, $this->concurrency));
    }

    public function pages(int $count): PaginatedResult
    {
        return $this->collect(PaginationRule::pages($count, $this->failStrategy, $this->concurrency));
    }

    public function range(int $from, int $to): PaginatedResult
    {
        return $this->collect(PaginationRule::range($from, $to, $this->failStrategy, $this->concurrency));
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return $this->iterate(items: false);
    }

    /** @return Traversable<int, mixed> */
    public function items(): Traversable
    {
        return $this->iterate(items: true);
    }

    /** @return Traversable<int, mixed> */
    private function iterate(bool $items): Traversable
    {
        $scope = $this->begin();
        $reader = $items ? new PaginationItemsReader() : null;
        $index = 0;
        try {
            foreach ($this->stream($scope) as $result) {
                if ($reader !== null && !$result->isFailed()) {
                    try {
                        foreach ($reader->read($result->data) as $item) {
                            yield $index++ => $item;
                        }
                        continue;
                    } catch (AdmissionRefused $signal) {
                        throw $signal;
                    } catch (Throwable $exception) {
                        $result = new PaginationFailureFactory($this->request, $this->client->getConfig(), $scope)->exception($exception);
                    }
                }
                if ($result->isFailed()) {
                    $finished = $scope->finish($result);
                    if ($result->trace === null || $result->trace === $scope->trace) {
                        $result = $finished;
                    }
                    if ($items || $this->client->getConfig()->throwOnErrors) {
                        throw ResultExceptionSelector::select($result);
                    }
                    yield $result;
                    return;
                }
                yield $result;
            }
            $scope->finish(new ExecutionResult(null, ResultStatus::SUCCESS, new ErrorCollection([])));
        } catch (AdmissionRefused $signal) {
            $scope->finish(new PaginationFailureFactory($this->request, $this->client->getConfig(), $scope)->exception($signal->cause));
            throw $signal;
        } catch (ExecutorContractViolation $exception) {
            $scope->finish(new ExecutionResult(null, ResultStatus::FAILED, new ErrorCollection([]), exception: $exception->cause));
            throw $exception->cause;
        } finally {
            $scope->abandon();
            $scope->release();
        }
    }

    /** @return Traversable<int, ExecutionResult> */
    private function stream(ExecutionScope $scope): Traversable
    {
        try {
            if ($this->concurrency > 1) {
                throw new ConfigurationException(new Message('pagination.concurrent_iterator_not_supported'));
            }
            $traversal = new PaginationTraversal($this->request, $this->options, $this->paginationOptions, $this->client->getConfig(), $this->client->execution());
            yield from $traversal->stream($scope);
        } catch (AdmissionRefused | ExecutorContractViolation $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            yield new PaginationFailureFactory($this->request, $this->client->getConfig(), $scope)->exception($exception);
        }
    }

    private function collect(PaginationRule $rule): PaginatedResult
    {
        $execution = new RequestExecution($this->request, $this->options->withPaginationRule($rule), $this->paginationOptions);
        try {
            $result = ExecutionDispatch::execute($this->client->execution(), $execution, RequestRole::Root);
        } catch (ExecutorContractViolation $exception) {
            throw $exception->cause;
        }
        $result = $result instanceof PaginatedResult ? $result : PaginationResultAccumulator::fromResult($result, $this->client->getConfig());
        if ($this->client->getConfig()->throwOnErrors) {
            $result->throw();
        }
        return $result;
    }

    private function begin(): ExecutionScope
    {
        $scope = $this->client->execution()->createScope($this->request::class, traceId: $this->options->getTraceIdOverride() ?? $this->request->getTraceIdOverride());
        $scope->start();
        return $scope;
    }
}
