<?php

declare(strict_types=1);

use ApiSutra\Auth\BearerAuthenticator;
use ApiSutra\Auth\ApiKeyAuthenticator;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\OriginPolicy;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Config\CredentialsEnrichmentConfig;
use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\Contracts\Interfaces\Serialization\RequestPartsEnricherInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Http\Origin;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Auth\RefreshingAuthenticator;
use ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use ApiSutra\Tests\Stubs\Requests\MultipartUploadRequest;
use ApiSutra\Tests\Stubs\Requests\RefreshTokenRequest;
use ApiSutra\Tests\Stubs\Requests\UriQueryRequest;
use ApiSutra\Tests\Stubs\Retry\ConsumingHttpClient;
use ApiSutra\Tests\Stubs\Core\SequenceHttpClient;
use ApiSutra\Tests\Support\StrictCache;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Tests\Support\FakeSleeper;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\Transport\RecordingTransport;
use ApiSutra\Serialization\VO\RequestPartsBag;
use ApiSutra\VO\Files\FileInput;
use ApiSutra\VO\Pipeline\PipelineContext;
use ApiSutra\VO\Http\PreparedRequest;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

it('передаёт полный endpoint без base URL и автоматических credentials', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.test/v1?base=1', auth: new BearerAuthenticator('fixture-token'),
        credentialsConfig: new CredentialsEnrichmentConfig(query: ['api_key' => 'fixture-key']),
    ), $transport);
    $url = 'https://storage.test/secret/%2f?x=+&x=%20&&signature=fixture&';
    $result = (new CacheProbeRequest($url . '#ignored'))->setClient($client)->send()->raw();
    expect($result->isSuccess())->toBeTrue()->and($transport->getRecorded()[0]->url)->toBe($url)
        ->and($transport->getRecorded()[0]->headers)->not->toHaveKey('Authorization');
});

it('не наследует credentials при внешнем withBaseUrl', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: new BearerAuthenticator('fixture-token')), $transport);
    (new CacheProbeRequest())->setClient($client)->withBaseUrl('https://storage.test')->send();
    expect($transport->getRecorded()[0]->headers)->not->toHaveKey('Authorization');
});

it('заменяет и очищает override полного URL без изменения исходного запроса', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), $transport);
    $request = (new CacheProbeRequest())->setClient($client);
    $execution = $request->withBaseUrl('https://api.test/v2')->withUrl('https://storage.test/first');
    $execution->withUrl('https://storage.test/second')->send();
    $execution->withoutUrl()->send();
    $execution->send();
    $request->send();
    expect(array_map(static fn (PreparedRequest $r): string => $r->url, $transport->getRecorded()))->toBe([
        'https://storage.test/second', 'https://api.test/v2/items', 'https://storage.test/first', 'https://api.test/items',
    ]);
});

it('не отправляет конфликтующие query в готовый URL', function (mixed $value): void {
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), $transport);
    $result = (new UriQueryRequest($value))->setClient($client)->withUrl('https://storage.test/file?sig=fixture')->send()->raw();
    expect($result->errors->first()->code->value)->toBe('configuration_error')->and($transport->getRecorded())->toBe([]);
})->with([null, '', false, [1]]);

it('включает auth только при явном выборе и разрешённом origin', function (bool $allow, bool $explicit): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.test', auth: new BearerAuthenticator('fixture-token'),
        originPolicy: new OriginPolicy($allow ? ['https://storage.test'] : []),
    ), $transport);
    $request = (new CacheProbeRequest())->setClient($client)->withUrl('https://storage.test/file');
    $result = ($explicit ? $request->withAuth() : $request)->send()->raw();
    if ($explicit && !$allow) {
        expect($result->errors->first()->code->value)->toBe('configuration_error')->and($transport->getRecorded())->toBe([]);
    } else {
        expect($result->isSuccess())->toBeTrue()
            ->and($transport->getRecorded()[0]->headers['Authorization'] ?? null)->toBe($explicit ? 'Bearer fixture-token' : null);
    }
})->with([false, true])->with([false, true]);

it('forceAuth не обходит origin policy', function (): void {
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: new BearerAuthenticator('fixture-token')), $transport);
    $result = (new CacheProbeRequest())->setClient($client)->withBaseUrl('https://storage.test')->forceAuth()->send()->raw();
    expect($result->errors->first()->code->value)->toBe('configuration_error')->and($transport->getRecorded())->toBe([]);
});

it('не изменяет готовый query даже при разрешённом query auth', function (): void {
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: new ApiKeyAuthenticator('fixture', header: null, query: 'api_key')), $transport);
    $result = (new CacheProbeRequest())->setClient($client)->withUrl('https://api.test/file')->withAuth()->send()->raw();
    expect($result->errors->first()->code->value)->toBe('configuration_error')->and($transport->getRecorded())->toBe([]);
});

