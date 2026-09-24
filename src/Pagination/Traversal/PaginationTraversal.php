<?php

declare(strict_types=1);

namespace ApiSutra\Pagination\Traversal;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\PaginationConfig;
use ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Execution\ClientExecutorInterface;
use ApiSutra\Contracts\Interfaces\Pagination\PaginableInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\ControlFlow\ExecutorContractViolation;
use ApiSutra\Execution\Async\AsyncTask;
use ApiSutra\Execution\ExecutionDispatch;
use ApiSutra\Localization\Message;
use ApiSutra\Pagination\PaginationConfigResolver;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Pipeline\Diagnostics\ExecutionEnvelope;
use ApiSutra\Pipeline\Diagnostics\ExecutionScope;
use ApiSutra\Request\PaginationOptions;
use ApiSutra\Request\RequestExecution;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\PaginatedResult;
use ApiSutra\VO\Metadata\PaginationMeta;
use ApiSutra\VO\Pipeline\PipelineContext;
use Generator;
use Throwable;
use ApiSutra\Support\ContainerProviderRegistry;
use ApiSutra\Exceptions\ControlFlow\AdmissionRefused;
use Traversable;

/** @internal Координация одного обхода внутри открытого scope; без публичной доставки ошибок. */
final class PaginationTraversal
{
    private readonly PaginationConfig $pagination;
    private readonly PaginationOptions $paginationOptions;
    private ?PaginationResultAccumulator $accumulator = null;

    public function __construct(
        private readonly AbstractRequest&PaginableInterface $request,
        private readonly ?RequestOptions $options,
        ?PaginationOptions $paginationOptions,
        private readonly ClientConfig $config,
        private readonly ClientExecutorInterface $execution,
    ) {
        $this->pagination = new PaginationConfigResolver($config)->resolve($request);
        $this->paginationOptions = $paginationOptions ?? PaginationOptions::empty();
    }

    public function collect(PaginationRule $rule, ExecutionScope $scope, PipelineContext $root): PaginatedResult
    {
        $accumulator = $this->accumulator = new PaginationResultAccumulator($this->config, $this->pagination, $this->request::class, $scope);
        $failures = new PaginationFailureFactory($this->request, $this->config, $scope);
        try {
            $progress = new PaginationProgress($rule, $this->pagination, $this->paginationOptions);
            $pages = $this->pages($progress, $root);
            $record = function (int $page, ExecutionResult $result) use ($accumulator, $progress, $root, $failures): void {
                $accumulator->record($page, $result);
                if ($result->isFailed() && $result->errors->first() === null) {
                    $accumulator->record($page, $result->withErrors($failures->guard(new Message('pagination.page_loading_failed'), $page, 'pagination_page_failed')->errors));
                }
                $meta = $result->isFailed() ? null : $this->resolveMeta($result);
                if ($meta !== null) {
                    $accumulator->meta($page, $meta);
                }
                $progress->accept($page, $result->isFailed(), $meta);
                if ($root->budget?->remainingMs() === 0) {
                    $progress->stop();
                }
            };
            if ($rule->concurrency === 1) {
                foreach ($pages as $page => $request) {
                    $record($page, ExecutionDispatch::execute($this->execution, $request, RequestRole::Root, $root));
                }
            } else {
                new ConcurrentPageLoader()->load(
                    $pages,
                    $rule->concurrency,
                    fn (RequestInterface $request) => ExecutionDispatch::executeAsync($this->execution, $request, RequestRole::Root, $root),
                    $record,
                );
            }
            $progress->finish();
            if ($progress->failure !== null) {
                $accumulator->fail($failures->guard($progress->failure, $progress->failurePage ?? 1, $progress->failureReason ?? 'pagination_stalled'));
            }
        } catch (AdmissionRefused | ExecutorContractViolation $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            AsyncTask::current()?->check();
            $accumulator->fail($failures->exception($exception), $exception instanceof ConfigurationException);
        } finally {
            $root->nested = $accumulator->pages();
        }
        try {
            return $accumulator->build();
        } catch (Throwable $exception) {
            AsyncTask::current()?->check();
            if ($exception instanceof AdmissionRefused) {
                throw $exception;
            }
            // Не вызываем ошибочную пользовательскую фабрику коллекции повторно.
            $accumulator->fail($failures->exception($exception), true);
            return $accumulator->build();
        }
    }

    public function interrupted(PaginatedResult $result, ExecutionResult $failure): PaginatedResult
    {
        return $this->accumulator?->interrupted($result, $failure) ?? PaginationResultAccumulator::fromResult($failure, $this->config);
    }

    /** @return Traversable<int, ExecutionResult> */
    public function stream(ExecutionScope $scope): Traversable
    {
        $provider = ContainerProviderRegistry::resolve($this->config->containerProvider);
        $failures = new PaginationFailureFactory($this->request, $this->config, $scope);
        try {
            $progress = new PaginationProgress(PaginationRule::all(), $this->pagination, $this->paginationOptions);
            foreach ($this->pages($progress) as $page => $request) {
                $result = ContainerProviderRegistry::withProvider($provider, fn (): ExecutionResult => ExecutionDispatch::execute($this->execution, $request, RequestRole::Root, parentTrace: $scope->trace));
                yield $result;
                if ($result->isFailed()) {
                    return;
                }
                $progress->accept($page, false, $this->resolveMeta($result));
            }
            $progress->finish();
            if ($progress->failure !== null) {
                yield $failures->guard($progress->failure, $progress->failurePage ?? 1, $progress->failureReason ?? 'pagination_stalled');
            }
        } catch (AdmissionRefused | ExecutorContractViolation $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            AsyncTask::current()?->check();
            yield $failures->exception($exception);
        }
    }

    /** @return Generator<int, RequestInterface> */
    private function pages(PaginationProgress $progress, ?PipelineContext $root = null): Generator
    {
        while (($page = $progress->next()) !== null) {
            $root?->budget?->check('pagination');
            yield $page => $this->buildExecution($page, $progress);
        }
    }

    private function buildExecution(int $page, PaginationProgress $progress): ExecutionEnvelope
    {
        // Сохраняем пользовательские withPage/withLimit; runtime-опции берём из снимка обхода.
        $execution = $this->request;
        if ($progress->limit !== null) {
            $execution = $this->snapshot($execution->withLimit($progress->limit));
        }
        $execution = $this->snapshot($execution->withPage($progress->offset($page)));
        if ($progress->cursor !== null) {
            $execution = $execution->withCursor($progress->cursor);
        }
        return new ExecutionEnvelope(
            $execution,
            options: ($this->options ?? $execution->getOptions())->withPaginationRule(PaginationRule::single()),
        );
    }

    private function snapshot(RequestExecutionInterface $execution): RequestExecution
    {
        if ($execution instanceof RequestExecution) {
            return $execution;
        }
        $request = $execution->getRequest();
        if (!$request instanceof AbstractRequest) {
            throw new ConfigurationException(new Message('request.request_does_not_support_pagination'));
        }
        return new RequestExecution($request, $execution->getOptions(), $execution->getPaginationOptions());
    }

    private function resolveMeta(ExecutionResult $result): PaginationMeta
    {
        return $result->meta instanceof PaginationMeta
            ? $result->meta
            : $this->request->extractMeta(($result->response ?? $result->debug?->response)?->json() ?? []);
    }
}
