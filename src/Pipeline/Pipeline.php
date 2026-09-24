<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline;

use ApiSutra\Exceptions\ControlFlow\ExecutionCancelledException;
use ApiSutra\Execution\Async\AsyncTask;
use ApiSutra\Exceptions\ControlFlow\ExecutorContractViolation;
use ApiSutra\Pipeline\Hydration\ResponseContractGuard;
use ApiSutra\Localization\ExceptionLocalization;
use ApiSutra\Localization\Message;
use ApiSutra\Attributes\AttributeRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Retry\RetryDelayPolicyInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestOptionsProviderInterface;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use ApiSutra\Core\AbstractClient;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Exceptions\ControlFlow\EarlyReturnException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Extensions\ExtensionRegistry;
use ApiSutra\Hooks\HookRegistry;
use ApiSutra\Pipeline\Attributes\StageProcessor;
use ApiSutra\Pipeline\Auth\AuthHandler;
use ApiSutra\Pipeline\Cache\CacheManager;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Pipeline\Error\ErrorPolicy;
use ApiSutra\Pipeline\Execution\CompositeFlow;
use ApiSutra\Pipeline\Flow\ExecutionResultBuilder;
use ApiSutra\Pipeline\Flow\PipelineCompositeHandler;
use ApiSutra\Pipeline\Flow\PipelineContextFactory;
use ApiSutra\Pipeline\Flow\PipelineValidator;
use ApiSutra\Pipeline\Flow\RequestContractValidator;
use ApiSutra\Pipeline\Flow\RequestFlowRunner;
use ApiSutra\Pipeline\Flow\RequestPreparationStep;
use ApiSutra\Pipeline\Hooks\HookRunner;
use ApiSutra\Pipeline\Hydration\ResponseHydrator;
use ApiSutra\Pipeline\Preparation\PreparedRequestFactory;
use ApiSutra\Pipeline\Preparation\RequestPreparer;
use ApiSutra\Pipeline\Result\ResultFactory;
use ApiSutra\Pipeline\Transport\RetrySender;
use ApiSutra\RateLimiting\RateLimiter;
use ApiSutra\RateLimiting\Cooldown\CooldownBackendInterface;
use ApiSutra\RateLimiting\Cooldown\Backends\LocalCooldownBackend;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Serializer;
use ApiSutra\Timing\ExecutionBudget;
use ApiSutra\Timing\SystemClock;
use ApiSutra\VO\Pipeline\PipelineContext;
use Psr\Log\LoggerInterface;
use ApiSutra\Exceptions\Core\RuntimeException;
use Throwable;
use ApiSutra\Exceptions\ControlFlow\AdmissionRefused;
use ApiSutra\Pipeline\Diagnostics\ExecutionEnvelope;

/**
 * Внутреннее исполнение стадий открытого invocation.
 *
 * Инварианты:
 * - вход может быть RequestInterface или RequestExecutionInterface;
 * - options извлекаются и из RequestExecutionInterface, и из RequestOptionsProviderInterface;
 * - validate/contracts/composite/preparation/flow выполняются в стабильном порядке;
 * - завершение scope и публичная доставка принадлежат внешним владельцам.
 *
 * @see docs/technical/pipeline.md
 * @see docs/technical/execution.md
 * @see docs/guides/request-pipeline.md
 */
final class Pipeline
{
    private readonly RequestPreparer $requestPreparer;
    private readonly StageProcessor $stageProcessor;
    private readonly AuthHandler $authHandler;
    private readonly HookRunner $hookRunner;
    private readonly CacheManager $cacheManager;
    private readonly RetrySender $retrySender;
    private readonly ErrorPolicy $errorPolicy;
    private readonly ResponseHydrator $responseHydrator;
    private readonly ResultFactory $resultFactory;
    private readonly AuditLogger $auditLogger;
    private readonly CompositeFlow $compositeFlow;
    private readonly ExecutionResultBuilder $resultBuilder;
    private readonly PipelineContextFactory $contextFactory;
    private readonly PipelineValidator $validator;
    private readonly RequestContractValidator $requestContractValidator;
    private readonly PipelineCompositeHandler $compositeHandler;
    private readonly RequestPreparationStep $preparationStep;
    private readonly RequestFlowRunner $flowRunner;
    private readonly PreparedRequestFactory $preparedRequestFactory;
    private readonly ClockInterface $clock;

