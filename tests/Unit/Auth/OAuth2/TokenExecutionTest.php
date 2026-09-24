<?php

declare(strict_types=1);

use ApiSutra\Auth\OAuth2\AuthorizationCodeFlow;
use ApiSutra\Auth\OAuth2\ClientAuthentication;
use ApiSutra\Auth\OAuth2\Internal\TokenRequest;
use ApiSutra\Auth\OAuth2\OAuth2Authenticator;
use ApiSutra\Auth\OAuth2\OAuth2Credential;
use ApiSutra\Auth\OAuth2\OAuth2TokenSet;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\OAuth2Config;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Exceptions\Auth\OAuth2Exception;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Auth\OAuth2\ResourceRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\ArrayCache;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Http\ProviderResponse;

it('CC получает и переиспользует токен, не смешивая Basic с телом и bearer API', function (bool $async): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => MockResponse::success(['access_token' => 'access', 'token_type' => 'bEaReR', 'expires_in' => 20]), ResourceRequest::class => MockResponse::success(['ok' => true])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', debug: true, auth: OAuth2Authenticator::clientCredentials(new OAuth2Config('https://id.test/token', 'id:a', 's e:c', scopes: ['a']))), $transport, $clock, $clock);
    for ($i = 0; $i < 2; $i++) {
        $result = $async ? $client->sendAsync(new ResourceRequest())->wait()->raw() : $client->send(new ResourceRequest())->raw();
        expect($result->isSuccess())->toBeTrue();
    }
    $sent = $transport->getRecorded();
    expect($sent)->toHaveCount(3)->and($sent[0]->url)->toBe('https://id.test/token')
        ->and($sent[0]->body)->toBe('grant_type=client_credentials&scope=a')
        ->and($sent[0]->headers['Authorization'])->toBe('Basic ' . base64_encode('id%3Aa:s+e%3Ac'))
        ->and($sent[1]->headers['Authorization'])->toBe('Bearer access');
    $clock->advance(21000);
    expect($client->send(new ResourceRequest())->raw()->isSuccess())->toBeTrue();
    expect($transport->getRecorded())->toHaveCount(5);
})->with([false, true]);

it('обменивает code стандартным send и маскирует form/DTO/debug', function (ClientAuthentication $method): void {
    $config = new OAuth2Config('https://id.test/token', 'client', $method === ClientAuthentication::None ? null : 'sensitive-secret', clientAuthentication: $method);
    $flow = new AuthorizationCodeFlow($config, 'https://id.test/authorize', 'https://app.test/callback');
    $attempt = $flow->begin();
    $request = $flow->exchange($attempt, ['state' => $attempt->state, 'code' => 'sensitive-code'], 'https://app.test/callback');
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => MockResponse::success(['access_token' => 'sensitive-access', 'refresh_token' => 'sensitive-refresh', 'token_type' => 'Bearer'])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', debug: true), $transport);
    $result = $client->send($request)->raw();
    expect($result->data)->toBeInstanceOf(OAuth2TokenSet::class)->and($result->data->expiresAt)->toBeNull();
    $sent = $transport->getRecorded()[0];
    parse_str($sent->body, $body);
    expect($body['code_verifier'])->toBe($attempt->codeVerifier);
    if ($method === ClientAuthentication::Basic) {
        expect($body)->not->toHaveKey('client_secret')->not->toHaveKey('client_id');
    } else {
        expect($body['client_id'])->toBe('client')->and(isset($sent->headers['Authorization']))->toBeFalse();
    }
    $dump = json_encode([$result->message(), $result->requestDebug()]);
    foreach (['sensitive-secret', 'sensitive-code', 'sensitive-access', 'sensitive-refresh', $attempt->codeVerifier] as $secret) {
        expect($dump)->not->toContain($secret);
    }
})->with(ClientAuthentication::cases());

it('CC имеет один ordinary retry цикл, code и refresh не повторяются', function (string $grant): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => MockResponse::serverError()]);
    $config = new OAuth2Config('https://id.test/token', 'client', 'secret');
    $auth = $grant === 'refresh_token'
        ? OAuth2Authenticator::authorizationCode($config, new OAuth2Credential(new OAuth2TokenSet('old', 'refresh', 1)))
        : OAuth2Authenticator::clientCredentials($config);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: $auth, authRetryAttempts: 3, retry: new RetryConfig(attempts: 3, baseDelay: 0, jitter: false)), $transport, $clock, $clock);
    $request = $grant === 'authorization_code' ? new TokenRequest($config, $grant, ['code' => 'once']) : new ResourceRequest();
    expect($client->send($request)->raw()->isFailed())->toBeTrue();
    expect($transport->getRecorded())->toHaveCount($grant === 'client_credentials' ? 3 : 1);
})->with(['client_credentials', 'authorization_code', 'refresh_token']);

