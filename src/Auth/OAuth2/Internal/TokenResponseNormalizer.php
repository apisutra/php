<?php

declare(strict_types=1);

namespace ApiSutra\Auth\OAuth2\Internal;

use ApiSutra\Exceptions\Serialization\HydrationException;
use SensitiveParameter;

final class TokenResponseNormalizer
{
    /** @param array<string, mixed> $data
     * @param list<string>|null $requestedScopes
     * @return array<string, mixed>
     */
    public static function normalize(#[SensitiveParameter] array $data, ?array $requestedScopes, int $now): array
    {
        if (array_key_exists('error', $data)) {
            self::invalid('error');
        }
        if (!is_string($data['access_token'] ?? null) || $data['access_token'] === '' || preg_match('/[\x00-\x20\x7f]/', $data['access_token'])) {
            self::invalid('access_token');
        }
        if (!is_string($data['token_type'] ?? null) || strcasecmp($data['token_type'], 'Bearer') !== 0) {
            self::invalid('token_type');
        }
        $expiresAt = $refreshAt = null;
        if (array_key_exists('expires_in', $data)) {
            $ttl = $data['expires_in'];
            if (!is_int($ttl) || $ttl <= 0 || $ttl > PHP_INT_MAX - $now) {
                self::invalid('expires_in');
            }
            $expiresAt = $now + $ttl;
            $refreshAt = $expiresAt - min(30, intdiv($ttl, 10));
        }
        if (array_key_exists('refresh_token', $data) && (!is_string($data['refresh_token']) || $data['refresh_token'] === '')) {
            self::invalid('refresh_token');
        }
        $scopes = $requestedScopes;
        if (array_key_exists('scope', $data)) {
            if (!is_string($data['scope']) || ($data['scope'] !== '' && !preg_match('/^[\x21\x23-\x5b\x5d-\x7e]+(?: [\x21\x23-\x5b\x5d-\x7e]+)*$/D', $data['scope']))) {
                self::invalid('scope');
            }
            $scopes = $data['scope'] === '' ? [] : explode(' ', $data['scope']);
        }
        return ['accessToken' => $data['access_token'], 'refreshToken' => $data['refresh_token'] ?? null,
            'expiresAt' => $expiresAt, 'refreshAt' => $refreshAt, 'scopes' => AuthorizationParameters::scopes($scopes)];
    }

    private static function invalid(string $field): never
    {
        throw HydrationException::invalidValue('oauth2_invalid_token_response', 'valid OAuth2 ' . $field, 'invalid', '$.' . $field);
    }
}
