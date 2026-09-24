<?php

declare(strict_types=1);

namespace ApiSutra\Auth;

use ApiSutra\Contracts\Interfaces\Auth\ManagedTokenAuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\Auth\AuthLockProviderInterface;
use ApiSutra\Pipeline\Auth\AuthBindingContext;
use ApiSutra\Enums\Http\TransmissionState;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\Contracts\Interfaces\Cache\CacheAwareInterface;
use ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Timing\SystemClock;
use ApiSutra\VO\Http\PreparedRequest;
use Override;
use Psr\SimpleCache\CacheInterface;

final class TokenAuthenticator implements ManagedTokenAuthenticatorInterface, CacheIdentityProviderInterface, CacheAwareInterface
{
    private readonly ClockInterface $clock;
    private ?CacheInterface $cache = null;
    private ?string $token = null;
    private ?int $expiresAt = null;

    public function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly int $tokenTtl = 600,
        private readonly ?string $refreshRequestClass = null,
        ?ClockInterface $clock = null,
    ) {
        $this->clock = $clock ?? new SystemClock();
    }

    /** Новая привязка не наследует токен, expiresAt или store другого контекста. */
    public function freshForContext(ClockInterface $clock): self
    {
        return new self($this->username, $this->password, $this->tokenTtl, $this->refreshRequestClass, $clock);
    }

    /** Непрозрачная версия для проверки обновления после 401; в логи не выводится. */
    public function tokenVersion(): ?string
    {
        return $this->token === null ? null : hash('sha256', $this->token);
    }

    public function bind(AuthBindingContext $context): ManagedTokenAuthenticatorInterface
    {
        return $this->freshForContext($context->clock);
    }

    public function bindingIdentity(): string
    {
        return $this->getCacheIdentity();
    }
    public function reloadToken(): void
    {
        $this->loadFromCache();
    }
    public function canRefresh(): bool
    {
        return $this->refreshRequestClass !== null;
    }
    public function refreshAttempts(int $configured): int
    {
        return $configured;
    }
    public function refreshLockProvider(): ?AuthLockProviderInterface
    {
        return null;
    }
    public function refreshFailed(TransmissionState $transmission, ?ProviderResponse $response): void
    {
    }

    #[Override]
    public function getCacheIdentity(?PreparedRequest $request = null): string
    {
        return CacheCredentialIdentity::forRequest(
            hash('sha256', serialize([self::class, $this->username, $this->password, $this->refreshRequestClass])),
            $request,
            ['Authorization'],
        );
    }

    #[Override]
    public function setCache(CacheInterface $cache): void
    {
        if ($this->cache !== $cache) {
            $this->token = null;
            $this->expiresAt = null;
        }
        $this->cache = $cache;
        $this->loadFromCache();
    }

    #[Override]
    public function getCacheKey(): string
    {
        return hash('sha256', serialize(['apisutra-auth-token-v1', $this->getCacheIdentity()]));
    }

    #[Override]
    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        if ($this->token === null) {
            return $request;
        }

        return $request->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Override]
    public function shouldRefresh(): bool
    {
        return $this->token === null || ($this->expiresAt !== null && $this->expiresAt < $this->clock->unixTime() + 30);
    }

    #[Override]
    public function getRefreshRequest(): ?RequestInterface
    {
        if ($this->refreshRequestClass === null) {
            return null;
        }

        return new $this->refreshRequestClass(
            username: $this->username,
            password: $this->password,
        );
    }

    #[Override]
    public function processTokenResponse(ResponseDtoInterface $response): void
    {
        if (property_exists($response, 'accessToken')) {
            $this->token = $response->accessToken;
        }

        if (property_exists($response, 'expiresIn')) {
            $this->expiresAt = $this->clock->unixTime() + (int) $response->expiresIn;
        } else {
            $this->expiresAt = $this->clock->unixTime() + $this->tokenTtl;
        }

        $this->saveToCache();
    }

    public function loadFromCache(): void
    {
        if ($this->cache === null) {
            return;
        }

        $data = $this->cache->get($this->getCacheKey());
        if (is_array($data) && is_string($data['token'] ?? null) && is_int($data['expires_at'] ?? null)) {
            $this->token = $data['token'];
            $this->expiresAt = $data['expires_at'];
        }
    }

    private function saveToCache(): void
    {
        if ($this->cache === null) {
            return;
        }

        $this->cache->set($this->getCacheKey(), [
            'token' => $this->token,
            'expires_at' => $this->expiresAt,
        ], $this->tokenTtl);
    }
}