it('прямой конфликт retry отклоняется, withRetry(1) разрешён', function (): void {
    $request = new TokenRequest(new OAuth2Config('https://id.test/token', 'id', 'secret'), 'authorization_code', ['code' => 'once']);
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['access_token' => 'token', 'token_type' => 'Bearer'])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), $transport);
    expect($client->send($request->withRetry(3))->raw()->errors->first()->code)->toBe(ErrorCode::ConfigurationError)
        ->and($transport->getRecorded())->toBe([]);
    expect($client->send($request->withRetry(1))->dataOrFail())->toBeInstanceOf(OAuth2TokenSet::class);
});

it('не принимает невалидный успешный token response', function (array|string $data, ErrorCode $code): void {
    $transport = new MockTransport();
    $transport->fake(['*' => new MockResponse($data, 200, ['Content-Type' => 'application/json'])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), $transport);
    $result = $client->send(new TokenRequest(new OAuth2Config('https://id.test/token', 'id', 'secret'), 'client_credentials'))->raw();
    expect($result->isFailed())->toBeTrue()->and($result->data)->toBeNull()
        ->and($result->errors->first()->code)->toBe($code);
})->with([
    'null' => ['null', ErrorCode::HydrationError], 'empty' => ['', ErrorCode::HydrationError],
    'broken' => ['{', ErrorCode::ResponseDecodingError], 'array' => ['[]', ErrorCode::HydrationError],
    'error' => [['error' => 'invalid_grant'], ErrorCode::HydrationError],
    'missing-type' => [['access_token' => 'a'], ErrorCode::HydrationError],
    'empty-token' => [['access_token' => '', 'token_type' => 'Bearer'], ErrorCode::HydrationError],
    'unsupported-type' => [['access_token' => 'a', 'token_type' => 'DPoP'], ErrorCode::HydrationError],
    'zero-ttl' => [['access_token' => 'a', 'token_type' => 'Bearer', 'expires_in' => 0], ErrorCode::HydrationError],
    'string-ttl' => [['access_token' => 'a', 'token_type' => 'Bearer', 'expires_in' => '60'], ErrorCode::HydrationError],
    'overflow' => [['access_token' => 'a', 'token_type' => 'Bearer', 'expires_in' => PHP_INT_MAX], ErrorCode::HydrationError],
    'bad-scope' => [['access_token' => 'a', 'token_type' => 'Bearer', 'scope' => ['a']], ErrorCode::HydrationError],
]);

it('относит неверный формат token response к decoding и не отправляет ресурсный запрос', function (?string $contentType, string $body, bool $async, bool $dependency): void {
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => static fn (TokenRequest $request) => new ProviderResponse(
        status: 200,
        headers: $contentType === null ? [] : ['Content-Type' => [$contentType]],
        body: $body,
        request: $request->getContext()->preparedRequest,
        duration: 0,
    )]);
    $config = new OAuth2Config('https://id.test/token', 'id', 'secret');
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.test',
        auth: OAuth2Authenticator::clientCredentials($config),
        authRetryAttempts: 3,
        retry: new RetryConfig(attempts: 3, baseDelay: 0, jitter: false),
    ), $transport);
    $request = $dependency ? new ResourceRequest() : new TokenRequest($config, 'client_credentials');
    $result = ($async ? $client->sendAsync($request)->wait() : $client->send($request))->raw();
    expect($result->isFailed())->toBeTrue()->and($result->data)->toBeNull()
        ->and($result->errors->first()->code)->toBe(ErrorCode::ResponseDecodingError)
        ->and($result->errors->first()->context['reason'])->toBe('unsupported_response_content_type')
        ->and($transport->getRecorded())->toHaveCount(1);
    if ($dependency) {
        expect($result->nested[0]->errors->first()->code)->toBe(ErrorCode::ResponseDecodingError);
    }
})->with([
    'html' => ['text/html', '<html>unavailable</html>'],
    'json as text' => ['text/plain', '{"access_token":"token","token_type":"Bearer"}'],
    'empty text' => ['text/plain', ''],
    'missing header' => [null, '{"access_token":"token","token_type":"Bearer"}'],
    'missing header and empty body' => [null, ''],
])->with([false, true])->with([false, true]);

it('сохраняет HTTP-категорию не-JSON отказа token endpoint', function (int $status): void {
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => new MockResponse('<html>unavailable</html>', $status, ['Content-Type' => 'text/html'])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), $transport);
    $result = $client->send(new TokenRequest(new OAuth2Config('https://id.test/token', 'id', 'secret'), 'client_credentials'))->raw();
    expect($result->errors->first()->code)->toBe(ErrorCode::fromHttpStatus($status))
        ->and($result->data)->toBeNull()->and($transport->getRecorded())->toHaveCount(1);
})->with([400, 429, 503]);

