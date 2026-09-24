<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Transport;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Contracts\Interfaces\Retry\RetryDelayPolicyInterface;
use ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use ApiSutra\Core\AbstractClient;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Enums\Http\TransmissionState;
use ApiSutra\Exceptions\Auth\AuthRefreshFailedException;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\ControlFlow\ControlFlowException;
use ApiSutra\Exceptions\ControlFlow\ExecutionCancelledException;
use ApiSutra\Exceptions\ControlFlow\ExecutorContractViolation;
use ApiSutra\Exceptions\ControlFlow\RetryableException;
use ApiSutra\Exceptions\RateLimiting\RateLimitBackendException;
use ApiSutra\Exceptions\RateLimiting\CooldownBackendException;
use ApiSutra\Exceptions\Request\CooldownException;
use ApiSutra\Exceptions\Request\RateLimitException;
use ApiSutra\Exceptions\Testing\RecordingException;
use ApiSutra\Exceptions\Transport\ConnectionException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Exceptions\Transport\TransportException;
use ApiSutra\Execution\Async\AsyncTask;
use ApiSutra\Files\FileTransferGuard;
use ApiSutra\Http\DestinationGuard;
use ApiSutra\Http\RequestBodyGuard;
use ApiSutra\Localization\Message;
use ApiSutra\Pipeline\Auth\AuthHandler;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Pipeline\Error\ErrorPolicy;
use ApiSutra\Pipeline\Hooks\HookRunner;
use ApiSutra\Pipeline\Preparation\TimeoutResolver;
use ApiSutra\RateLimiting\Cooldown\CooldownBackendInterface;
use ApiSutra\RateLimiting\Cooldown\Backends\LocalCooldownBackend;
use ApiSutra\RateLimiting\RateLimiter;
use ApiSutra\Retry\RequestBodyReplay;
use ApiSutra\Retry\RetryAfterDelay;
use ApiSutra\Timing\CooperativeSleeper;
use ApiSutra\Timing\ExecutionBudget;
use ApiSutra\Timing\SystemClock;
use ApiSutra\Transport\TransportCapabilities;
use ApiSutra\Transport\TransportExceptionNormalizer;
use ApiSutra\Transport\TransportExecution;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;
use Psr\Log\LogLevel;
use Throwable;
use ApiSutra\Exceptions\ControlFlow\AdmissionRefused;

