<?php

declare(strict_types=1);

namespace ApiSutra\Auth;

use ApiSutra\Auth\Authorization\QuotedCommaFormatter;
use ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\Auth\AuthorizationParamsFormatterInterface;
use ApiSutra\Contracts\Interfaces\Auth\AuthorizationParamsProviderInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use Stringable;
use Override;

final readonly class AuthorizationSchemeAuthenticator implements AuthenticatorInterface, CacheIdentityProviderInterface
{
    /**
     * Параметры схемы Authorization.
     *
     * @param array<string, string|int|float|bool|Stringable|null> $params
     */
    public function __construct(
        private string $scheme,
        private ?string $token = null,
        private array $params = [],
        private ?AuthorizationParamsProviderInterface $provider = null,
        private ?AuthorizationParamsFormatterInterface $formatter = null,
    ) {
    }

    #[Override]
    public function getCacheIdentity(?PreparedRequest $request = null): ?string
    {
        if ($this->token !== null) {
            return CacheCredentialIdentity::forRequest(
                hash('sha256', serialize([self::class, $this->scheme, $this->token])),
                $request,
                ['Authorization'],
            );
        }
        // Динамические параметры и пользовательский formatter могут зависеть от запроса.
        if ($this->provider !== null || $this->formatter !== null) {
            return null;
        }
        foreach ($this->params as $value) {
            if ($value instanceof Stringable) {
                return null;
            }
        }

        return CacheCredentialIdentity::forRequest(
            hash('sha256', serialize([self::class, $this->scheme, $this->params])),
            $request,
            ['Authorization'],
        );
    }

    #[Override]
    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        $header = $this->buildHeaderValue($request);
        if ($header === null) {
            return $request;
        }

        return $request->withHeader('Authorization', $header);
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

    private function buildHeaderValue(PreparedRequest $request): ?string
    {
        if ($this->token !== null) {
            return $this->scheme . ' ' . $this->token;
        }

        $params = $this->resolveParams($request);
        if ($params === []) {
            return null;
        }

        return $this->scheme . ' ' . $this->formatParams($params);
    }

    /**
     * @return array<string, string|int|float|bool|Stringable>
     */
    private function resolveParams(PreparedRequest $request): array
    {
        $params = $this->params;
        if ($this->provider !== null) {
            $params = array_merge($params, $this->provider->resolve($request));
        }

        $filtered = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            $filtered[(string) $key] = $value;
        }

        return $filtered;
    }

    /**
     * @param array<string, scalar|Stringable> $params
     */
    private function formatParams(array $params): string
    {
        $formatter = $this->formatter ?? new QuotedCommaFormatter();
        return $formatter->format($params);
    }
}