it('не делает refresh и auth retry для внешнего запроса без auth', function (): void {
    RefreshingAuthenticator::reset();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make([], 401)]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: new RefreshingAuthenticator()), $transport);
    $result = (new CacheProbeRequest())->setClient($client)->withUrl('https://storage.test/file')->send()->raw();
    expect($result->response->status)->toBe(401)->and($transport->getRecorded())->toHaveCount(1)
        ->and(RefreshingAuthenticator::$refreshCalls)->toBe(0)->and(RefreshingAuthenticator::$authenticateCalls)->toBe(0);
});

it('разрешённый auth refresh не наследует внешний URL родителя', function (): void {
    RefreshingAuthenticator::reset();
    $transport = new MockTransport();
    $transport->fake([
        CacheProbeRequest::class => MockResponse::sequence([MockResponse::make([], 401), MockResponse::success()]),
        RefreshTokenRequest::class => MockResponse::success(['token' => 'fixture-refreshed']),
    ]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.test', authScopes: ['upload' => new RefreshingAuthenticator()],
        originPolicy: new OriginPolicy(['https://storage.test']),
    ), $transport);
    $result = (new CacheProbeRequest())->setClient($client)->withUrl('https://storage.test/file?sig=fixture')->withAuthScope('upload')->send()->raw();
    expect($result->isSuccess())->toBeTrue()->and(RefreshingAuthenticator::$refreshCalls)->toBe(1)
        ->and(array_map(static fn (PreparedRequest $r): string => $r->url, $transport->getRecorded()))->toBe([
            'https://storage.test/file?sig=fixture', 'https://api.test/refresh', 'https://storage.test/file?sig=fixture',
        ])
        ->and($transport->getRecorded()[2]->headers['Authorization'])->toBe('Bearer fixture-refreshed');
});

it('обходит автоматический cache и запрещает его явное включение', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', cacheConfig: new CacheConfig(store: new StrictCache())), $transport);
    $request = (new CacheProbeRequest())->setClient($client)->withUrl('https://storage.test/file');
    $request->send();
    $request->send();
    expect($transport->getRecorded())->toHaveCount(2);
    expect($request->withCache()->send()->raw()->errors->first()->code->value)->toBe('configuration_error');
    expect($transport->getRecorded())->toHaveCount(2);
});

it('проверяет изменённый hook адрес до HTTP', function (bool $full): void {
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), $transport);
    $client->hooks()->on(Hook::BeforeSend, new class implements HookInterface {
        public function handle(PipelineContext $context): ?array
        {
            $context->preparedRequest = $context->preparedRequest->with(url: 'https://other.test/changed');
            return null;
        }
    });
    $request = (new CacheProbeRequest())->setClient($client);
    $result = ($full ? $request->withUrl('https://storage.test/file') : $request)->send()->raw();
    expect($result->errors->first()->code->value)->toBe('configuration_error')->and($transport->getRecorded())->toBe([]);
})->with([false, true]);

it('требует capability у стороннего PSR клиента', function (): void {
    RefreshingAuthenticator::reset();
    RefreshingAuthenticator::$shouldRefresh = true;
    $http = new SequenceHttpClient([new Response(204)]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: new RefreshingAuthenticator()), new HttpTransport($http, $factory, $factory));
    $result = (new CacheProbeRequest())->setClient($client)->withUrl('https://api.test/file')->withAuth()->send()->raw();
    expect($result->errors->first()->code->value)->toBe('configuration_error')->and($http->calls)->toBe(0)
        ->and(RefreshingAuthenticator::$refreshCalls)->toBe(0);
});

it('сохраняет ошибку готового URL через promise resolved и throwOnErrors', function (bool $throws): void {
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', throwOnErrors: $throws), $transport);
    $request = (new CacheProbeRequest(ids: [1]))->setClient($client)->withUrl('https://storage.test/file');
    if ($throws) {
        expect(fn () => $request->sendAsync()->wait()->raw())->toThrow(ConfigurationException::class);
    } else {
        $handle = $request->sendAsync();
        expect($handle->wait()->raw()->errors->first()->code->value)->toBe('configuration_error')
            ->and($handle->wait()->resolved()->isFailed())->toBeTrue();
        expect(fn () => $request->dataOrFail())->toThrow(ConfigurationException::class);
    }
    expect($transport->getRecorded())->toBe([]);
})->with([false, true]);

it('сравнивает origin независимо от регистра и default port', function (): void {
    expect(Origin::fromUrl('HTTPS://API.test/a'))->toBe(Origin::fromUrl('https://api.test:443/b'))
        ->and(Origin::fromUrl('http://api.test'))->not->toBe(Origin::fromUrl('https://api.test'))
        ->and(Origin::fromUrl('https://sub.api.test'))->not->toBe(Origin::fromUrl('https://api.test'));
});