    /**
     * Собирает зависимости и компоненты пайплайна.
     */
    public function __construct(
        private readonly ClientConfig $config,
        private readonly TransportInterface $transport,
        private readonly Serializer $serializer,
        private readonly Hydrator $hydrator,
        private readonly HookRegistry $hooks,
        private readonly AttributeRegistry $attributes,
        private readonly ExtensionRegistry $extensions,
        private readonly RateLimiter $rateLimiter,
        private readonly RetryDelayPolicyInterface $retryDelayPolicy,
        private readonly ?SleeperInterface $sleeper = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?AbstractClient $client = null,
        private ?string $traceId = null,
        ?ClockInterface $clock = null,
        ?CooldownBackendInterface $cooldownBackend = null,
    ) {
        $this->clock = $clock ?? new SystemClock();
        $this->requestPreparer = new RequestPreparer($this->config);
        $this->stageProcessor = new StageProcessor($this->attributes);
        $this->hookRunner = new HookRunner($this->hooks);
        $this->errorPolicy = new ErrorPolicy($this->client, $this->clock);
        $this->responseHydrator = new ResponseHydrator($this->config, $this->hydrator, $this->extensions);
        $this->resultFactory = new ResultFactory($this->errorPolicy);
        $this->auditLogger = new AuditLogger($this->config);
        $this->preparedRequestFactory = new PreparedRequestFactory($this->serializer, $this->requestPreparer);
        $this->authHandler = new AuthHandler(
            $this->config,
            $this->sleeper,
            $this->clock,
            $this->client !== null ? $this->client::class : self::class,
        );
        $this->cacheManager = new CacheManager(
            $this->config,
            $this->preparedRequestFactory,
            $this->requestPreparer,
            $this->authHandler,
            $this->client !== null ? $this->client::class : self::class,
        );
        $this->retrySender = new RetrySender(
            config: $this->config,
            transport: $this->transport,
            retryDelayPolicy: $this->retryDelayPolicy,
            rateLimiter: $this->rateLimiter,
            hookRunner: $this->hookRunner,
            authHandler: $this->authHandler,
            errorPolicy: $this->errorPolicy,
            auditLogger: $this->auditLogger,
            client: $this->client,
            sleeper: $this->sleeper,
            cooldownBackend: $cooldownBackend ?? $config->cooldownBackend ?? new LocalCooldownBackend($this->clock),
        );
        $this->compositeFlow = new CompositeFlow($this->hydrator);
        $this->resultBuilder = new ExecutionResultBuilder($this->config, $this->responseHydrator);
        $this->contextFactory = new PipelineContextFactory(
            config: $this->config,
            requestPreparer: $this->requestPreparer,
            stageProcessor: $this->stageProcessor,
        );
        $this->validator = new PipelineValidator($this->resultBuilder);
        $this->requestContractValidator = new RequestContractValidator();
        $this->compositeHandler = new PipelineCompositeHandler($this->compositeFlow);
        $this->preparationStep = new RequestPreparationStep($this->preparedRequestFactory, $this->auditLogger);
        $this->flowRunner = new RequestFlowRunner(
            stageProcessor: $this->stageProcessor,
            authHandler: $this->authHandler,
            hookRunner: $this->hookRunner,
            cacheManager: $this->cacheManager,
            retrySender: $this->retrySender,
            errorPolicy: $this->errorPolicy,
            responseHydrator: $this->responseHydrator,
            resultFactory: $this->resultFactory,
            auditLogger: $this->auditLogger,
            resultBuilder: $this->resultBuilder,
        );
    }

    /**
     * Устанавливает traceId по умолчанию.
     */
    public function setTraceId(?string $traceId): void
    {
        $this->traceId = $traceId;
    }

    /** @internal Исполняет стадии уже открытого запуска, не завершает scope. */
    public function run(PipelineContext $context): ExecutionResult
    {
        $request = $context->request;
        $audit = [];
        $this->initializeBudget($context);
        $this->retrySender->assertConcurrencySupported($context);
        ResponseContractGuard::validate($request, $this->config);
        $this->responseHydrator->assertResponseModeSupported($request, $context);
        $startTime = $this->contextFactory->start($request, $context, $audit);
        $context->budget->check('started');
        return $this->runStages($request, $context, $audit, $startTime);
    }

