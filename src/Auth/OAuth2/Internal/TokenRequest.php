<?php

declare(strict_types=1);

namespace ApiSutra\Auth\OAuth2\Internal;

use ApiSutra\Attributes\Behavior\Cache;
use ApiSutra\Attributes\Behavior\NoAuth;
use ApiSutra\Attributes\Behavior\SkipContinuation;
use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\SkipCredentialsEnrichment;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Auth\OAuth2\ClientAuthentication;
use ApiSutra\Auth\OAuth2\OAuth2TokenSet;
use ApiSutra\Config\OAuth2Config;
use ApiSutra\Contracts\Interfaces\Concurrency\RetrySafetyPolicyInterface;
use ApiSutra\Contracts\Interfaces\Diagnostics\SensitiveFieldsProviderInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Cache\CacheMode;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Request\RateLimitException;
use ApiSutra\Exceptions\Request\RequestException;
use ApiSutra\Exceptions\Serialization\ResponseDecodingException;
use ApiSutra\Localization\Message;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Retry\RetryAfterDelay;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;
use SensitiveParameter;
use Throwable;

/** @internal Общий form request для grants; HTTP исполняет существующий pipeline. */
#[Post('/')]
#[NoAuth]
#[Cache(mode: CacheMode::Disabled)]
#[SkipCredentialsEnrichment]
#[SkipContinuation]
#[Returns(OAuth2TokenSet::class, hydrator: false)]
final class TokenRequest extends AbstractRequest implements RetrySafetyPolicyInterface, SensitiveFieldsProviderInterface
{
    /** @param array<string, string> $parameters
     * @param list<string>|null $scopes
     */
    public function __construct(
        private readonly OAuth2Config $config,
        private readonly string $grantType,
        #[SensitiveParameter] private readonly array $parameters = [],
        private readonly ?array $scopes = null,
    ) {
    }

    public function getOptions(): RequestOptions
    {
        return parent::getOptions()->withUrl($this->config->tokenUrl)->withoutAuth()->withoutCache()
            ->withoutCredentialsEnrichment()->withRequestEnrichers(false)->withPaginationRule(PaginationRule::single())->withRawResponse(false);
    }

    protected function currentOptions(): RequestOptions
    {
        return $this->getOptions();
    }

    /** @return list<string>|null */
    public function requestedScopes(): ?array
    {
        return $this->scopes;
    }

    public function isRetrySafe(HttpMethod $method, ?ProviderResponse $response, ?Throwable $exception): bool
    {
        return $this->grantType === 'client_credentials';
    }

    /** @return list<string> */
    public function sensitiveFields(): array
    {
        return ['code', 'code_verifier', 'state', 'access_token', 'refresh_token', 'client_secret',
            'accessToken', 'refreshToken', 'codeVerifier', 'error_description', ...array_keys($this->config->tokenParameters)];
    }

    protected function beforeSend(PipelineContext $context): void
    {
        $options = $context->options ?? $this->getOptions();
        if ($this->grantType !== 'client_credentials' && ($options->getRetryOverride()['attempts'] ?? 1) > 1) {
            AuthorizationParameters::invalid('one_time_grant_retry');
        }
        // Прямые runtime overrides не должны превращать служебный обмен в обычный API-запрос.
        if (
            $options->getUrlOverride() !== $this->config->tokenUrl || $options->getRawResponseOverride() === true
            || $options->getCredentialsEnrichmentEnabledOverride() === true || $options->getRequestEnrichersEnabledOverride() === true
        ) {
            AuthorizationParameters::invalid('token_request_options');
        }
        $parameters = [...$this->config->tokenParameters, ...$this->parameters, 'grant_type' => $this->grantType];
        if ($this->grantType !== 'authorization_code' && $this->scopes !== null && $this->scopes !== []) {
            $parameters['scope'] = implode(' ', $this->scopes);
        }
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'];
        if ($this->config->clientAuthentication === ClientAuthentication::Basic) {
            $headers['Authorization'] = 'Basic ' . base64_encode(urlencode($this->config->clientId) . ':' . urlencode($this->config->clientSecret ?? ''));
        } else {
            $parameters['client_id'] = $this->config->clientId;
            if ($this->config->clientAuthentication === ClientAuthentication::Post) {
                $parameters['client_secret'] = $this->config->clientSecret ?? '';
            }
        }
        $prepared = $context->preparedRequest;
        if ($prepared !== null) {
            $context->preparedRequest = $prepared->with(
                headers: $headers,
                body: http_build_query($parameters, '', '&', PHP_QUERY_RFC1738),
                meta: [...$prepared->meta, 'body' => $parameters]
            );
        }
    }

    protected function hasRequestFailed(ProviderResponse $response): bool
    {
        if ($response->status < 200 || $response->status >= 300) {
            return true;
        }

        // Формат ответа проверяет политика запроса, а не пользовательский hook.
        $contentType = strtolower(trim(explode(';', $response->header('Content-Type') ?? '')[0]));
        if ($contentType !== 'application/json' && !str_ends_with($contentType, '+json')) {
            throw new ResponseDecodingException(
                new Message('oauth2.token_response_must_be_json'),
                reason: 'unsupported_response_content_type',
            );
        }

        return false;
    }

    protected function getRequestException(ProviderResponse $response): ?Throwable
    {
        if (!$this->hasRequestFailed($response)) {
            return null;
        }
        $message = new Message('oauth2.token_request_failed');
        if ($response->status === 429) {
            $clock = $this->getContext()?->budget?->clock;
            return new RateLimitException($message, $response, (new RetryAfterDelay())->seconds($response->header('Retry-After'), $clock?->unixTime()));
        }
        return new RequestException($message, $response);
    }
}
