<?php

declare(strict_types=1);

namespace ApiSutra\Auth\OAuth2;

use ApiSutra\Auth\OAuth2\Internal\AuthorizationParameters;
use SensitiveParameter;

/** Серверное состояние одного redirect; приложение связывает его с сессией и погашает атомарно. */
final readonly class AuthorizationAttempt
{
    /** @param list<string>|null $scopes */
    public function __construct(
        public string $url,
        #[SensitiveParameter] public string $state,
        #[SensitiveParameter] public string $codeVerifier,
        public int $expiresAt,
        public string $flowIdentity,
        public ?array $scopes,
    ) {
        AuthorizationParameters::endpoint($url === '' ? '' : explode('?', $url, 2)[0]);
        if (
            !preg_match('/^[A-Za-z0-9_-]{43,128}$/D', $state)
            || !preg_match('/^[A-Za-z0-9._~-]{43,128}$/D', $codeVerifier)
            || !preg_match('/^[a-f0-9]{64}$/D', $flowIdentity) || $expiresAt < 0
            || AuthorizationParameters::scopes($scopes) !== $scopes
        ) {
            AuthorizationParameters::invalid('authorization_attempt');
        }
    }

    /** Содержит verifier: сохранять только на сервере, не в URL, cookie или логах.
     * @return array{version:int, url:string, state:string, codeVerifier:string, expiresAt:int, flowIdentity:string, scopes:?list<string>}
     */
    public function export(): array
    {
        return ['version' => 1, 'url' => $this->url, 'state' => $this->state, 'codeVerifier' => $this->codeVerifier,
            'expiresAt' => $this->expiresAt, 'flowIdentity' => $this->flowIdentity, 'scopes' => $this->scopes];
    }

    /** @param array<string, mixed> $snapshot */
    public static function restore(#[SensitiveParameter] array $snapshot): self
    {
        if (
            ($snapshot['version'] ?? null) !== 1 || !is_int($snapshot['expiresAt'] ?? null)
            || !array_key_exists('scopes', $snapshot) || (!is_array($snapshot['scopes']) && $snapshot['scopes'] !== null)
        ) {
            AuthorizationParameters::invalid('attempt_snapshot');
        }
        foreach (['url', 'state', 'codeVerifier', 'flowIdentity'] as $field) {
            if (!is_string($snapshot[$field] ?? null)) {
                AuthorizationParameters::invalid('attempt_snapshot');
            }
        }
        return new self($snapshot['url'], $snapshot['state'], $snapshot['codeVerifier'], $snapshot['expiresAt'], $snapshot['flowIdentity'], $snapshot['scopes']);
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['state' => '[REDACTED]', 'codeVerifier' => '[REDACTED]', 'expiresAt' => $this->expiresAt];
    }
}
