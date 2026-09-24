<?php

declare(strict_types=1);

use ApiSutra\Auth\OAuth2\Internal\TokenRequest;
use ApiSutra\Auth\OAuth2\OAuth2Authenticator;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Config\OAuth2Config;
use ApiSutra\Continuation\ContinuationState;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Execution\Async\AsyncRuntime;
use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Integration\HttpMappingContext;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Auth\OAuth2\ResourceRequest;
use ApiSutra\Tests\Stubs\Continuation\RecordingStateResolver;
use ApiSutra\Tests\Stubs\Continuation\WithoutCriterionRequest;
use ApiSutra\Tests\Stubs\DtoMapping\CallbackHydrator;
use ApiSutra\Tests\Stubs\DtoMapping\HydratorRequest;
use ApiSutra\Tests\Stubs\DtoMapping\LocalHydrator;
use ApiSutra\Tests\Stubs\HydrationRules\CompositeRequest;
use ApiSutra\Tests\Stubs\HydrationRules\ContainerRequest;
use ApiSutra\Tests\Stubs\HydrationRules\ItemsRequest;
use ApiSutra\Tests\Stubs\HydrationRules\RecordDto;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Tracing\MemoryLogger;
use ApiSutra\Tests\Support\ArrayCache;
use ApiSutra\Transport\MockTransport;
use GuzzleHttp\Promise\Utils;
use Revolt\EventLoop;

it('соблюдает global local false и null выбор в sync и async', function (bool $async): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 7])]);
    $custom = new CallbackHydrator(fn () => new RecordDto(10), [RecordDto::class]);
    $config = new ClientConfig(baseUrl: 'https://hydrator.test', hydration: new HydrationConfig(hydrator: $custom));
    expect($config->with(debug: true)->hydration->hydrator)->toBe($custom);
    $client = new TestClient($config, $transport);
    foreach ([[null, 10], [LocalHydrator::class, 20], [false, 7]] as [$selection, $expected]) {
        $request = new HydratorRequest($selection);
        $result = $async ? $client->sendAsync($request)->wait() : $client->send($request);
        expect($result->dataOrFail()->id)->toBe($expected);
    }
})->with([false, true]);

it('применяет unwrap до custom и даёт исходный HTTP без второго снимка', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make(['wrapped' => ['id' => 7]], headers: ['X-Api-Version' => '2'])]);
    $custom = new CallbackHydrator(function ($data, $class, HydrationContext $context): object {
        expect($data)->toBe(['id' => 7])->and($context->http())->toBe($context->extension(HttpMappingContext::class))
            ->and($context->http()->response->header('X-Api-Version'))->toBe('2');
        return new RecordDto($data['id']);
    });
    $client = new TestClient(new ClientConfig(baseUrl: 'https://hydrator.test', hydration: new HydrationConfig(hydrator: $custom)), $transport);
    expect($client->send(new HydratorRequest(unwrap: 'wrapped'))->dataOrFail()->id)->toBe(7);
});

it('передаёт один выбор items контейнеру, cache hit composite и continuation', function (): void {
    $calls = [];
    $custom = new CallbackHydrator(function ($data, $class, HydrationContext $context) use (&$calls): object {
        $calls[] = $context->http();
        return new RecordDto($data['id'] + 1);
    }, [RecordDto::class]);
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 7, 'response' => ['rows' => [['id' => 7]]]])]);
    $resolver = new RecordingStateResolver();
    $resolver->state = ContinuationState::ready(['id' => 8]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://hydrator.test', hydration: new HydrationConfig(hydrator: $custom), cacheConfig: new CacheConfig(store: new ArrayCache()), continuationStateResolver: $resolver), $transport);
    foreach ([ItemsRequest::class, ContainerRequest::class] as $class) {
        $data = $client->send(new $class())->dataOrFail();
        expect(($class === ContainerRequest::class ? $data->items() : $data)[0]->id)->toBe(8);
    }
    foreach ([0, 1] as $call) {
        expect(new HydratorRequest()->setClient($client)->withCache()->dataOrFail()->id)->toBe(8);
    }
    expect($transport->getRecorded())->toHaveCount(2);
    expect($client->send(new CompositeRequest()->setClient($client))->dataOrFail()->id)->toBe(8);
    expect($client->send(new WithoutCriterionRequest())->awaitAs(RecordDto::class)->id)->toBe(9);
    expect($calls)->toHaveCount(6)->and($calls[5])->toBeNull();
});

