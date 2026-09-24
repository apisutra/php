<?php

declare(strict_types=1);

namespace ApiSutra\Auth\OAuth2\Internal;

use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;
use SensitiveParameter;

/** Проверки конфигурации без значений секретов в диагностике. */
final class AuthorizationParameters
{
    private const array RESERVED = [
        'client_id', 'client_secret', 'grant_type', 'code', 'code_verifier', 'refresh_token',
        'scope', 'state', 'redirect_uri', 'response_type', 'code_challenge', 'code_challenge_method', 'iss',
    ];

    public static function endpoint(string $url): void
    {
        $parts = parse_url($url);
        if (
            !is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['fragment'])
            || preg_match('/[\x00-\x20\x7f]/', $url)
        ) {
            self::invalid('endpoint');
        }
        foreach (explode('&', $parts['query'] ?? '') as $parameter) {
            $name = urldecode(explode('=', $parameter, 2)[0]);
            if (in_array($name, self::RESERVED, true)) {
                self::invalid('endpoint_query');
            }
        }
    }

    /** @param array<array-key, mixed> $parameters */
    public static function extras(#[SensitiveParameter] array $parameters): void
    {
        foreach ($parameters as $key => $value) {
            if (!is_string($key) || $key === '' || !is_string($value) || in_array($key, self::RESERVED, true)) {
                self::invalid('parameters');
            }
        }
    }

    /** Порядок ключей карты не меняет identity; отправляемые параметры остаются исходными.
     * @param array<string, string> $parameters
     * @return array<string, string>
     */
    public static function canonicalParameters(array $parameters): array
    {
        ksort($parameters, SORT_STRING);
        return $parameters;
    }

    /** @param array<array-key, mixed>|null $scopes
     * @return list<string>|null
     */
    public static function scopes(?array $scopes): ?array
    {
        if ($scopes === null) {
            return null;
        }
        if (!array_is_list($scopes)) {
            self::invalid('scopes');
        }
        foreach ($scopes as $scope) {
            if (!is_string($scope) || !preg_match('/^[\x21\x23-\x5b\x5d-\x7e]+$/D', $scope)) {
                self::invalid('scopes');
            }
        }
        $scopes = array_values(array_unique($scopes));
        sort($scopes, SORT_STRING);
        return $scopes;
    }

    public static function pkceChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public static function invalid(string $field): never
    {
        throw new ConfigurationException(new Message('oauth2.invalid_configuration', ['field' => $field]));
    }
}