it('принимает JSON Content-Type с параметрами и суффиксом', function (string $contentType): void {
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => new MockResponse(['access_token' => 'token', 'token_type' => 'Bearer'], 200, ['Content-Type' => $contentType])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), $transport);
    $tokens = $client->send(new TokenRequest(new OAuth2Config('https://id.test/token', 'id', 'secret'), 'client_credentials'))->dataOrFail();
    expect($tokens)->toBeInstanceOf(OAuth2TokenSet::class)->and($tokens->accessToken)->toBe('token');
})->with(['application/json; charset=utf-8', 'application/token+json', 'Application/JSON ; charset=UTF-8']);

it('refresh сохраняет известные scopes и прежний refresh token при отсутствии полей', function (): void {
    $saved = [];
    $credential = new OAuth2Credential(new OAuth2TokenSet('old', 'refresh', 1, ['narrow']), onTokensChanged: function ($tokens) use (&$saved) { $saved[] = $tokens->export(); });
    $config = new OAuth2Config('https://id.test/token', 'id', 'secret', ['broad', 'narrow']);
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => MockResponse::success(['access_token' => 'new', 'token_type' => 'Bearer']), ResourceRequest::class => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: OAuth2Authenticator::authorizationCode($config, $credential)), $transport);
    expect($client->send(new ResourceRequest())->raw()->isSuccess())->toBeTrue();
    expect($transport->getRecorded()[0]->body)->toContain('scope=narrow')->and($credential->tokens()->refreshToken)->toBe('refresh')
        ->and($credential->tokens()->scopes)->toBe(['narrow'])->and($saved)->toHaveCount(1);
});

it('ошибка сохранения блокирует API и повторяет только сохранение новой пары', function (): void {
    $fail = true;
    $credential = new OAuth2Credential(new OAuth2TokenSet('old', 'old-refresh', 1), onTokensChanged: function () use (&$fail) { if ($fail) { throw new RuntimeException('store failed'); } });
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => MockResponse::success(['access_token' => 'new', 'refresh_token' => 'new-refresh', 'token_type' => 'Bearer']), ResourceRequest::class => MockResponse::success()]);
    $auth = OAuth2Authenticator::authorizationCode(new OAuth2Config('https://id.test/token', 'id', 'secret'), $credential);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: $auth), $transport);
    expect($client->send(new ResourceRequest())->raw()->isFailed())->toBeTrue()
        ->and($credential->tokens()->refreshToken)->toBe('new-refresh')
        ->and($client->send(new ResourceRequest())->raw()->errors->first()->context['reason'])->toBe('oauth2_token_persistence_failed')
        ->and($transport->getRecorded())->toHaveCount(1);
    $fail = false;
    $credential->retryPersistence();
    expect($client->send(new ResourceRequest())->raw()->isSuccess())->toBeTrue()->and($transport->getRecorded())->toHaveCount(2);
});

it('invalid_grant терминален до явного нового разрешения', function (): void {
    $credential = new OAuth2Credential(new OAuth2TokenSet('old', 'refresh', 1));
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => new MockResponse(['error' => 'invalid_grant', 'error_description' => 'secret-echo'], 400), ResourceRequest::class => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: OAuth2Authenticator::authorizationCode(new OAuth2Config('https://id.test/token', 'id', 'secret'), $credential)), $transport);
    $first = $client->send(new ResourceRequest())->raw();
    expect($first->response->status)->toBe(400)->and(json_encode($first->message()))->not->toContain('secret-echo');
    expect($client->send(new ResourceRequest())->raw()->errors->first()->context['reason'])->toBe('oauth2_authorization_required')
        ->and($transport->getRecorded())->toHaveCount(1);
    $credential->replaceAuthorization(new OAuth2TokenSet('new'));
    expect($client->send(new ResourceRequest())->raw()->isSuccess())->toBeTrue()->and($transport->getRecorded())->toHaveCount(2);
});

it('ротация с прежними правами сохраняет cache, новые права меняют ключ', function (): void {
    $credential = new OAuth2Credential(new OAuth2TokenSet('first', scopes: ['b', 'a']));
    $transport = new MockTransport();
    $transport->fake([ResourceRequest::class => MockResponse::success(['cached' => true])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', cacheConfig: new CacheConfig(store: new ArrayCache()), auth: OAuth2Authenticator::authorizationCode(new OAuth2Config('https://id.test/token', 'id', 'secret'), $credential)), $transport);
    expect($client->send(new ResourceRequest())->raw()->isSuccess())->toBeTrue();
    $credential->replaceAuthorization(new OAuth2TokenSet('second', scopes: ['a', 'b']));
    expect($client->send(new ResourceRequest())->raw()->isSuccess())->toBeTrue()->and($transport->getRecorded())->toHaveCount(1);
    $credential->replaceAuthorization(new OAuth2TokenSet('third', scopes: ['a']));
    expect($client->send(new ResourceRequest())->raw()->isSuccess())->toBeTrue()->and($transport->getRecorded())->toHaveCount(2);
});
