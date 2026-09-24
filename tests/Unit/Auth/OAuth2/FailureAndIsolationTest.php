<?php

declare(strict_types=1);

use ApiSutra\Auth\OAuth2\Internal\AuthorizationParameters;
use ApiSutra\Auth\OAuth2\Internal\TokenRequest;
use ApiSutra\Auth\OAuth2\OAuth2Authenticator;
use ApiSutra\Auth\OAuth2\OAuth2Credential;
use ApiSutra\Auth\OAuth2\OAuth2TokenSet;
use ApiSutra\Contracts\Interfaces\Core\TimeoutAwareTransportInterface;
use ApiSutra\Contracts\Interfaces\Core\DestinationAwareInterface;
use ApiSutra\VO\Http\TransportOptions;
use ApiSutra\Http\RequestDestination;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Config\CredentialsEnrichmentConfig;
use ApiSutra\Config\OAuth2Config;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Contracts\Interfaces\Continuation\ContinuationModeApplicatorInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Enums\Continuation\ContinuationMode;
use ApiSutra\Enums\Http\TransmissionState;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Transport\ConnectionException;
use ApiSutra\Serialization\VO\RequestPartsBag;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Auth\OAuth2\ResourceRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Tracing\MemoryLogger;
use ApiSutra\Tests\Support\ArrayCache;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Transport\RecordingTransport;
use ApiSutra\VO\Pipeline\PipelineContext;
use ApiSutra\VO\Errors\SystemErrorContextKeys;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;

it('ошибка сохранения сохраняет trace и локализацию зависимости после успешного HTTP', function (bool $async): void {
    $credential = new OAuth2Credential(new OAuth2TokenSet('old', 'refresh', 1), onTokensChanged: static fn () => throw new RuntimeException('store offline'));
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => MockResponse::success(['access_token' => 'new', 'token_type' => 'Bearer'])]);
    $config = new ClientConfig(
        baseUrl: 'https://api.test',
        auth: OAuth2Authenticator::authorizationCode(new OAuth2Config('https://id.test/token', 'id', 'secret'), $credential),
        localization: new LocalizationConfig('ru', ['ru' => ['oauth2.operation_failed' => 'Ошибка сохранения: {reason}']]),
    );
    $client = new TestClient($config, $transport);
    $request = (new ResourceRequest())->withTraceId('persistence-trace');
    $result = ($async ? $client->sendAsync($request)->wait() : $client->send($request))->raw();
    $error = $result->errors->first();
    expect($result->isFailed())->toBeTrue()->and($result->traceId)->toBe('persistence-trace')
        ->and($error->context[SystemErrorContextKeys::TraceId->value])->toBe('persistence-trace')
        ->and($error->context['reason'])->toBe('oauth2_token_persistence_failed')
        ->and($error->message)->toBe('Ошибка сохранения: oauth2_token_persistence_failed')
        ->and($result->exception->getMessage())->toBe($error->message)
        ->and($result->nested[0]->isSuccess())->toBeTrue()
        ->and($result->nested[0]->trace->parentExecutionId)->toBe($result->trace->executionId)
        ->and($transport->getRecorded())->toHaveCount(1);
})->with([false, true]);

it('сообщение token endpoint безопасно и согласовано с исключением', function (int $status): void {
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => new MockResponse(['message' => 'secret-code', 'error_description' => 'secret-verifier'], $status)]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.test',
        localization: new LocalizationConfig('ru', ['ru' => ['oauth2.token_request_failed' => 'Сервер авторизации отклонил запрос']]),
    ), $transport);
    $request = new TokenRequest(new OAuth2Config('https://id.test/token', 'id', 'secret'), 'authorization_code', ['code' => 'secret-code']);
    $result = $client->send($request)->raw();
    expect($result->errors->first()->message)->toBe('Сервер авторизации отклонил запрос')
        ->and($result->exception->getMessage())->toBe($result->errors->first()->message);
})->with([400, 429, 503]);

