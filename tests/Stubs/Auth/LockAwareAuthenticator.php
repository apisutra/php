<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Auth;

use ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\Cache\CacheAwareInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use ApiSutra\Tests\Stubs\Requests\RefreshTokenRequest;
use ApiSutra\VO\Http\PreparedRequest;
use Psr\SimpleCache\CacheInterface;

final class LockAwareAuthenticator implements AuthenticatorInterface, CacheAwareInterface
{
    public int $shouldRefreshCalls = 0;
    public int $refreshCalls = 0;
    public int $authenticateCalls = 0;
    public ?CacheInterface $cache = null;
    private bool $refreshNeeded = true;

    public function __construct(
        private string $cacheKey = 'auth-lock-test',
        private bool $stopAfterFirstCheck = false,
    ) {}

    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        $this->authenticateCalls++;

        return $request;
    }

    public function shouldRefresh(): bool
    {
        $this->shouldRefreshCalls++;
        if ($this->stopAfterFirstCheck && $this->shouldRefreshCalls > 1) {
            $this->refreshNeeded = false;
        }

        return $this->refreshNeeded;
    }

    public function getRefreshRequest(): ?RequestInterface
    {
        $this->refreshCalls++;

        return new RefreshTokenRequest();
    }

    public function processTokenResponse(ResponseDtoInterface $response): void
    {
        $this->refreshNeeded = false;
    }

    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    public function getCacheKey(): string
    {
        return $this->cacheKey;
    }
}
