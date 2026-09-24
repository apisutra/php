<?php

declare(strict_types=1);

namespace ApiSutra\Auth\OAuth2;

use ApiSutra\Auth\OAuth2\Internal\AuthorizationParameters;
use ApiSutra\Auth\OAuth2\Internal\TokenRequest;
use ApiSutra\Config\OAuth2Config;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Exceptions\Auth\OAuth2Exception;
use ApiSutra\Timing\SystemClock;
use SensitiveParameter;

final readonly class AuthorizationCodeFlow
{
    private ClockInterface $clock;
    private string $identity;

    /** @param array<string, string> $authorizationParameters */
    public function __construct(
        private OAuth2Config $config,
        private string $authorizationUrl,
        private string $redirectUri,
        private ?string $expectedIssuer = null,
        private int $attemptTtlSeconds = 600,
        private array $authorizationParameters = [],
        ?ClockInterface $clock = null,
    ) {
        AuthorizationParameters::endpoint($authorizationUrl);
        AuthorizationParameters::endpoint($redirectUri);
        if ($expectedIssuer !== null) {
            AuthorizationParameters::endpoint($expectedIssuer);
        }
        AuthorizationParameters::extras($authorizationParameters);
        $this->clock = $clock ?? new SystemClock();
        if ($attemptTtlSeconds < 1 || $attemptTtlSeconds > PHP_INT_MAX - $this->clock->unixTime()) {
            AuthorizationParameters::invalid('attempt_ttl');
        }
        $this->identity = hash('sha256', serialize([
            $config->grantIdentity(), $authorizationUrl, $redirectUri, $expectedIssuer,
            AuthorizationParameters::canonicalParameters($authorizationParameters),
        ]));
    }

    public function begin(): AuthorizationAttempt
    {
        $state = self::base64Url(random_bytes(32));
        $verifier = self::base64Url(random_bytes(32));
        $parameters = [...$this->authorizationParameters,
            'response_type' => 'code', 'client_id' => $this->config->clientId, 'redirect_uri' => $this->redirectUri,
            'state' => $state, 'code_challenge' => AuthorizationParameters::pkceChallenge($verifier), 'code_challenge_method' => 'S256'];
        if ($this->config->scopes !== null && $this->config->scopes !== []) {
            $parameters['scope'] = implode(' ', $this->config->scopes);
        }
        $url = $this->authorizationUrl . (str_contains($this->authorizationUrl, '?') ? '&' : '?')
            . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
        return new AuthorizationAttempt($url, $state, $verifier, $this->clock->unixTime() + $this->attemptTtlSeconds, $this->identity, $this->config->scopes);
    }

    /** actualCallbackUri — адрес обработчика без OAuth-параметров ответа.
     * @param array<string, mixed> $callbackParameters
     */
    public function exchange(AuthorizationAttempt $attempt, #[SensitiveParameter] array $callbackParameters, string $actualCallbackUri): AbstractRequest
    {
        if (
            $this->clock->unixTime() >= $attempt->expiresAt || !hash_equals($this->identity, $attempt->flowIdentity)
            || $attempt->scopes !== $this->config->scopes || $actualCallbackUri !== $this->redirectUri
        ) {
            $this->invalidCallback();
        }
        foreach ($callbackParameters as $value) {
            if (!is_string($value)) {
                $this->invalidCallback();
            }
        }
        $state = $callbackParameters['state'] ?? null;
        if (
            !is_string($state) || !hash_equals($attempt->state, $state)
            || ($this->expectedIssuer !== null && ($callbackParameters['iss'] ?? null) !== $this->expectedIssuer)
        ) {
            $this->invalidCallback();
        }
        if (
            array_key_exists('error', $callbackParameters) || !is_string($callbackParameters['code'] ?? null)
            || trim($callbackParameters['code']) === ''
        ) {
            $this->invalidCallback();
        }
        return new TokenRequest($this->config, 'authorization_code', [
            'code' => $callbackParameters['code'], 'redirect_uri' => $this->redirectUri, 'code_verifier' => $attempt->codeVerifier,
        ], $attempt->scopes);
    }

    private function invalidCallback(): never
    {
        throw new OAuth2Exception(OAuth2FailureReason::InvalidCallback);
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