final readonly class RetrySender
{
    private RetryConfigResolver $retryConfigResolver;
    private RetryDecisionMaker $retryDecisionMaker;
    private RateLimitApplier $rateLimitApplier;
    private DelayApplier $delayApplier;
    private ?RetryAfterDelay $retryAfterDelay;
    private CooldownResolver $cooldownResolver;
    private CooldownCoordinator $cooldown;

    public function __construct(
        private ClientConfig $config,
        private TransportInterface $transport,
        private RetryDelayPolicyInterface $retryDelayPolicy,
        RateLimiter $rateLimiter,
        private HookRunner $hookRunner,
        private AuthHandler $authHandler,
        ErrorPolicy $errorPolicy,
        private AuditLogger $auditLogger,
        ?AbstractClient $client = null,
        ?SleeperInterface $sleeper = null,
        ?RetryAfterDelay $retryAfterDelay = null,
        ?CooldownBackendInterface $cooldownBackend = null,
    ) {
        $this->retryConfigResolver = new RetryConfigResolver($this->config);
        $this->retryDecisionMaker = new RetryDecisionMaker(
            config: $this->config,
            errorPolicy: $errorPolicy,
            client: $client,
        );
        $this->rateLimitApplier = new RateLimitApplier($config, $rateLimiter);
        $sleeper ??= new CooperativeSleeper();
        $this->delayApplier = new DelayApplier($config, $sleeper);
        $this->retryAfterDelay = $retryAfterDelay;
        $backend = $cooldownBackend ?? $config->cooldownBackend ?? new LocalCooldownBackend(new SystemClock());
        $this->cooldown = new CooldownCoordinator(new CooldownStore($backend, $auditLogger), $sleeper, $auditLogger);
        $this->cooldownResolver = new CooldownResolver($config, $authHandler, $auditLogger);
    }

    public function assertConcurrencySupported(PipelineContext $context): void
    {
        if (AsyncTask::current() === null) {
            return;
        }
        TransportExecution::assertConcurrent($this->transport);
    }

    /** Проверка поддержки до auth/refresh и любых HTTP-попыток этого исполнения. */
    public function assertDestinationSupported(PipelineContext $context): void
    {
        DestinationGuard::checkContext($context);
        FileTransferGuard::checkContext($context);
        DestinationGuard::checkCapability($this->transport, $context->destination);
        $transfer = $context->preparedRequest !== null
            ? FileTransferGuard::options($context->preparedRequest) : $context->fileTransfer;
        FileTransferGuard::checkCapability($this->transport, $transfer);
    }

    public function sendWithRetry(RequestInterface $request, PipelineContext $context): ProviderResponse
    {
        $this->assertDestinationSupported($context);
        $retryConfig = $this->retryConfigResolver->resolve($request, $context->options);
        $attempts = $retryConfig->attempts ?? 1;

        $attempt = 1;
        $lastException = null;
        $authRetryUsed = 0;
        $authRetryLimit = max(0, $this->config->authRetryAttempts);
        $context->budget ??= new ExecutionBudget(new SystemClock(), $this->config->retry?->totalTimeoutMs, $context->parent?->budget);
        $retryAfterDelay = $this->retryAfterDelay ?? new RetryAfterDelay(static fn (): int => $context->budget->clock->unixTime());
        $bodyReplay = new RequestBodyReplay($context->preparedRequest);
        $retryNotBeforeMs = 0;
        $additionalWaitMs = 0;

        while ($attempt <= $attempts) {
            $this->assertPreparedRequest($context);
            $context->budget->check('before_attempt', $lastException);

            try {
                $cooldownRule = $this->admitAttempt($request, $context, $retryNotBeforeMs, $additionalWaitMs);
                $response = $this->sendAttempt($context, $cooldownRule, $retryAfterDelay);
                $lastException = null;

                $responseReceivedMs = $context->budget->clock->monotonicMs();
                $serverDelayMs = $this->acceptResponse($context, $response, $cooldownRule, $retryAfterDelay, $responseReceivedMs);
                $this->cooldown->flush($context);
                $context->budget->check('http_response');
                $this->hookRunner->runHookStage(Hook::AfterResponse, $request, $context);
                $context->budget->check('after_response');

                if (
                    $response->status === 401 && $this->config->authRetryOn401
                    && $authRetryUsed < $authRetryLimit
                    && (!$context->destination?->requiresIsolation() || $this->authHandler->resolveForRequest($request, $context) !== null)
                ) {
                    if (!$this->prepareRepeat($request, $context, $bodyReplay)) {
                        return $response;
                    }
                    if ($this->authHandler->recoverAuthentication($request, $context)) {
                        if (!$this->prepareRepeat($request, $context, $bodyReplay)) {
                            return $response;
                        }
                        $authRetryUsed++;
                        $this->auditLogger->log(LogLevel::WARNING, new Message('pipeline.retry_after_authentication_recovery'), [
                            ...$context->trace->logContext(),
                            'request' => $request::class,
                            'auth_attempt' => $authRetryUsed,
                        ]);
                        $retryNotBeforeMs = 0;
                        continue;
                    }
                }

                if ($attempt < $attempts && $this->retryDecisionMaker->shouldRetry($request, $response, $attempt, $retryConfig, $context->budget->clock)) {
                    if (!$this->prepareRepeat($request, $context, $bodyReplay)) {
                        return $response;
                    }
                    $retryNotBeforeMs = $this->retryDeadline(
                        $context,
                        $retryConfig,
                        $attempt,
                        $serverDelayMs,
                        $responseReceivedMs
                    );
                    $this->auditLogger->log(LogLevel::WARNING, new Message('pipeline.retry_after_response'), [
                        ...$context->trace->logContext(),
                        'request' => $request::class,
                        'attempt' => $attempt,
                        'status' => $response->status,
                    ]);
                    $attempt++;
                    continue;
                }

                return $response;
            } catch (RetryableException $exception) {
                $lastException = $exception;
                $this->auditLogger->log(LogLevel::WARNING, new Message('pipeline.retryable_exception'), [
                    ...$context->trace->logContext(),
                    'request' => $request::class,
                    'attempt' => $attempt,
                    'exception' => $exception::class,
                ]);
                if (
                    $retryConfig === null || $attempt >= $attempts
                    || ($exception->maxAttempts !== null && $attempt >= $exception->maxAttempts)
                    || !$this->prepareRepeat($request, $context, $bodyReplay, $exception)
                ) {
                    throw $exception;
                }
                $retryNotBeforeMs = $this->retryDeadline(
                    $context,
                    $retryConfig,
                    $attempt,
                    $retryAfterDelay->fromSeconds($exception->retryAfter),
                    $context->budget->clock->monotonicMs()
                );
                $attempt++;
                continue;
            } catch (ExecutionDeadlineException $exception) {
                if ($exception->getPrevious() === null && $lastException !== null) {
                    throw new ExecutionDeadlineException($exception->stage, $lastException);
                }
                throw $exception;
            } catch (AdmissionRefused | ControlFlowException | ExecutorContractViolation | ExecutionCancelledException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                if ($exception instanceof CooldownBackendException || $exception instanceof AuthRefreshFailedException || $exception instanceof RecordingException || $exception instanceof ConfigurationException) {
                    throw $exception;
                }
                // Локальный отказ не является HTTP-попыткой и не допускает слепого повтора записи.
                if ($exception instanceof RateLimitBackendException) {
                    throw new RateLimitBackendException($exception->getPrevious(), $context->response ?? $context->lastResponse);
                }
                if ($exception instanceof CooldownException) {
                    throw $exception->withLastResponse($context->response ?? $context->lastResponse);
                }
                if ($exception instanceof RateLimitException && $exception->response === null) {
                    throw new RateLimitException(
                        $exception->messageDefinition() ?? $exception->getMessage(),
                        null,
                        $exception->retryAfter,
                        $exception->getCode(),
                        $exception,
                        $context->response ?? $context->lastResponse,
                    );
                }
                $lastException = $exception;
                if ($context->failureCode !== ErrorCode::HookError && $retryConfig !== null && $this->retryDecisionMaker->isRetryException($exception, $retryConfig) && $attempt < $attempts) {
                    if (!$this->prepareRepeat($request, $context, $bodyReplay, $exception)) {
                        throw $exception;
                    }
                    $retryNotBeforeMs = $this->retryDeadline($context, $retryConfig, $attempt);
                    $this->auditLogger->log(LogLevel::WARNING, new Message('pipeline.retry_after_exception'), [
                        ...$context->trace->logContext(),
                        'request' => $request::class,
                        'attempt' => $attempt,
                        'exception' => $exception::class,
                    ]);
                    $attempt++;
                    continue;
                }

                throw $exception;
            } finally {
                $this->cooldown->flush($context);
            }
        }

        if ($lastException instanceof Throwable) {
            throw $lastException;
        }

        throw new ConnectionException(new Message('pipeline.failed_to_execute_the_request'));
    }

    /** @phpstan-impure Перемотка тела зависит от текущего состояния потока. */
    private function prepareRepeat(RequestInterface $request, PipelineContext $context, RequestBodyReplay $body, ?Throwable $exception = null): bool
    {
        $reason = $this->retryDecisionMaker->isSafe($request, $context->preparedRequest->method, $context->response, $exception)
            ? $body->restore($context->preparedRequest)
            : 'operation_not_safe';
        if ($reason === null) {
            return true;
        }
        $context->retryRefusalReason = $reason;
        $this->auditLogger->log(LogLevel::WARNING, new Message('pipeline.request_retry_is_forbidden'), [
            ...$context->trace->logContext(),
            'request' => $request::class,
            'retryRefusalReason' => $reason,
        ]);
        return false;
    }

    private function retryDeadline(
        PipelineContext $context,
        RetryConfig $config,
        int $retryNumber,
        int $minimumDelayMs = 0,
        ?int $receivedMs = null,
    ): int {
        try {
            $delay = $this->retryDelayPolicy->delayMs($config, $retryNumber);
        } catch (Throwable $exception) {
            throw new ConfigurationException(new Message('retry.invalid_delay_policy'), previous: $exception);
        }
        $now = $context->budget->clock->monotonicMs();
        $receivedMs ??= $now;
        if ($delay < 0 || $delay > PHP_INT_MAX - $now || $minimumDelayMs > PHP_INT_MAX - $receivedMs) {
            throw new ConfigurationException(new Message('retry.invalid_delay_policy'));
        }
        return max($now + $delay, $receivedMs + $minimumDelayMs);
    }

    /** Порядок допуска общий для sync/async; после последнего ожидания SDK не уступает управление. */
    private function admitAttempt(
        RequestInterface $request,
        PipelineContext $context,
        int $retryNotBeforeMs,
        int &$additionalWaitMs,
    ): ?ResolvedCooldown {
        $transportOptions = TimeoutResolver::resolve($context);
        TransportCapabilities::check($this->transport, $transportOptions->effective());
        $rule = $this->cooldownResolver->resolve($context);
        $this->cooldown->preflight($rule, $context);
        $this->delayApplier->apply($request, $context->options, $context->budget);
        $this->cooldown->wait($rule, $context, $retryNotBeforeMs, $additionalWaitMs);
        $this->rateLimitApplier->apply($request, $context);
        $this->cooldown->wait($rule, $context, $retryNotBeforeMs, $additionalWaitMs, final: true);
        $this->assertPreparedRequest($context);
        $context->budget->check('before_http');
        $transportOptions = $transportOptions->effective();
        TransportCapabilities::check($this->transport, $transportOptions);
        $context->preparedRequest = $context->preparedRequest->with(transportOptions: $transportOptions);
        return $rule;
    }

    private function assertPreparedRequest(PipelineContext $context): void
    {
        DestinationGuard::checkContext($context);
        FileTransferGuard::checkContext($context);
        RequestBodyGuard::check($context->preparedRequest);
    }

    /** Фактический ответ фиксируется одинаково при обычном возврате и ошибке записи. */
    private function acceptResponse(
        PipelineContext $context,
        ProviderResponse $response,
        ?ResolvedCooldown $rule,
        RetryAfterDelay $retryAfterDelay,
        int $receivedMs,
    ): int {
        $context->response = $response;
        $context->lastResponse = $response;
        $delayMs = $retryAfterDelay->forResponse($response);
        if ($response->status === 429) {
            $this->cooldown->extend($rule, $delayMs, $receivedMs, $context);
        }
        return $delayMs;
    }

    private function sendAttempt(
        PipelineContext $context,
        ?ResolvedCooldown $rule,
        RetryAfterDelay $retryAfterDelay,
    ): ProviderResponse {
        // Предыдущий ответ не подставляется при сетевом сбое новой попытки.
        $context->response = null;
        $previousTransmission = $context->transmissionState;
        $context->sentAuthTokenVersion = $context->authTokenVersion;
        $context->transmissionState = TransmissionState::Unknown;
        $send = fn (): ProviderResponse => TransportExecution::send($this->transport, $context->preparedRequest);
        try {
            return $context->scope !== null ? $context->scope->http($send, $context->preparedRequest) : $send();
        } catch (Throwable $exception) {
            $exception = TransportExceptionNormalizer::normalize($exception);
            if ($exception instanceof TransportException && $exception->transmissionState === TransmissionState::NotSent) {
                $context->transmissionState = $previousTransmission;
            }
            if ($exception instanceof RecordingException) {
                $context->response = $context->lastResponse = $exception->response;
                try {
                    $context->budget->check('cooldown_publish');
                    $this->acceptResponse($context, $exception->response, $rule, $retryAfterDelay, $context->budget->clock->monotonicMs());
                } catch (ExecutionCancelledException $cancelled) {
                    throw $cancelled;
                } catch (Throwable) {
                    // Вторичный сбой публикации не заменяет recording_failed.
                    $this->auditLogger->log(LogLevel::DEBUG, new Message('rate_limit.cooldown_publication_unconfirmed'), [
                        ...$context->trace->logContext(),
                    ]);
                }
                throw $exception;
            }
            if (!$exception instanceof ExecutionDeadlineException) {
                $context->budget->check('http', $exception);
            }
            throw $exception;
        }
    }
}
