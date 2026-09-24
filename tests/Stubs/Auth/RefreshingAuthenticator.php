<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Auth;

use ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use ApiSutra\Tests\Stubs\Requests\RefreshTokenRequest;
use ApiSutra\VO\Http\PreparedRequest;

final class RefreshingAuthenticator implements AuthenticatorInterface, CacheIdentityProviderInterface
{
    public static bool $shouldRefresh = false;
    public static int $refreshCalls = 0;
    public static int $authenticateCalls = 0;
    public static ?string $token = null;

    public function getCacheIdentity(?PreparedRequest $request = null): ?string
    {
        return 'fixture-account';
    }

    public static function reset(): void
    {
        self::$shouldRefresh = false;
        self::$refreshCalls = 0;
        self::$authenticateCalls = 0;
        self::$token = null;
    }

    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        self::$authenticateCalls++;
        $token = self::$token ?? 'token';

        return $request->withHeader('Authorization', 'Bearer ' . $token);
    }

    public function shouldRefresh(): bool
    {
        return self::$shouldRefresh;
    }

    public function getRefreshRequest(): ?RequestInterface
    {
        self::$refreshCalls++;

        return new RefreshTokenRequest();
    }

    public function processTokenResponse(ResponseDtoInterface $response): void
    {
        if (property_exists($response, 'token')) {
            self::$token = (string) $response->token;
        }
        self::$shouldRefresh = false;
    }
}
