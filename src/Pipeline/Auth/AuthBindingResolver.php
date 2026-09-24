<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Auth;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Auth\ManagedTokenAuthenticatorInterface;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use Psr\Log\LogLevel;
use WeakMap;

/** Привязки живут вместе с pipeline; исходный встроенный authenticator не меняется. */
final class AuthBindingResolver
{
    /** @var WeakMap<AuthenticatorInterface, array<string, AuthBinding>> */
    private WeakMap $bindings;
    private readonly string $localIdentity;

    public function __construct(
        private readonly ClientConfig $config,
        private readonly string $provider,
        private readonly ClockInterface $clock,
    ) {
        $this->bindings = new WeakMap();
        $this->localIdentity = bin2hex(random_bytes(16));
    }

    public function resolve(AuthenticatorInterface $auth, ?string $authScope): AuthBinding
    {
        $identity = $auth instanceof ManagedTokenAuthenticatorInterface ? $auth->bindingIdentity()
            : ($auth instanceof CacheIdentityProviderInterface ? $auth->getCacheIdentity() : null);
        $tenant = $this->config->cacheConfig?->identity;
        $tenantIdentity = $tenant === null ? 'default' : $tenant->getCacheIdentity();
        $shared = $identity !== null && trim($identity) !== '' && $tenantIdentity !== null && trim($tenantIdentity) !== '';
        $scope = hash('sha256', serialize([
            'apisutra-auth-scope-v1', $this->provider, $this->config->baseUrl, $auth::class,
            $identity, $authScope, $tenant === null ? null : $tenant::class, $tenantIdentity,
            $shared ? null : [$this->localIdentity, spl_object_id($auth)],
        ]));
        $bindings = $this->bindings[$auth] ?? [];
        if (isset($bindings[$scope])) {
            return $bindings[$scope];
        }
        $store = $shared ? $this->config->cacheConfig?->store : null;
        if (!$shared) {
            (new AuditLogger($this->config))->log(LogLevel::DEBUG, new Message('pipeline.auth_token_cache_uses_local_scope'), [
                'reason' => 'auth_cache_identity_unavailable',
            ]);
        }
        $cache = new AuthTokenCache($store, $scope, $this->clock);
        $binding = new AuthBinding(
            $auth instanceof ManagedTokenAuthenticatorInterface ? $auth->bind(new AuthBindingContext($this->clock, $cache, $scope)) : $auth,
            $cache,
            hash('sha256', serialize(['apisutra-auth-v1', 'refresh-lock', $scope])),
        );
        $bindings[$scope] = $binding;
        $this->bindings[$auth] = $bindings;
        return $binding;
    }
}