it('S256 совпадает с опубликованным в RFC 7636 test vector', function (): void {
    expect(AuthorizationParameters::pkceChallenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'))->toBe('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM');
});

it('не использует API enrichment, continuation и response cache для token request', function (): void {
    $applicator = new class implements ContinuationModeApplicatorInterface {
        public function apply(RequestInterface $request, RequestPartsBag $parts, ContinuationMode $mode, ?PipelineContext $context = null): RequestPartsBag
        {
            throw new RuntimeException('API continuation reached token endpoint');
        }
    };
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => MockResponse::success(['access_token' => 'token', 'token_type' => 'Bearer'])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', cacheConfig: new CacheConfig(store: new ArrayCache()), credentialsConfig: new CredentialsEnrichmentConfig(body: ['api_secret' => 'private']), continuationModeApplicator: $applicator), $transport);
    $request = new TokenRequest(new OAuth2Config('https://id.test/token', 'id', 'secret', tokenParameters: ['audience' => 'example']), 'client_credentials');
    expect($client->send($request)->raw()->isSuccess())->toBeTrue()->and($client->send($request)->raw()->isSuccess())->toBeTrue();
    expect($transport->getRecorded())->toHaveCount(2)->and($transport->getRecorded()[0]->body)->not->toContain('api_secret')->toContain('audience=example');
});

it('только достоверный NotSent разрешает следующую отдельную попытку refresh', function (TransmissionState $state): void {
    $credential = new OAuth2Credential(new OAuth2TokenSet('old', 'refresh', 1));
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => static fn () => throw new ConnectionException('fixture', transmissionState: $state)]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: OAuth2Authenticator::authorizationCode(new OAuth2Config('https://id.test/token', 'id', 'secret'), $credential), retry: new RetryConfig(attempts: 3)), $transport);
    expect($client->send(new ResourceRequest())->raw()->isFailed())->toBeTrue();
    expect($client->send(new ResourceRequest())->raw()->isFailed())->toBeTrue();
    expect($transport->getRecorded())->toHaveCount($state === TransmissionState::NotSent ? 2 : 1);
})->with(TransmissionState::cases());

it('CC не умножает неповторяемый отказ и ограничивает Retry-After общим бюджетом', function (bool $retryAfter): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => $retryAfter ? MockResponse::rateLimited(60) : new MockResponse(['error' => 'invalid_client'], 400)]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: OAuth2Authenticator::clientCredentials(new OAuth2Config('https://id.test/token', 'id', 'secret')), authRetryAttempts: 3, retry: new RetryConfig(attempts: 3, totalTimeoutMs: 1000)), $transport, $clock, $clock);
    $result = $client->send(new ResourceRequest())->raw();
    expect($result->isFailed())->toBeTrue()->and($transport->getRecorded())->toHaveCount(1)->and($clock->waits)->toBe([]);
    if ($retryAfter) {
        expect($result->errors->first()->context['reason'])->toBe('execution_deadline_exceeded');
    }
})->with([false, true]);

it('CC scoped token cache отделяет baseUrl и credentials без prefixes', function (): void {
    $cache = new ArrayCache();
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => MockResponse::success(['access_token' => 'a', 'token_type' => 'Bearer']), ResourceRequest::class => MockResponse::success()]);
    foreach ([['https://one.test', 'a'], ['https://one.test', 'a'], ['https://two.test', 'a'], ['https://one.test', 'b']] as [$url, $id]) {
        $client = new TestClient(new ClientConfig(baseUrl: $url, cacheConfig: new CacheConfig(store: $cache), auth: OAuth2Authenticator::clientCredentials(new OAuth2Config('https://id.test/token', $id, 'secret'))), $transport);
        expect($client->send((new ResourceRequest())->withoutCache())->raw()->isSuccess())->toBeTrue();
    }
    $transport->assertSent(TokenRequest::class, times: 3);
});

it('не смешивает конфигурации одного credential и не принимает Promise вместо сохранения', function (): void {
    $credential = new OAuth2Credential(new OAuth2TokenSet('a', 'r'), onTokensChanged: fn () => new FulfilledPromise(null));
    OAuth2Authenticator::authorizationCode(new OAuth2Config('https://id.test/token', 'a', 'secret'), $credential);
    expect(fn () => OAuth2Authenticator::authorizationCode(new OAuth2Config('https://id.test/token', 'b', 'secret'), $credential))->toThrow(ConfigurationException::class);
    try {
        $credential->replaceAuthorization(new OAuth2TokenSet('next'));
    } catch (Throwable) {
    }
    expect($credential->failureReason()->value)->toBe('oauth2_token_persistence_failed')->and($credential->tokens()->accessToken)->toBe('next');
});

