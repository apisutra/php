<?php

declare(strict_types=1);

namespace ApiSutra\Auth\OAuth2;

use ApiSutra\Auth\OAuth2\Internal\AuthorizationParameters;
use ApiSutra\Auth\OAuth2\Internal\CredentialRefreshState;
use ApiSutra\Config\OAuth2Config;
use ApiSutra\Contracts\Interfaces\Auth\AuthLockProviderInterface;
use ApiSutra\Contracts\Interfaces\Auth\AuthLockLeaseInterface;
use ApiSutra\Exceptions\Auth\OAuth2Exception;
use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use SensitiveParameter;
use Throwable;

/** Разрешение одного подключения. Долговременным хранением и межпроцессным владением управляет приложение. */
final class OAuth2Credential
{
    public readonly string $identity;
    private OAuth2TokenSet $tokens;
    private readonly ?Closure $onTokensChanged;
    private readonly CredentialRefreshState $refresh;
    private ?string $binding = null;
    private string $version;
    private ?OAuth2FailureReason $failure = null;

    /** @param (callable(OAuth2TokenSet): mixed)|null $onTokensChanged */
    public function __construct(#[SensitiveParameter] OAuth2TokenSet $tokens, ?string $identity = null, ?callable $onTokensChanged = null)
    {
        if ($identity !== null && trim($identity) === '') {
            AuthorizationParameters::invalid('credential_identity');
        }
        $this->tokens = $tokens;
        $this->identity = $identity ?? bin2hex(random_bytes(16));
        $this->version = bin2hex(random_bytes(16));
        $this->refresh = new CredentialRefreshState();
        $this->onTokensChanged = $onTokensChanged === null ? null : Closure::fromCallable($onTokensChanged);
    }

    public function tokens(): OAuth2TokenSet
    {
        return $this->tokens;
    }

    public function tokenVersion(): string
    {
        return $this->version;
    }

    public function failureReason(): ?OAuth2FailureReason
    {
        return $this->failure;
    }

    /** @internal */
    public function bind(OAuth2Config $config): void
    {
        $identity = $config->grantIdentity();
        if ($this->binding !== null && $this->binding !== $identity) {
            AuthorizationParameters::invalid('credential_binding');
        }
        $this->binding = $identity;
    }

    /** @internal */
    public function refreshLockProvider(): AuthLockProviderInterface
    {
        return $this->refresh;
    }

    /** @internal */
    public function isRefreshing(): bool
    {
        return $this->refresh->isRefreshing();
    }

    public function assertUsable(): void
    {
        if ($this->failure !== null) {
            throw new OAuth2Exception($this->failure);
        }
    }

    /** @internal */
    public function block(OAuth2FailureReason $reason): void
    {
        $this->failure ??= $reason;
    }

    /** @internal При ротации отсутствие refresh_token сохраняет прежний секрет. */
    public function acceptRefresh(#[SensitiveParameter] OAuth2TokenSet $tokens): void
    {
        // Coordinator уже владеет credential; повторный захват здесь заблокировал бы владельца.
        $this->acceptTokens($tokens->refreshToken === null ? $tokens->with(refreshToken: $this->tokens->refreshToken) : $tokens);
    }

    /** Повторяет только сохранение уже принятой пары, без token HTTP. */
    public function retryPersistence(): void
    {
        $lease = $this->acquireMutation();
        try {
            $this->persistTokens();
        } finally {
            $lease->release();
        }
    }

    /** Явно устанавливает результат новой авторизации и снимает терминальное состояние. */
    public function replaceAuthorization(#[SensitiveParameter] OAuth2TokenSet $tokens): void
    {
        $lease = $this->acquireMutation();
        try {
            $this->acceptTokens($tokens);
        } finally {
            $lease->release();
        }
    }

    private function acquireMutation(): AuthLockLeaseInterface
    {
        // Локальное владение не истекает по TTL и покрывает весь callback сохранения.
        return $this->refresh->acquire($this->identity, 0)
            ?? AuthorizationParameters::invalid('credential_refresh_in_progress');
    }

    private function acceptTokens(#[SensitiveParameter] OAuth2TokenSet $tokens): void
    {
        $this->tokens = $tokens;
        $this->version = bin2hex(random_bytes(16));
        $this->failure = OAuth2FailureReason::TokenPersistenceFailed;
        $this->persistTokens();
    }

    private function persistTokens(): void
    {
        if ($this->failure !== OAuth2FailureReason::TokenPersistenceFailed) {
            $this->assertUsable();
            return;
        }
        try {
            $result = ($this->onTokensChanged)?->__invoke($this->tokens);
            if ($result instanceof PromiseInterface) {
                throw new OAuth2Exception(OAuth2FailureReason::TokenPersistenceFailed);
            }
            $this->failure = null;
        } catch (Throwable $exception) {
            throw new OAuth2Exception(OAuth2FailureReason::TokenPersistenceFailed, $exception);
        }
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['identity' => $this->identity, 'failure' => $this->failure?->value, 'tokens' => '[REDACTED]'];
    }
}