it('отклоняет некорректный полный URL без HTTP', function (string $url): void {
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), $transport);
    $result = (new CacheProbeRequest())->setClient($client)->withUrl($url)->send()->raw();
    expect($result->errors->first()->code->value)->toBe('configuration_error')->and($transport->getRecorded())->toBe([]);
})->with(['file:///fixture', '//storage.test/file', '/relative', 'https://user:fixture@storage.test/file', 'https://storage.test/%invalid', "https://storage.test/a\nb", 'https://storage.test/a b', 'https://пример.test/file']);

it('не принимает wildcard path query или userinfo в origin policy', function (string $origin): void {
    expect(fn () => new OriginPolicy([$origin]))->toThrow(ConfigurationException::class);
})->with(['https://*.test', 'https://api.test/path', 'https://api.test?x=1', 'https://fixture@api.test']);

it('отключает и явно подключает общие enrichers на внешнем origin', function (bool $enabled): void {
    $enricher = new class implements RequestPartsEnricherInterface {
        public int $calls = 0;
        public function enrich(RequestInterface $request, RequestPartsBag $parts, ?PipelineContext $context = null): RequestPartsBag
        {
            $this->calls++;
            $parts->headers['X-Custom-Credential'] = 'fixture-explicit';
            return $parts;
        }
    };
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', requestEnrichers: [$enricher], originPolicy: new OriginPolicy(['https://storage.test'])), $transport);
    $request = (new CacheProbeRequest())->setClient($client)->withUrl('https://storage.test/file');
    expect(($enabled ? $request->withRequestEnrichers() : $request)->send()->raw()->isSuccess())->toBeTrue()
        ->and($enricher->calls)->toBe($enabled ? 1 : 0);
})->with([false, true]);

it('сохраняет multipart retry без автоматических body и form credentials', function (): void {
    $http = new ConsumingHttpClient([new Response(503), new Response(204)]);
    $factory = new HttpFactory();
    $clock = new VirtualClock();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.test',
        auth: new BearerAuthenticator('fixture-token'),
        credentialsConfig: new CredentialsEnrichmentConfig(body: ['secret' => 'fixture-body'], form: ['secret' => 'fixture-form']),
        retry: new RetryConfig(attempts: 2, baseDelay: 0, jitter: false, safeMethods: [HttpMethod::POST], totalTimeoutMs: 1000),
    ), new HttpTransport($http, $factory, $factory), new FakeSleeper(), $clock);
    $url = 'https://storage.test/file?sig=fixture&';
    $result = (new MultipartUploadRequest([FileInput::fromContent('fixture-file', 'file.txt')], 'comment'))
        ->setClient($client)->withUrl($url)->withHeader('X-Upload-Key', 'fixture-upload')->send()->raw();
    expect($result->isSuccess())->toBeTrue()->and($http->bodies)->toHaveCount(2)
        ->and($http->bodies[0])->toBe($http->bodies[1])->toContain('fixture-file')->not->toContain('fixture-body', 'fixture-form');
    foreach ($http->requests as $request) {
        expect($request->getRequestTarget())->toBe('/file?sig=fixture&')
            ->and($request->getHeaderLine('X-Upload-Key'))->toBe('fixture-upload')
            ->and($request->hasHeader('Authorization'))->toBeFalse();
    }
    foreach ($http->options as $options) {
        expect($options->timeoutMs)->toBe(1000)->and($options->destination->url)->toBe($url);
    }
});

it('маскирует готовый URL в debug logger и recorder', function (): void {
    $logger = new class extends AbstractLogger {
        /** @var list<array<string, mixed>> */
        public array $records = [];
        public function log(mixed $level, Stringable|string $message, array $context = []): void
        {
            $this->records[] = $context;
        }
    };
    $url = 'https://storage.test/fixture-path-secret?unknown=fixture-query-secret';
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['echo' => $url])]);
    $directory = sys_get_temp_dir() . '/apisutra-signed-' . bin2hex(random_bytes(8));
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', debug: true, logger: $logger, logLevel: LogLevel::DEBUG), new RecordingTransport($transport, $directory));
    try {
        $result = (new CacheProbeRequest())->setClient($client)->withUrl($url)->send()->raw();
        expect($result->isSuccess())->toBeTrue()
            ->and($result->requestDebugJson())->not->toContain('fixture-path-secret', 'fixture-query-secret')
            ->and(json_encode($logger->records))->not->toContain('fixture-path-secret', 'fixture-query-secret')
            ->and(file_get_contents(glob($directory . '/*.json')[0]))->not->toContain('fixture-path-secret', 'fixture-query-secret')
            ->and($result->requestDebug(false)['url'])->toBe($url)
            ->and($transport->getRecorded()[0]->url)->toBe($url);
    } finally {
        foreach (glob($directory . '/*.json') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});
