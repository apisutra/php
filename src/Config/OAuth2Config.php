<?php

declare(strict_types=1);

namespace ApiSutra\Config;

use ApiSutra\Auth\OAuth2\ClientAuthentication;
use ApiSutra\Auth\OAuth2\Internal\AuthorizationParameters;
use SensitiveParameter;

final readonly class OAuth2Config
{
    public ClientAuthentication $clientAuthentication;
    /** @var list<string>|null */
    public ?array $scopes;

    /** @param list<string>|null $scopes
     * @param array<string, string> $tokenParameters
     */
    public function __construct(
        public string $tokenUrl,
        public string $clientId,
        #[SensitiveParameter] public ?string $clientSecret = null,
        ?array $scopes = null,
        ?ClientAuthentication $clientAuthentication = null,
        public array $tokenParameters = [],
    ) {
        AuthorizationParameters::endpoint($tokenUrl);
        if ($clientId === '' || $clientSecret === '') {
            AuthorizationParameters::invalid('client_credentials');
        }
        $this->clientAuthentication = $clientAuthentication ?? ClientAuthentication::Basic;
        if (($this->clientAuthentication === ClientAuthentication::None) !== ($clientSecret === null)) {
            AuthorizationParameters::invalid('client_authentication');
        }
        $this->scopes = AuthorizationParameters::scopes($scopes);
        AuthorizationParameters::extras($tokenParameters);
    }

    /** Не содержит токенов; отделяет несовместимые контексты одного разрешения. */
    public function grantIdentity(): string
    {
        return hash('sha256', serialize([
            $this->tokenUrl, $this->clientId, $this->clientAuthentication, $this->scopes,
            AuthorizationParameters::canonicalParameters($this->tokenParameters),
        ]));
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['tokenUrl' => $this->tokenUrl, 'clientId' => $this->clientId, 'clientSecret' => '[REDACTED]'];
    }
}