it('credential без refresh token работает до отказа и затем требует авторизацию', function (): void {
    $credential = new OAuth2Credential(new OAuth2TokenSet('access'));
    $transport = new MockTransport();
    $transport->fake([ResourceRequest::class => MockResponse::sequence([MockResponse::success(), new MockResponse([], 401)])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: OAuth2Authenticator::authorizationCode(new OAuth2Config('https://id.test/token', 'id', 'secret'), $credential)), $transport);
    expect($client->send(new ResourceRequest())->raw()->isSuccess())->toBeTrue()->and($client->send(new ResourceRequest())->raw()->isFailed())->toBeTrue();
    expect($client->send(new ResourceRequest())->raw()->errors->first()->context['reason'])->toBe('oauth2_authorization_required')->and($transport->getRecorded())->toHaveCount(2);
});

it('recordings, nested diagnostics и logger маскируют OAuth secrets, raw данные сохраняются', function (): void {
    $directory = sys_get_temp_dir() . '/apisutra-oauth-' . bin2hex(random_bytes(8));
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => MockResponse::success(['access_token' => 'secret-access', 'refresh_token' => 'secret-refresh', 'token_type' => 'Bearer'])]);
    $logger = new MemoryLogger();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', logger: $logger, logLevel: 'debug', debug: true), new RecordingTransport($transport, $directory));
    try {
        $result = $client->send(new TokenRequest(new OAuth2Config('https://id.test/token', 'id', 'secret-client'), 'authorization_code', ['code' => 'secret-code', 'code_verifier' => 'secret-verifier']))->raw();
        expect($result->isSuccess())->toBeTrue()->and($result->data->accessToken)->toBe('secret-access');
        $files = glob($directory . '/*.json');
        expect($files)->toHaveCount(1);
        $dump = file_get_contents($files[0]) . $result->requestDebugJson() . json_encode($logger->records);
        foreach (['secret-access', 'secret-refresh', 'secret-client', 'secret-code', 'secret-verifier'] as $secret) {
            expect($dump)->not->toContain($secret);
        }
        expect($result->requestDebugJson(false))->toContain('secret-code');
        $playback = new MockTransport();
        $playback->loadFixtures($directory);
        $replay = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), $playback);
        expect($replay->send(new TokenRequest(new OAuth2Config('https://id.test/token', 'id', 'secret'), 'client_credentials'))->raw()->isSuccess())->toBeTrue();
    } finally {
        foreach (glob($directory . '/*.json') as $file) {
            unlink($file);
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});

it('серия refresh не удерживает предыдущие результаты, contexts и token sets', function (): void {
    $clock = new VirtualClock();
    $transport = new class implements TimeoutAwareTransportInterface, DestinationAwareInterface {
        public function sendAsync(PreparedRequest $request): PromiseInterface { return new FulfilledPromise($this->send($request)); }
        public function assertSupportsTimeouts(TransportOptions $options): void {}
        public function assertSupportsDestination(RequestDestination $destination): void {}
        public function send(PreparedRequest $request): ProviderResponse
        {
            return MockResponse::success(($request->meta['requestClass'] ?? null) === TokenRequest::class
                ? ['access_token' => 'new', 'token_type' => 'Bearer', 'expires_in' => 1] : ['ok' => true])->toProviderResponse($request);
        }
    };
    $credential = new OAuth2Credential(new OAuth2TokenSet('old', 'r', 1));
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: OAuth2Authenticator::authorizationCode(new OAuth2Config('https://id.test/token', 'id', 'secret'), $credential)), $transport, $clock, $clock);
    $refs = [];
    for ($i = 0; $i < 30; $i++) {
        $request = new ResourceRequest();
        $result = $client->send($request)->raw();
        expect($result->isSuccess())->toBeTrue();
        $refs[] = [WeakReference::create($result), WeakReference::create($request->getContext()), WeakReference::create($credential->tokens())];
        $clock->advance(2000);
    }
    gc_collect_cycles();
    foreach (array_slice($refs, 0, -1) as $objects) {
        foreach ($objects as $reference) {
            expect($reference->get())->toBeNull();
        }
    }
});