it('классифицирует DI ошибки и сохраняет сообщение Returns при неверном результате', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 7])]);
    $custom = new CallbackHydrator(fn () => new stdClass());
    $client = new TestClient(new ClientConfig(baseUrl: 'https://hydrator.test', hydration: new HydrationConfig(hydrator: $custom)), $transport);
    foreach ([stdClass::class, CallbackHydrator::class] as $class) {
        expect($client->send(new HydratorRequest($class))->raw()->errors->first()->code)->toBe(ErrorCode::ConfigurationError);
    }
    $result = $client->send(new HydratorRequest())->raw();
    expect($result->errors->first()->code)->toBe(ErrorCode::HydrationError)
        ->and($result->exception->getMessage())->toBe('Unexpected record.');
});

it('изолирует HTTP контексты и выбор гидратора при чередовании вызовов', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 7])]);
    $traces = [];
    $custom = new CallbackHydrator(function ($data, $class, HydrationContext $context) use (&$traces): object {
        $http = $context->http();
        new AsyncRuntime()->sleep(1);
        expect($context->http())->toBe($http);
        $traces[] = $http->traceId;
        return new RecordDto($data['id']);
    });
    $client = new TestClient(new ClientConfig(baseUrl: 'https://hydrator.test', hydration: new HydrationConfig(hydrator: $custom)), $transport);
    $results = Utils::all([
        $client->sendAsync(new HydratorRequest()),
        $client->sendAsync(new HydratorRequest()),
        $client->sendAsync(new HydratorRequest(LocalHydrator::class)),
        $client->sendAsync(new HydratorRequest(false)),
    ])->wait();
    expect($results[0]->dataOrFail()->id)->toBe(7)->and($results[1]->dataOrFail()->id)->toBe(7)
        ->and($results[2]->dataOrFail()->id)->toBe(20)->and($results[3]->dataOrFail()->id)->toBe(7)
        ->and(array_unique($traces))->toHaveCount(2);
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe([]);
});

it('сохраняет native OAuth нормализацию при глобальном гидраторе для всех типов', function (): void {
    $custom = new CallbackHydrator(fn () => throw new LogicException('OAuth must not call custom hydrator'));
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => MockResponse::success(['access_token' => 'synthetic', 'token_type' => 'Bearer', 'expires_in' => 60]), ResourceRequest::class => MockResponse::success(['ok' => true])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://hydrator.test', hydration: new HydrationConfig(hydrator: $custom), auth: OAuth2Authenticator::clientCredentials(new OAuth2Config('https://id.test/token', 'client', 'secret'))), $transport);
    expect($client->send(new ResourceRequest())->raw()->isSuccess())->toBeTrue()->and($transport->getRecorded())->toHaveCount(2);
});

it('не раскрывает текст исключения обработчика через debug или logger', function (bool $async): void {
    $logger = new MemoryLogger();
    $custom = new CallbackHydrator(fn () => throw new LogicException('synthetic-hydrator-secret'));
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 7])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://hydrator.test', hydration: new HydrationConfig(hydrator: $custom), debug: true, logger: $logger, logLevel: 'debug'), $transport);
    $handle = $async ? $client->sendAsync(new HydratorRequest())->wait() : $client->send(new HydratorRequest());
    expect($handle->raw()->errors->first()->code)->toBe(ErrorCode::HydrationError);
    expect(json_encode([$handle->raw()->requestDebug(), $logger->records, $handle->raw()->message()]))->not->toContain('synthetic-hydrator-secret');
})->with([false, true]);
