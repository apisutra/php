<?php

declare(strict_types=1);

namespace ApiSutra\Auth;

use ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use ApiSutra\VO\Http\PreparedRequest;
use Override;

final readonly class BearerAuthenticator implements AuthenticatorInterface, CacheIdentityProviderInterface
{
    public function __construct(
        private string $token,
    ) {
    }

    #[Override]
    public function getCacheIdentity(?PreparedRequest $request = null): ?string
    {
        return CacheCredentialIdentity::forRequest(
            hash('sha256', serialize([self::class, $this->token])),
            $request,
            ['Authorization'],
        );
    }

    #[Override]
    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        return $request->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Override]
    public function shouldRefresh(): bool
    {
        return false;
    }

    #[Override]
    public function getRefreshRequest(): ?RequestInterface
    {
        return null;
    }

    #[Override]
    public function processTokenResponse(ResponseDtoInterface $response): void
    {
    }
}
