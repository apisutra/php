<?php

declare(strict_types=1);

use ApiSutra\Tests\Support\MappingInvocation;

use ApiSutra\Auth\ApiKeyAuthenticator;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Casts\BooleanCast;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Diagnostics\RedactionPolicy;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Enums\Http\QueryArrayFormat;
use ApiSutra\Enums\Serialization\BooleanFormat;
use ApiSutra\Exceptions\Serialization\SerializationException;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\BooleanMultipartRequest;
use ApiSutra\Tests\Stubs\Requests\BooleanWireRequest;
use ApiSutra\Tests\Stubs\Requests\UriPathRequest;
use ApiSutra\Tests\Stubs\Requests\UriQueryRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Retry\ConsumingHttpClient;
use ApiSutra\Tests\Stubs\Auth\RefreshingAuthenticator;
use ApiSutra\Tests\Support\FakeSleeper;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\Tests\Support\StrictCache;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Http\PreparedRequest;

it('проводит URI через pipeline одинаково для всех HTTP методов', function (HttpMethod $method): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test/v1?base=+#ignored', auth: new ApiKeyAuthenticator('fixture key', header: null, query: 'api_key')), $transport);
    $result = (new UriQueryRequest(false, '/items?fixed=%2F&fixed=+#fragment', $method))->setClient($client)->send()->raw();
    expect($result->isSuccess())->toBeTrue()
        ->and($transport->getRecorded()[0]->url)->toBe('https://api.test/v1/items?base=+&fixed=%2F&fixed=+&value=0&included=&api_key=fixture%20key');
})->with([HttpMethod::GET, HttpMethod::POST, HttpMethod::PUT, HttpMethod::PATCH, HttpMethod::DELETE]);

it('разрешает null через атрибут независимо от глобальной политики', function (bool $serializeNulls): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', serializeNulls: $serializeNulls), $transport);
    (new UriQueryRequest())->setClient($client)->send();
    expect($transport->getRecorded()[0]->url)->toBe('https://api.test/items?' . ($serializeNulls ? 'value=&' : '') . 'included=');
})->with([false, true]);

it('пропускает пустые списки во всех форматах', function (QueryArrayFormat $format): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', queryArrayFormat: $format), $transport);
    (new UriQueryRequest([]))->setClient($client)->send();
    expect($transport->getRecorded()[0]->url)->toBe('https://api.test/items?included=');
})->with(QueryArrayFormat::cases());

it('применяет клиентский формат boolean и casts без изменения JSON', function (BooleanFormat $format, bool $multipart): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $config = new ClientConfig(baseUrl: 'https://api.test', textBooleanFormat: $format);
    $client = new TestClient($config->with(debug: true), $transport);
    $request = $multipart ? new BooleanMultipartRequest() : new BooleanWireRequest();
    expect($request->setClient($client)->send()->raw()->isSuccess())->toBeTrue();
    $prepared = $transport->getRecorded()[0];
    expect($prepared->url)->toContain('default=' . $format->format(false) . '&literal=false&numeric=1&structure=%7B%22nested%22%3A%7B%22enabled%22%3Afalse%7D%7D');
    if ($multipart) {
        $body = (string) $prepared->stream;
        expect($body)->toContain('name="bodyBoolean"' . "\r\nContent-Length: " . strlen($format->format(false)) . "\r\n\r\n" . $format->format(false) . "\r\n")
            ->toContain('name="bodyArray"' . "\r\nContent-Length: 12\r\n\r\n[false,true]\r\n")
            ->toContain('name="bodyLiteral"' . "\r\nContent-Length: 5\r\n\r\nfalse\r\n");
    } else {
        expect(json_decode($prepared->body, true))->toBe(['bodyBoolean' => false, 'bodyArray' => [false, true], 'bodyLiteral' => 'false']);
    }
})->with(BooleanFormat::cases())->with([false, true]);

it('ошибка query или path останавливает отправку и retry', function (bool $path, bool $throws): void {
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', retry: new RetryConfig(), throwOnErrors: $throws), $transport);
    $request = ($path ? new UriPathRequest() : new UriQueryRequest(['key' => 'value']))->setClient($client);
    if ($throws) {
        expect(fn () => $request->send()->raw())->toThrow(SerializationException::class);
    } else {
        $result = $request->send()->raw();
        expect($result->errors->first()->code->value)->toBe('serialization_error');
    }
    expect($transport->getRecorded())->toBe([]);
})->with([false, true])->with([false, true]);

it('повторная query auth заменяет только собственную пару SDK и сохраняет копии', function (): void {
    $first = new ApiKeyAuthenticator('fixture-a', header: null, query: 'key');
    $second = new ApiKeyAuthenticator('fixture-b', header: null, query: 'key');
    $request = new PreparedRequest(HttpMethod::GET, 'https://api.test/items?key=original&key=fixture-a&x=%2F#fragment');
    $authenticated = $first->authenticate($request);
    $changed = $second->authenticate($authenticated);
    expect($first->authenticate($authenticated)->url)->toBe($authenticated->url)
        ->and($changed->url)->toBe('https://api.test/items?key=original&key=fixture-a&x=%2F&key=fixture-b')
        ->and($request->url)->toBe('https://api.test/items?key=original&key=fixture-a&x=%2F#fragment');
});