    /** @internal Один срок создаётся до выбора Single/пагинации и не перезапускается стадиями. */
    public function initializeBudget(PipelineContext $context): void
    {
        $clock = $context->parent->budget->clock ?? $this->clock;
        $context->budget ??= new ExecutionBudget($clock, $this->config->retry?->totalTimeoutMs, $context->parent?->budget, $clock->monotonicMs(), $context->options?->getDeadline());
        $context->budget->check('started');
    }

    /** @internal Контекст не владеет start/finish: это ответственность ClientExecutor. */
    public function createContext(
        ExecutionEnvelope $envelope,
        RequestRole $role = RequestRole::Root,
        ?PipelineContext $parent = null,
        ?string $traceId = null,
    ): PipelineContext {
        $context = $this->contextFactory->create(
            $envelope->getRequest(),
            $role,
            $parent,
            $envelope->parentTrace->traceId ?? $parent->traceId ?? $traceId,
            $this->traceId,
            $envelope->getOptions(),
            $envelope->getPaginationOptions(),
            $envelope->parentTrace,
        );
        $envelope->context = $context;
        $context->client = $this->client;
        return $context;
    }

    /** @internal Построение ошибки не завершает запуск и не выбирает throw/return. */
    public function failure(PipelineContext $context, Throwable $exception): ExecutionResult
    {
        if ($exception instanceof ExecutorContractViolation || $exception instanceof AdmissionRefused) {
            throw $exception;
        }
        if (AsyncTask::current()?->cancelled) {
            $exception = new ExecutionCancelledException();
        }
        if (!$exception instanceof ExecutionCancelledException && !$exception instanceof ExecutionDeadlineException && $context->budget?->remainingMs() === 0) {
            $exception = new ExecutionDeadlineException('execution', $exception);
        }
        if ($exception instanceof ExecutionDeadlineException) {
            $exception = new ExecutionDeadlineException(
                $exception->stage,
                $exception->getPrevious(),
                $exception->response ?? $context->response ?? $context->lastResponse,
                $exception->bytesWritten,
                $exception->partial,
                $context->transmissionState,
            );
        }
        $exception = ExceptionLocalization::apply($exception, $this->config->localization);
        $context->failureException = $exception;
        $audit = $context->scope->audit ?? [];
        return $this->resultBuilder->buildExceptionResult($context->request, $context, $audit, microtime(true), $exception);
    }

    private function runStages(
        RequestInterface $request,
        PipelineContext $context,
        array &$audit,
        float $startTime,
    ): ExecutionResult {
        $validationResult = $this->validator->validate($request, $context, $audit, $startTime);
        $context->budget?->check('validation');
        if ($validationResult instanceof ExecutionResult) {
            return $validationResult;
        }

        $contractValidation = $this->requestContractValidator->validate($request);
        $context->budget?->check('validation');
        $context->requestContractDebug = $contractValidation->oneOfDebug;
        if ($contractValidation->failed()) {
            $violation = $contractValidation->violation;
            if ($violation === null) {
                throw new RuntimeException(new Message('pipeline.expected_a_request_contract_error'));
            }

            return $this->resultBuilder->buildRequestContractViolation(
                request: $request,
                context: $context,
                audit: $audit,
                startTime: $startTime,
                violation: $violation,
            );
        }

        $compositeResult = $this->compositeHandler->handle($request, $context);
        $context->budget?->check('composite');
        if ($compositeResult instanceof ExecutionResult) {
            return $compositeResult;
        }

        $prepared = $this->preparationStep->prepare($request, $context);
        $context->budget?->check('preparation');

        try {
            return $this->flowRunner->run($request, $context, $audit, $startTime, $prepared);
        } catch (EarlyReturnException $exception) {
            return $this->resultBuilder->buildEarlyReturnResult($request, $context, $audit, $startTime, $prepared, $exception);
        } catch (Throwable $exception) {
            return $this->failure($context, $exception);
        }
    }

    /** Инвалидировать настроенное пространство кеша клиента. */
    public function clearCacheScope(): void
    {
        $this->cacheManager->clearScope();
    }

    /**
     * Очистить кеш для конкретного запроса.
     */
    public function clearCache(RequestInterface $request): void
    {
        $options = null;
        $paginationOptions = null;
        if ($request instanceof RequestExecutionInterface) {
            $options = $request->getOptions();
            $paginationOptions = $request->getPaginationOptions();
            $request = $request->getRequest();
        }
        if ($options === null && $request instanceof RequestOptionsProviderInterface) {
            $options = $request->getOptions();
        }

        $this->cacheManager->clearCache($request, $this->traceId, $options, $paginationOptions);
    }
}
