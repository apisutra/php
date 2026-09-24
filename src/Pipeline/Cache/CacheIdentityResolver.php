<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Cache;

use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Auth\CacheCredentialIdentity;
use ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\VO\Http\PreparedRequest;

/** Автоматические границы доступа отделены от пользовательского логического ключа. */
final readonly class CacheIdentityResolver
{
    public function __construct(private ClientConfig $config, private string $provider)
    {
    }

    public function scope(?AuthenticatorInterface $auth, CacheConfig $cache, string $prefix): ?string
    {
        $identity = $auth === null ? 'anonymous' : $this->provided($auth);
        $tenant = $cache->identity === null ? 'default' : $this->provided($cache->identity);
        if ($identity === null || $tenant === null) {
            return null;
        }

        return hash('sha256', serialize([
            'apisutra-scope-v3', $this->provider, $this->config->baseUrl, $identity, $tenant, $prefix,
        ]));
    }

    public function request(RequestInterface $request): ?string
    {
        return $request instanceof CacheIdentityProviderInterface ? $this->provided($request) : 'default';
    }

    private function provided(object $provider, ?PreparedRequest $request = null): ?string
    {
        if (!$provider instanceof CacheIdentityProviderInterface) {
            return null;
        }
        $identity = $provider->getCacheIdentity($request);
        if ($identity === null || trim($identity) === '') {
            return null;
        }

        return hash('sha256', serialize([$provider::class, $identity]));
    }

    /** Защитные признаки custom key; обычные HTTP-параметры не включаются. */
    public function customGuard(PreparedRequest $prepared, RequestInterface $request, ?AuthenticatorInterface $auth, CacheConfig $cache): ?string
    {
        $authIdentity = $auth === null ? 'anonymous' : $this->provided($auth, $prepared);
        $tenantIdentity = $request instanceof CacheIdentityProviderInterface ? $this->provided($request, $prepared) : 'default';
        $configuredIdentity = $cache->identity === null ? 'default' : $this->provided($cache->identity, $prepared);
        if ($authIdentity === null || $tenantIdentity === null || $configuredIdentity === null) {
            return null;
        }

        $url = parse_url($prepared->url);
        if ($url === false || !isset($url['scheme'], $url['host'])) {
            return null;
        }

        return hash('sha256', serialize([
            $authIdentity, $tenantIdentity, $configuredIdentity,
            strtolower($url['scheme']), strtolower($url['host']), $url['port'] ?? null,
            ...CacheCredentialIdentity::credentialFields($prepared),
        ]));
    }
}