it('доставляет исходные query байты до PSR клиента при 401 и обычном retry', function (): void {
    $http = new ConsumingHttpClient([new Response(401), new Response(503), new Response(204)]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.test/v1?base=%2F&base=+',
        auth: new ApiKeyAuthenticator('fixture key', header: null, query: 'key'),
        retry: new RetryConfig(attempts: 3, baseDelay: 0, maxDelay: 0, jitter: false, retryOn: [401, 503]),
    ), new HttpTransport($http, $factory, $factory), new FakeSleeper(), new VirtualClock());
    $result = (new UriQueryRequest(false, '/items/%2F?key=original&x=+&x=%20#ignored'))->setClient($client)->send()->raw();
    expect($result->isSuccess())->toBeTrue()->and($http->requests)->toHaveCount(3);
    foreach ($http->requests as $request) {
        expect($request->getRequestTarget())->toBe('/v1/items/%2F?base=%2F&base=+&key=original&x=+&x=%20&value=0&included=&key=fixture%20key');
    }
});

it('отклоняет неподдерживаемый endpoint до авторизации и HTTP', function (string $endpoint): void {
    RefreshingAuthenticator::reset();
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: new RefreshingAuthenticator()), $transport);
    $result = (new UriQueryRequest(endpoint: $endpoint))->setClient($client)->send()->raw();
    expect($result->errors->first()->code->value)->toBe('configuration_error')
        ->and(RefreshingAuthenticator::$authenticateCalls)->toBe(0)
        ->and($transport->getRecorded())->toBe([]);
})->with(['https://other.test/items', '//other.test/items', '/items/../secret']);

it('не меняет существующий BooleanCast без явного текстового формата', function (): void {
    $cast = new BooleanCast();
    expect(MappingInvocation::serialize($cast, false))->toBeFalse()->and(MappingInvocation::serialize($cast, 'yes'))->toBeTrue()
        ->and(MappingInvocation::serialize($cast, null))->toBeNull()->and(MappingInvocation::hydrate($cast, 'yes'))->toBeTrue()
        ->and(MappingInvocation::hydrate($cast, 'false'))->toBeFalse();
    expect(MappingInvocation::serialize(new BooleanCast(BooleanFormat::Literal), false))->toBe('false')
        ->and(MappingInvocation::serialize(new BooleanCast(BooleanFormat::Numeric), false))->toBe('0');
});

it('различает false и пустую строку в автоматическом кеше', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', cacheConfig: new CacheConfig(store: new StrictCache())), $transport);
    foreach ([false, '', false, ''] as $value) {
        expect((new UriQueryRequest($value))->setClient($client)->send()->raw()->isSuccess())->toBeTrue();
    }
    expect($transport->getRecorded())->toHaveCount(2);
});

it('сохраняет исходный execution при withBaseUrl и кодирует path на границе HTTP', function (): void {
    $http = new ConsumingHttpClient([new Response(204), new Response(204)]);
    $factory = new HttpFactory();
    $config = new ClientConfig(baseUrl: 'https://api.test/v1?base=1');
    $client = new TestClient($config, new HttpTransport($http, $factory, $factory));
    $request = (new UriPathRequest('мир/%2F +'))->setClient($client);
    $execution = $request->withBaseUrl('https://other.test/v2?base=2');
    expect($execution->send()->raw()->isSuccess())->toBeTrue()
        ->and($request->send()->raw()->isSuccess())->toBeTrue()
        ->and((string) $http->requests[0]->getUri())->toBe('https://other.test/v2/items/%D0%BC%D0%B8%D1%80%2F%252F%20%2B?base=2')
        ->and((string) $http->requests[1]->getUri())->toBe('https://api.test/v1/items/%D0%BC%D0%B8%D1%80%2F%252F%20%2B?base=1')
        ->and($config->baseUrl)->toBe('https://api.test/v1?base=1');
});

it('маскирует все повторяющиеся секреты в диагностической копии URI', function (): void {
    $auth = new ApiKeyAuthenticator('fixture-new', header: null, query: 'api_key');
    $prepared = $auth->authenticate(new PreparedRequest(HttpMethod::GET, 'https://api.test/items?api_key=fixture-old&x=%2F&api_key=fixture-other'));
    expect((new RedactionPolicy())->url($prepared->url))->toBe('https://api.test/items?api_key=***&x=%2F&api_key=***&api_key=***')
        ->and($prepared->url)->toContain('api_key=fixture-old', 'api_key=fixture-other', 'api_key=fixture-new');
});

it('сохраняет literal boolean в элементах query списка', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', textBooleanFormat: BooleanFormat::Literal), $transport);
    (new UriQueryRequest([false, true, '0', '']))->setClient($client)->send();
    expect($transport->getRecorded()[0]->url)->toBe('https://api.test/items?value[]=false&value[]=true&value[]=0&value[]=&included=');
});

it('повторно отправляет те же байты multipart boolean и сохраняет transport options', function (): void {
    $http = new ConsumingHttpClient([new Response(503), new Response(204)]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.test',
        retry: new RetryConfig(attempts: 2, baseDelay: 0, jitter: false, safeMethods: [HttpMethod::POST], totalTimeoutMs: 1000),
    ), new HttpTransport($http, $factory, $factory), new FakeSleeper(), new VirtualClock());
    expect((new BooleanMultipartRequest())->setClient($client)->send()->raw()->isSuccess())->toBeTrue()
        ->and($http->bodies)->toHaveCount(2)
        ->and($http->bodies[0])->toBe($http->bodies[1])->toContain("\r\n\r\n0\r\n", '[false,true]')
        ->and($http->options)->toHaveCount(2);
    foreach ($http->options as $options) {
        expect($options->timeoutMs)->toBeGreaterThan(0)->toBeLessThanOrEqual(1000);
    }
});

it('доставляет ошибку URI через async и dataOrFail без HTTP', function (): void {
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), $transport);
    $request = (new UriPathRequest())->setClient($client);
    expect($request->sendAsync()->wait()->raw()->errors->first()->code->value)->toBe('serialization_error');
    expect(fn () => $request->dataOrFail())->toThrow(SerializationException::class);
    expect($transport->getRecorded())->toBe([]);
});
