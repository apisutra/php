<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Auth;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Auth\ManagedTokenAuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Execution\ClientExecutorInterface;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Enums\Http\TransmissionState;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Auth\AuthLockLeaseInterface;
use ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestOptionsProviderInterface;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Execution\ExecutionDispatch;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use ApiSutra\Execution\ExecutionErrorFactory;
use ApiSutra\Exceptions\Auth\AuthDependencyException;
use ApiSutra\Exceptions\RateLimiting\CooldownBackendException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Exceptions\Auth\AuthLockBackendException;
use ApiSutra\Exceptions\Auth\AuthRefreshLockTimeoutException;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Timing\ExecutionBudget;
use ApiSutra\VO\Pipeline\PipelineContext;
use Psr\Log\LogLevel;
use Throwable;
use ApiSutra\Exceptions\ControlFlow\AdmissionRefused;
use ApiSutra\Pipeline\Diagnostics\ExecutionEnvelope;

/** Координирует обновление через канонический dependency execution. */
final readonly class AuthRefreshCoordinator
{
    private const int REFRESH_LOCK_MIN_TTL_SECONDS = 5;
    private const int REFRESH_LOCK_MAX_TTL_SECONDS = 30;
    private const int REFRESH_LOCK_WAIT_STEP_MS = 50;
    private AuthRefreshLock $refreshLock;

    public function __construct(
        private ClientConfig $config,
        private ClockInterface $clock,
        private SleeperInterface $sleeper,
    ) {
        $this->refreshLock = new AuthRefreshLock($config->cacheConfig?->store, $config->cacheConfig?->locks, $clock);
    }

    public function refreshToken(
        AuthenticatorInterface $auth,
        PipelineContext $context,
        int $attempts,
        bool $forceRefresh,
        string $lockKey,
        ?string $previousVersion,
    ): bool {
        if ($this->hasUpdatedToken($auth, $forceRefresh, $previousVersion)) {
            return true;
        }
        $managed = $auth instanceof ManagedTokenAuthenticatorInterface;
        $refreshRequest = $managed ? null : $auth->getRefreshRequest();
        $context->budget?->check('auth_refresh');
        if ($managed ? !$auth->canRefresh() : $refreshRequest === null) {
            return false;
        }
        $lease = $this->waitForRefreshLock($auth, $lockKey, $forceRefresh, $previousVersion, $context);
        if ($lease === null) {
            return true;
        }
        $failure = null;
        $dispatch = null;
        $result = null;
        try {
            $context->budget?->check('auth_lock_wait');
            // Другой владелец мог обновить токен между проверкой и захватом lease.
            if ($this->hasUpdatedToken($auth, $forceRefresh, $previousVersion)) {
                return true;
            }
            $executor = $context->executor ?? throw new ConfigurationException(new Message('execution.no_client_specified_for_batch_execution'));
            for ($i = 0; $i < $attempts; $i++) {
                $context->budget?->check('auth_refresh');
                if ($managed || $i > 0) {
                    $refreshRequest = $auth->getRefreshRequest();
                    if ($refreshRequest === null) {
                        break;
                    }
                }
                $dispatch = $this->prepareDependency($refreshRequest);
                $result = null;
                $result = $this->executeDependency($executor, $dispatch, $context);
                if ($result->exception instanceof CooldownBackendException) {
                    throw new AuthDependencyException($result);
                }
                if ($result->exception instanceof ExecutionDeadlineException) {
                    throw $result->exception;
                }
                $context->budget?->check('auth_refresh', $result->exception);
                if (!$result->isSuccess()) {
                    continue;
                }
                $this->acceptResponse($auth, $dispatch, $result);
                return true;
            }
            if ($result === null) {
                return false;
            }
            throw new AuthDependencyException($result);
        } catch (Throwable $exception) {
            $failure = $exception;
            if ($auth instanceof ManagedTokenAuthenticatorInterface && $dispatch !== null) {
                $child = $dispatch->context;
                $auth->refreshFailed($child->transmissionState ?? TransmissionState::Unknown, $result->response ?? $child->response ?? null);
            }
            throw $exception;
        } finally {
            try {
                $lease->release();
            } catch (Throwable $exception) {
                if ($failure === null || $failure instanceof AdmissionRefused) {
                    $context->budget?->check('auth_lock_release', $exception);
                    throw new AuthLockBackendException($exception);
                }
                // Вторичная ошибка backend/logger не подменяет исходную причину отказа.
                try {
                    (new AuditLogger($this->config))->log(LogLevel::ERROR, new Message('pipeline.failed_to_release_the_auth_lock'), [
                        ...$context->trace->logContext(),
                        'reason' => 'auth_lock_backend_error',
                    ]);
                } catch (Throwable) {
                }
            }
        }
    }

    private function prepareDependency(RequestInterface $request): ExecutionEnvelope
    {
        $options = $request instanceof RequestOptionsProviderInterface ? $request->getOptions() : RequestOptions::empty();
        // Auth отключён по умолчанию; явная политика зависимости сохраняется одинаково для обеих форм request.
        if ($options->getAuthOverride() === null) {
            $options = $options->withoutAuth();
        }
        return new ExecutionEnvelope($request, options: $options->withPaginationRule(PaginationRule::single()));
    }

    private function executeDependency(
        ClientExecutorInterface $executor,
        ExecutionEnvelope $dispatch,
        PipelineContext $parent,
    ): ExecutionResult {
        $lastResponse = $parent->lastResponse;
        $parent->lastResponse = null;
        try {
            $result = ExecutionDispatch::execute($executor, $dispatch, RequestRole::Dependency, $parent);
        } finally {
            $parent->lastResponse ??= $lastResponse;
        }
        $parent->nested[] = $result;
        return $result;
    }

    private function acceptResponse(AuthenticatorInterface $auth, ExecutionEnvelope $dispatch, ExecutionResult $result): void
    {
        try {
            if (!$result->data instanceof ResponseDtoInterface) {
                throw HydrationException::invalidValue('auth_refresh_dto_required', ResponseDtoInterface::class, get_debug_type($result->data));
            }
            $auth->processTokenResponse($result->data);
        } catch (AdmissionRefused $signal) {
            throw $signal;
        } catch (Throwable $exception) {
            $failure = (new ExecutionErrorFactory($this->config->localization))->buildExceptionResult($dispatch, $exception, $result->response);
            throw new AuthDependencyException($failure);
        }
    }

    private function resolveRefreshLockTtlSeconds(): int
    {
        return max(self::REFRESH_LOCK_MIN_TTL_SECONDS, min(self::REFRESH_LOCK_MAX_TTL_SECONDS, $this->config->timeout));
    }

    private function waitForRefreshLock(
        AuthenticatorInterface $auth,
        string $lockKey,
        bool $forceRefresh,
        ?string $previousVersion,
        PipelineContext $context,
    ): ?AuthLockLeaseInterface {
        $ttl = $this->resolveRefreshLockTtlSeconds();
        $maxWaitMs = $ttl * 1000;
        $waitedMs = 0;
        $start = $this->clock->monotonicMs();
        $budget = $context->budget ?? new ExecutionBudget($this->clock);
        while (true) {
            $budget->check('auth_lock_wait');
            if ($this->hasUpdatedToken($auth, $forceRefresh, $previousVersion)) {
                $budget->check('auth_lock_wait');
                return null;
            }
            $budget->check('auth_lock_wait');
            try {
                $lease = ($auth instanceof ManagedTokenAuthenticatorInterface && ($provider = $auth->refreshLockProvider()) !== null)
                    ? $provider->acquire($lockKey, $ttl)
                    : $this->refreshLock->acquireLease($lockKey, $ttl);
            } catch (Throwable $exception) {
                $budget->check('auth_lock_wait', $exception);
                throw new AuthLockBackendException($exception);
            }
            if ($lease !== null) {
                return $lease;
            }
            $budget->check('auth_lock_wait');
            $waitedMs = max($waitedMs, $this->clock->monotonicMs() - $start);
            if ($waitedMs >= $maxWaitMs) {
                throw new AuthRefreshLockTimeoutException();
            }
            $delay = min(self::REFRESH_LOCK_WAIT_STEP_MS, $maxWaitMs - $waitedMs);
            $budget->wait($delay, $this->sleeper, 'auth_lock_wait');
            $waitedMs += $delay;
        }
    }

    /** @phpstan-impure Чтение общего store может увидеть обновление другого владельца. */
    private function hasUpdatedToken(AuthenticatorInterface $auth, bool $forceRefresh, ?string $previousVersion): bool
    {
        if ($auth instanceof ManagedTokenAuthenticatorInterface) {
            $auth->reloadToken();
            return !$auth->shouldRefresh() && (!$forceRefresh || $auth->tokenVersion() !== $previousVersion);
        }
        return !$forceRefresh && !$auth->shouldRefresh();
    }
}
