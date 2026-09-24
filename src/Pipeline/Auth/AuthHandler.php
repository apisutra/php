<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Auth;

use ApiSutra\Exceptions\ControlFlow\ExecutionCancelledException;
use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Auth\ManagedTokenAuthenticatorInterface;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Auth\AuthPolicyInterface;
use ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\Cache\CacheAwareInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Exceptions\ControlFlow\ExecutorContractViolation;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Exceptions\Auth\AuthDependencyException;
use ApiSutra\Exceptions\Auth\AuthRefreshFailedException;
use ApiSutra\Enums\Auth\AuthOverride;
use ApiSutra\Exceptions\Auth\AuthLockBackendException;
use ApiSutra\Exceptions\Auth\AuthRefreshLockTimeoutException;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Http\DestinationGuard;
use ApiSutra\Timing\SystemClock;
use ApiSutra\Timing\CooperativeSleeper;
use ApiSutra\VO\Pipeline\PipelineContext;
use Throwable;
use ApiSutra\Exceptions\ControlFlow\AdmissionRefused;

/**
 * Обработчик авторизации запроса.
 *
 * Нюансы:
 * - forceAuth/forceAuthScope пробивает #[NoAuth].
 * - порядок: forceAuth/forceAuthScope → #[NoAuth]/withoutAuth → runtime scope override → AuthScope → withAuth → AuthPolicy → default auth.
 */
