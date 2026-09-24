<?php

declare(strict_types=1);

namespace ApiSutra\Execution;

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Execution\ClientExecutorInterface;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Diagnostics\ExecutionTrace;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Pipeline\Diagnostics\ExecutionEnvelope;
use ApiSutra\Pipeline\Diagnostics\ExecutionScope;
use ApiSutra\Pipeline\Pipeline;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Contracts\Interfaces\Pagination\PaginableInterface;
use ApiSutra\Pagination\Traversal\PaginationTraversal;
use ApiSutra\Exceptions\ControlFlow\ExecutorContractViolation;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\PaginatedResult;
use ApiSutra\Timing\SystemClock;
use ApiSutra\VO\Pipeline\PipelineContext;
use ApiSutra\Execution\Async\AsyncRuntime;
use GuzzleHttp\Promise\PromiseInterface;
use Override;
use Throwable;
use ApiSutra\Exceptions\ControlFlow\AdmissionRefused;
use ApiSutra\Support\ContainerProviderRegistry;
use ApiSutra\Pipeline\Diagnostics\ExecutionObservation;

/** Владелец одного запуска запроса; публичная доставка выполняется после return. */
final class ClientExecutor implements ClientExecutorInterface
{
    public function __construct(
        private readonly ClientConfig $config,
        private readonly Pipeline $pipeline,
        private readonly ClockInterface $clock = new SystemClock(),
        private ?string $traceId = null,
    ) {
        $this->activity = new ExecutionActivity();
    }

    private ?AsyncRuntime $async = null;
    private readonly ExecutionActivity $activity;

    public function isActive(): bool
    {
        return $this->activity->isActive();
    }

    public function setTraceId(?string $traceId): void
    {
        $this->traceId = $traceId;
    }

    #[Override]
    public function execute(
        RequestInterface $request,
        RequestRole $role = RequestRole::Root,
        ?PipelineContext $parent = null,
        ?ExecutionTrace $parentTrace = null,
        ?ClientExecutorInterface $dispatcher = null,
    ): ExecutionResult {
        return ContainerProviderRegistry::withProvider(
            ContainerProviderRegistry::resolve($this->config->containerProvider),
            fn (): ExecutionResult => $this->executeRequest($request, $role, $parent, $parentTrace, $dispatcher),
        );
    }

    private function executeRequest(
        RequestInterface $request,
        RequestRole $role,
        ?PipelineContext $parent,
        ?ExecutionTrace $parentTrace,
        ?ClientExecutorInterface $dispatcher,
    ): ExecutionResult {
        if ($parent !== null && $parentTrace !== null && $parent->trace !== $parentTrace) {
            throw new ConfigurationException(new Message('diagnostics.conflicting_trace'), localization: $this->config->localization);
        }
        $envelope = new ExecutionEnvelope($request, $parentTrace);
        $context = $this->pipeline->createContext($envelope, $role, $parent, $this->traceId);
        if ($request instanceof ExecutionEnvelope) {
            // Возвращаем служебный контекст отправителю envelope, не извлекая его из общего request.
            $request->context = $context;
        }
        $leave = $context->request instanceof AbstractRequest ? $context->request->enterExecutionContext($context) : null;
        try {
            $context->executor = $dispatcher ?? $this;
            $scope = new ExecutionScope(
                $context->trace,
                new AuditLogger($this->config),
                $parent->budget->clock ?? $this->clock,
                $context->request::class,
                $context->role,
                ExecutionObservation::forConfig($this->config),
                $this->activity,
            );
            $context->scope = $scope;
            $scope->start();
            $traversal = null;
            try {
                $this->pipeline->initializeBudget($context);
                $rule = $context->options?->getPaginationRuleOverride() ?? $this->config->getPaginationRule();
                if ($rule->isSingle()) {
                    $result = $this->pipeline->run($context);
                } else {
                    $request = $context->request;
                    if (!$request instanceof AbstractRequest || !$request instanceof PaginableInterface) {
                        throw new ConfigurationException(new Message('request.request_does_not_support_pagination'));
                    }
                    $traversal = new PaginationTraversal($request, $context->options, $context->paginationOptions, $this->config, $context->executor);
                    $result = $traversal->collect($rule, $scope, $context);
                }
            } catch (AdmissionRefused $signal) {
                $scope->finish($this->pipeline->failure($context, $signal->cause));
                throw $signal;
            } catch (ExecutorContractViolation $exception) {
                $scope->finish($this->pipeline->failure($context, $exception->cause));
                throw $exception->cause;
            } catch (Throwable $exception) {
                $result = $this->pipeline->failure($context, $exception);
            }
            try {
                $result = $this->attachMeta($result);
            } catch (AdmissionRefused $signal) {
                $scope->finish($this->pipeline->failure($context, $signal->cause));
                throw $signal;
            } catch (Throwable $exception) {
                $failure = $this->pipeline->failure($context, $exception);
                $result = $result instanceof PaginatedResult && $traversal !== null
                    ? $traversal->interrupted($result, $failure)
                    : ($result->isFailed()
                        ? $result->withErrors(new ErrorCollection([...$result->errors->all(), ...$failure->errors->all()]))
                        : $failure);
            }
            if (!$result->isFailed()) {
                try {
                    $context->budget?->check('completed');
                } catch (Throwable $exception) {
                    $failure = $this->pipeline->failure($context, $exception);
                    $result = $result instanceof PaginatedResult && $traversal !== null
                        ? $traversal->interrupted($result, $failure)
                        : $failure;
                }
            }
            if ($parent !== null && $context->lastResponse !== null) {
                $parent->lastResponse = $context->lastResponse;
            }
            return $scope->finish($result->localized($this->config->localization));
        } finally {
            $context->client = null;
            $leave?->__invoke();
            $context->scope?->release();
        }
    }

    #[Override]
    public function executeAsync(
        RequestInterface $request,
        RequestRole $role = RequestRole::Root,
        ?PipelineContext $parent = null,
        ?ExecutionTrace $parentTrace = null,
        ?ClientExecutorInterface $dispatcher = null,
    ): PromiseInterface {
        return ($this->async ??= new AsyncRuntime())->start(fn (): ExecutionResult => $this->execute($request, $role, $parent, $parentTrace, $dispatcher));
    }

    #[Override]
    public function createScope(
        ?string $requestClass = null,
        ?ExecutionTrace $parent = null,
        ?string $traceId = null,
    ): ExecutionScope {
        return new ExecutionScope(
            ExecutionTrace::create($traceId ?? $parent->traceId ?? $this->traceId, $parent),
            new AuditLogger($this->config),
            $this->clock,
            $requestClass,
            observation: ExecutionObservation::forConfig($this->config),
            activity: $this->activity,
        );
    }

    private function attachMeta(ExecutionResult $result): ExecutionResult
    {
        $result = $result->localized($this->config->localization);
        if ($result->meta !== null || $this->config->resultMetaExtractor === null) {
            return $result;
        }
        $meta = $this->config->resultMetaExtractor->extract($result);
        return $meta === null ? $result : $result->withMeta($meta);
    }
}
