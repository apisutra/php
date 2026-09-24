<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Auth;

use ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\Cache\CacheAwareInterface;
use ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use ApiSutra\VO\Http\PreparedRequest;
use Psr\SimpleCache\CacheInterface;

final class TokenCacheProbeAuthenticator implements AuthenticatorInterface, CacheAwareInterface, CacheIdentityProviderInterface
{
    public ?CacheInterface $cache = null;

    public function __construct(private ?string $identity)
    {
    }

    public function getCacheIdentity(?PreparedRequest $request = null): ?string
    {
        return $this->identity;
    }

    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    public function getCacheKey(): string
    {
        return 'fixture:logical-token';
    }

    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        return $request->withHeader('Authorization', 'Bearer ' . ($this->cache?->get($this->getCacheKey()) ?? 'empty'));
    }

    public function shouldRefresh(): bool
    {
        return false;
    }

    public function getRefreshRequest(): ?RequestInterface
    {
        return null;
    }

    public function processTokenResponse(ResponseDtoInterface $response): void
    {
    }
}