final readonly class AuthHandler
{
    private AuthRefreshCoordinator $refresh;
    private AuthBindingResolver $bindings;

    public function __construct(
        private ClientConfig $config,
        ?SleeperInterface $sleeper = null,
        ?ClockInterface $clock = null,
        string $provider = self::class,
    ) {
        $clock ??= new SystemClock();
        $sleeper ??= new CooperativeSleeper();
        $this->bindings = new AuthBindingResolver($config, $provider, $clock);
        $this->refresh = new AuthRefreshCoordinator($config, $clock, $sleeper);
    }

    public function handleAuthentication(RequestInterface $request, PipelineContext $context, bool $forceRefresh = false): void
    {
        $this->authenticate($request, $context, $forceRefresh);
    }

    /** Разрешает повтор только после фактического восстановления credentials. */
    public function recoverAuthentication(RequestInterface $request, PipelineContext $context): bool
    {
        try {
            return $this->authenticate($request, $context, true);
        } catch (AdmissionRefused | ExecutionDeadlineException | AuthRefreshLockTimeoutException | AuthLockBackendException | ExecutorContractViolation | ExecutionCancelledException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            if ($context->response === null) {
                throw $exception;
            }
            throw new AuthRefreshFailedException(
                $context->response,
                $exception instanceof AuthDependencyException ? $exception->dependencyResult : null,
                $exception,
            );
        }
    }

    private function authenticate(RequestInterface $request, PipelineContext $context, bool $forceRefresh): bool
    {
        DestinationGuard::checkContext($context);
        $context->budget?->check('authentication');
        if (!$request instanceof AbstractRequest) {
            return false;
        }

        if ($this->shouldSkipAuth($request, $context)) {
            return false;
        }

        $auth = $this->resolveAuthenticator($request, $context);
        if ($auth === null) {
            return false;
        }

        $binding = $this->bindings->resolve($auth, $this->resolveAuthScopeOverride($request, $context) ?? $request->getAuthScope());
        $auth = $binding->auth;
        $previousVersion = $auth instanceof ManagedTokenAuthenticatorInterface
            ? ($context->sentAuthTokenVersion ?? $this->sentTokenVersion($context) ?? $auth->tokenVersion()) : null;
        if ($auth instanceof CacheAwareInterface) {
            $auth->setCache($binding->cache);
        }

        $shouldRefresh = $forceRefresh || $auth->shouldRefresh();
        $refreshAttempts = max(0, $this->config->authRetryAttempts);
        if ($forceRefresh) {
            $refreshAttempts = min(1, $refreshAttempts);
        }

        if ($auth instanceof ManagedTokenAuthenticatorInterface) {
            $refreshAttempts = $auth->refreshAttempts($refreshAttempts);
        }
        $recovered = false;
        $context->budget?->check('authentication');
        if ($shouldRefresh && $refreshAttempts > 0) {
            $recovered = $this->refresh->refreshToken($auth, $context, $refreshAttempts, $forceRefresh, $binding->lockKey, $previousVersion);
        }

        if ($forceRefresh && !$recovered) {
            return false;
        }
        $context->budget?->check('authentication');
        if ($context->preparedRequest !== null) {
            $context->preparedRequest = $auth->authenticate($context->preparedRequest);
            $context->authTokenVersion = $auth instanceof ManagedTokenAuthenticatorInterface ? $auth->tokenVersion() : null;
            DestinationGuard::checkContext($context);
            $context->budget?->check('authentication');
        }
        return $recovered;
    }

    /** Выбор auth без refresh, внедрения store и вызова authenticate. */
    public function resolveForRequest(RequestInterface $request, PipelineContext $context): ?AuthenticatorInterface
    {
        if (!$request instanceof AbstractRequest || $this->shouldSkipAuth($request, $context)) {
            return null;
        }

        return $this->resolveAuthenticator($request, $context);
    }

    private function shouldSkipAuth(AbstractRequest $request, PipelineContext $context): bool
    {
        $override = $this->resolveAuthOverride($request, $context);
        if ($context->destination?->requiresIsolation()) {
            $explicit = in_array($override, [AuthOverride::Enable, AuthOverride::ForceEnable], true);
            if (!$explicit) {
                return true;
            }
            $context->destination->assertCredentialsAllowed($this->config->originPolicy);
        }
        if ($override === AuthOverride::ForceEnable) {
            return false;
        }

        if ($request->hasNoAuth()) {
            return true;
        }

        if ($override === AuthOverride::Disable) {
            return true;
        }

        return false;
    }

    private function resolveAuthenticator(AbstractRequest $request, PipelineContext $context): ?AuthenticatorInterface
    {
        $scopeOverride = $this->resolveAuthScopeOverride($request, $context);
        if ($scopeOverride !== null) {
            return $this->resolveAuthScope($scopeOverride);
        }

        $scope = $request->getAuthScope();
        if ($scope !== null) {
            return $this->resolveAuthScope($scope);
        }

        $authOverride = $this->resolveAuthOverride($request, $context);
        if ($authOverride === AuthOverride::Enable || $authOverride === AuthOverride::ForceEnable) {
            if ($this->config->auth === null) {
                throw new ConfigurationException(new Message('pipeline.auth_is_enabled_but_not_configured'));
            }

            return $this->config->auth;
        }

        $policy = $this->config->authPolicy;
        if ($policy !== null && !$this->isAllowedByPolicy($policy, $request)) {
            return null;
        }

        if ($policy !== null && $this->config->auth === null) {
            throw new ConfigurationException(new Message('pipeline.authpolicy_is_set_but_auth_is_not_configured'));
        }

        return $this->config->auth;
    }

    private function resolveAuthScopeOverride(AbstractRequest $request, PipelineContext $context): ?string
    {
        if ($context->options !== null) {
            // Execution содержит полный снимок опций, включая явный сброс scope.
            return $context->options->getAuthScopeOverride();
        }

        return $request->getAuthScopeOverride();
    }

    private function resolveAuthOverride(AbstractRequest $request, PipelineContext $context): ?AuthOverride
    {
        $contextOverride = $context->options?->getAuthOverride();
        if ($contextOverride !== null) {
            return $contextOverride;
        }

        return $request->getAuthOverride();
    }

    private function resolveAuthScope(string $scope): AuthenticatorInterface
    {
        $auth = $this->config->authScopes[$scope] ?? null;
        if ($auth === null) {
            throw new ConfigurationException(new Message('pipeline.auth_scope_not_found_in_config', ['scope' => $scope]));
        }

        return $auth;
    }

    private function isAllowedByPolicy(AuthPolicyInterface $policy, RequestInterface $request): bool
    {
        foreach ($policy->allowedRequests() as $class) {
            if ($request instanceof $class) {
                return true;
            }
        }

        return false;
    }

    private function sentTokenVersion(PipelineContext $context): ?string
    {
        $prepared = $context->response->request ?? $context->preparedRequest;
        foreach ($prepared->headers ?? [] as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0 && str_starts_with($value, 'Bearer ')) {
                return hash('sha256', substr($value, 7));
            }
        }
        return null;
    }
}
