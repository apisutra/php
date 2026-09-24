<?php

declare(strict_types=1);

namespace ApiSutra\Auth\OAuth2;

use ApiSutra\Auth\CacheCredentialIdentity;
use ApiSutra\Auth\OAuth2\Internal\AuthorizationParameters;
use ApiSutra\Auth\OAuth2\Internal\TokenRequest;
use ApiSutra\Config\OAuth2Config;
use ApiSutra\Contracts\Interfaces\Auth\AuthLockProviderInterface;
use ApiSutra\Contracts\Interfaces\Auth\ManagedTokenAuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use ApiSutra\Enums\Http\TransmissionState;
use ApiSutra\Exceptions\Auth\OAuth2Exception;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Pipeline\Auth\AuthBindingContext;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;

final class OAuth2Authenticator implements ManagedTokenAuthenticatorInterface, CacheIdentityProviderInterface
{
    private ?AuthBindingContext $binding = null;
    private ?OAuth2TokenSet $tokens = null;
    private ?string $version = null;

    private function __construct(private readonly OAuth2Config $config, private readonly ?OAuth2Credential $credential)
    {
        $credential?->bind($config);
    }

    public static function clientCredentials(OAuth2Config $config): self
    {
        if ($config->clientAuthentication === ClientAuthentication::None || $config->clientSecret === null) {
            AuthorizationParameters::invalid('client_credentials');
        }
        return new self($config, null);
    }

    public static function authorizationCode(OAuth2Config $config, OAuth2Credential $credential): self
    {
        return new self($config, $credential);
    }

    public function bind(AuthBindingContext $context): ManagedTokenAuthenticatorInterface
    {
        $bound = new self($this->config, $this->credential);
        $bound->binding = $context;
        return $bound;
    }

    public function bindingIdentity(): string
    {
        return hash('sha256', serialize([$this->config->grantIdentity(), $this->credential?->identity, $this->credential === null ? $this->config->clientSecret : null]));
    }

    public function getCacheIdentity(?PreparedRequest $request = null): string
    {
        return CacheCredentialIdentity::forRequest($this->bindingIdentity(), $request, ['Authorization']);
    }

    public function tokenVersion(): ?string
    {
        return $this->credential?->tokenVersion() ?? $this->version;
    }

    public function reloadToken(): void
    {
        if ($this->credential !== null || $this->binding === null) {
            return;
        }
        $snapshot = $this->binding->cache->get('oauth2_client_credentials');
        if (is_array($snapshot) && is_array($snapshot['tokens'] ?? null) && is_string($snapshot['version'] ?? null)) {
            $this->tokens = OAuth2TokenSet::restore($snapshot['tokens']);
            $this->version = $snapshot['version'];
        }
    }

    public function shouldRefresh(): bool
    {
        if ($this->credential?->isRefreshing()) {
            return true;
        }
        $this->credential?->assertUsable();
        $tokens = $this->currentTokens();
        return $tokens === null || $tokens->isExpired($this->now());
    }

    public function canRefresh(): bool
    {
        if ($this->credential?->isRefreshing()) {
            return true;
        }
        $this->credential?->assertUsable();
        if ($this->credential !== null && $this->credential->tokens()->refreshToken === null) {
            $this->credential->block(OAuth2FailureReason::AuthorizationRequired);
            throw new OAuth2Exception(OAuth2FailureReason::AuthorizationRequired);
        }
        return true;
    }

    public function refreshAttempts(int $configured): int
    {
        return min(1, $configured);
    }

    public function refreshLockProvider(): ?AuthLockProviderInterface
    {
        return $this->credential?->refreshLockProvider();
    }

    public function getRefreshRequest(): ?RequestInterface
    {
        $this->credential?->assertUsable();
        if ($this->credential === null) {
            return new TokenRequest($this->config, 'client_credentials', scopes: $this->config->scopes);
        }
        $tokens = $this->credential->tokens();
        if ($tokens->refreshToken === null) {
            $this->canRefresh();
            return null;
        }
        return new TokenRequest($this->config, 'refresh_token', ['refresh_token' => $tokens->refreshToken], $tokens->scopes);
    }

    public function processTokenResponse(ResponseDtoInterface $response): void
    {
        if (!$response instanceof OAuth2TokenSet) {
            throw HydrationException::invalidValue('oauth2_invalid_token_response', OAuth2TokenSet::class, get_debug_type($response));
        }
        if ($this->credential !== null) {
            $this->credential->acceptRefresh($response);
            return;
        }
        $this->tokens = $response;
        $this->version = bin2hex(random_bytes(16));
        $ttl = $response->expiresAt === null ? null : max(1, $response->expiresAt - $this->now());
        $this->binding?->cache->set('oauth2_client_credentials', ['tokens' => $response->export(), 'version' => $this->version], $ttl);
    }

    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        $this->credential?->assertUsable();
        $tokens = $this->currentTokens();
        if ($tokens === null || $tokens->isExpired($this->now())) {
            throw new OAuth2Exception(OAuth2FailureReason::AuthorizationRequired);
        }
        $request = $request->withHeader('Authorization', 'Bearer ' . $tokens->accessToken);
        $identity = hash('sha256', serialize([$this->bindingIdentity(), $tokens->scopes]));
        return CacheCredentialIdentity::stamp($request, 'Authorization', $identity);
    }

    public function refreshFailed(TransmissionState $transmission, ?ProviderResponse $response): void
    {
        if ($this->credential === null || $this->credential->failureReason() !== null) {
            return;
        }
        $data = $response?->json();
        if (is_array($data) && ($data['error'] ?? null) === 'invalid_grant') {
            $this->credential->block(OAuth2FailureReason::AuthorizationRequired);
        } elseif ($transmission !== TransmissionState::NotSent) {
            // Даже 5xx не доказывает, что одноразовый секрет не был потреблён.
            $this->credential->block(OAuth2FailureReason::RefreshOutcomeUnknown);
        }
    }

    private function currentTokens(): ?OAuth2TokenSet
    {
        return $this->credential?->tokens() ?? $this->tokens;
    }

    private function now(): int
    {
        return $this->binding === null ? time() : $this->binding->clock->unixTime();
    }
}
