<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Auth;

use ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\Cache\CacheAwareInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use ApiSutra\VO\Http\PreparedRequest;
use Psr\SimpleCache\CacheInterface;

final class RecordingAuthenticator implements AuthenticatorInterface, CacheAwareInterface
{
    public static int $authenticateCalls = 0;
    public static ?CacheInterface $cache = null;

    public static function reset(): void
    {
        self::$authenticateCalls = 0;
        self::$cache = null;
    }

    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        self::$authenticateCalls++;

        return $request->withHeader('X-Auth', 'token');
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

    public function setCache(CacheInterface $cache): void
    {
        self::$cache = $cache;
    }

    public function getCacheKey(): string
    {
        return 'auth';
    }
}
