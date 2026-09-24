<?php

declare(strict_types=1);

namespace ApiSutra\Auth;

use ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use ApiSutra\VO\Http\PreparedRequest;
use Override;

final readonly class HmacAuthenticator implements AuthenticatorInterface, CacheIdentityProviderInterface
{
    public function __construct(
        private string $apiKey,
        private string $secret,
    ) {
    }

    #[Override]
    public function getCacheIdentity(?PreparedRequest $request = null): ?string
    {
        return CacheCredentialIdentity::forRequest(
            hash('sha256', serialize([self::class, $this->apiKey, $this->secret])),
            $request,
            ['X-Api-Key'],
        );
    }

    #[Override]
    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        $timestamp = time();
        $signature = $this->sign($request, $timestamp);

        return $request
            ->withHeader('X-Api-Key', $this->apiKey)
            ->withHeader('X-Timestamp', (string) $timestamp)
            ->withHeader('X-Signature', $signature);
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

    private function sign(PreparedRequest $request, int $timestamp): string
    {
        $data = implode("\n", [
            $request->method->value,
            $request->url,
            $request->body ?? '',
            $timestamp,
        ]);

        return hash_hmac('sha256', $data, $this->secret);
    }
}
