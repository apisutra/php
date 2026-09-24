<?php

declare(strict_types=1);

namespace ApiSutra\Auth;

use ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use ApiSutra\VO\Http\PreparedRequest;
use Override;

final readonly class BasicAuthenticator implements AuthenticatorInterface, CacheIdentityProviderInterface
{
    public function __construct(
        private string $username,
        private string $password,
    ) {
    }

    #[Override]
    public function getCacheIdentity(?PreparedRequest $request = null): ?string
    {
        return CacheCredentialIdentity::forRequest(
            hash('sha256', serialize([self::class, $this->username, $this->password])),
            $request,
            ['Authorization'],
        );
    }

    #[Override]
    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        $token = base64_encode($this->username . ':' . $this->password);
        return $request->withHeader('Authorization', 'Basic ' . $token);
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
