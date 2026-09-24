<?php

declare(strict_types=1);

namespace ApiSutra\Auth\OAuth2;

use ApiSutra\Auth\OAuth2\Internal\AuthorizationParameters;
use ApiSutra\Auth\OAuth2\Internal\TokenRequest;
use ApiSutra\Auth\OAuth2\Internal\TokenResponseNormalizer;
use ApiSutra\DataTransfer\AbstractResponseDto;
use ApiSutra\VO\Pipeline\PipelineContext;
use SensitiveParameter;

final readonly class OAuth2TokenSet extends AbstractResponseDto
{
    /** @var list<string>|null */
    public ?array $scopes;

    /** @param list<string>|null $scopes */
    public function __construct(
        #[SensitiveParameter] public string $accessToken,
        #[SensitiveParameter] public ?string $refreshToken = null,
        public ?int $expiresAt = null,
        ?array $scopes = null,
        public ?int $refreshAt = null,
    ) {
        if (
            $accessToken === '' || preg_match('/[\x00-\x20\x7f]/', $accessToken) || $refreshToken === ''
            || ($expiresAt !== null && $expiresAt < 0)
            || ($refreshAt !== null && ($expiresAt === null || $refreshAt < 0 || $refreshAt > $expiresAt))
        ) {
            AuthorizationParameters::invalid('token_set');
        }
        $this->scopes = AuthorizationParameters::scopes($scopes);
    }

    public static function computed(array $data, ?PipelineContext $context = null): array
    {
        if ($context?->request instanceof TokenRequest) {
            return TokenResponseNormalizer::normalize($data, $context->request->requestedScopes(), $context->budget?->clock->unixTime() ?? time());
        }
        return $data;
    }

    public function isExpired(int $now): bool
    {
        return ($this->refreshAt ?? $this->expiresAt) !== null && $now >= ($this->refreshAt ?? $this->expiresAt);
    }

    /** Формат хранения содержит секреты и не предназначен для логов.
     * @return array{version: int, accessToken: string, refreshToken: ?string, expiresAt: ?int, refreshAt: ?int, scopes: ?list<string>}
     */
    public function export(): array
    {
        return ['version' => 1, 'accessToken' => $this->accessToken, 'refreshToken' => $this->refreshToken,
            'expiresAt' => $this->expiresAt, 'refreshAt' => $this->refreshAt, 'scopes' => $this->scopes];
    }

    /** @param array<string, mixed> $snapshot */
    public static function restore(#[SensitiveParameter] array $snapshot): self
    {
        if (
            ($snapshot['version'] ?? null) !== 1 || !is_string($snapshot['accessToken'] ?? null)
            || !array_key_exists('refreshToken', $snapshot) || (!is_string($snapshot['refreshToken']) && $snapshot['refreshToken'] !== null)
            || !array_key_exists('expiresAt', $snapshot) || (!is_int($snapshot['expiresAt']) && $snapshot['expiresAt'] !== null)
            || !array_key_exists('refreshAt', $snapshot) || (!is_int($snapshot['refreshAt']) && $snapshot['refreshAt'] !== null)
            || !array_key_exists('scopes', $snapshot) || (!is_array($snapshot['scopes']) && $snapshot['scopes'] !== null)
        ) {
            AuthorizationParameters::invalid('token_snapshot');
        }
        return new self($snapshot['accessToken'], $snapshot['refreshToken'], $snapshot['expiresAt'], $snapshot['scopes'], $snapshot['refreshAt']);
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['accessToken' => '[REDACTED]', 'refreshToken' => '[REDACTED]', 'expiresAt' => $this->expiresAt, 'scopes' => $this->scopes];
    }
}
