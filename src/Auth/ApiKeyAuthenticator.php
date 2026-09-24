<?php

declare(strict_types=1);

namespace ApiSutra\Auth;

use ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use ApiSutra\Serialization\UrlQuery;
use ApiSutra\VO\Http\PreparedRequest;
use Override;

final readonly class ApiKeyAuthenticator implements AuthenticatorInterface, CacheIdentityProviderInterface
{
    public function __construct(
        private string $key,
        private ?string $header = 'X-Api-Key',
        private ?string $query = null,
    ) {
    }

    #[Override]
    public function getCacheIdentity(?PreparedRequest $request = null): ?string
    {
        return CacheCredentialIdentity::forRequest(
            hash('sha256', serialize([self::class, $this->key, $this->header, $this->query])),
            $request,
            $this->header !== null ? [$this->header] : [],
            $this->header === null ? $this->query : null,
        );
    }

    #[Override]
    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        if ($this->header !== null) {
            return $request->withHeader($this->header, $this->key);
        }

        if ($this->query !== null) {
            [$path, $query] = UrlQuery::split(UrlQuery::append($request->url));
            $parts = $query === '' ? [] : explode('&', $query);
            $meta = $request->meta;
            $previous = $meta['queryAuth'] ?? null;
            $nameHash = hash('sha256', $this->query);
            if (is_array($previous) && ($previous['nameHash'] ?? null) === $nameHash) {
                $index = $previous['index'] ?? null;
                if (
                    is_int($index) && isset($parts[$index])
                    && hash('sha256', $parts[$index]) === ($previous['pairHash'] ?? null)
                ) {
                    // Удаляется только ранее добавленная SDK пара, исходные дубли сохраняются.
                    unset($parts[$index]);
                    $parts = array_values($parts);
                }
            }
            $pair = rawurlencode($this->query) . '=' . rawurlencode($this->key);
            $meta['queryAuth'] = ['nameHash' => $nameHash, 'index' => count($parts), 'pairHash' => hash('sha256', $pair)];
            return $request->with(url: UrlQuery::append($path, implode('&', $parts), $pair), meta: $meta);
        }

        return $request;
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
