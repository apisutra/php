<?php

declare(strict_types=1);

use ApiSutra\Config\CacheConfig;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Tests\Stubs\Requests\ContractFlowRequest;
use ApiSutra\Tests\Stubs\Dto\PolymorphicOwnerPersonDto;
use ApiSutra\Tests\Support\SpyCache;
use ApiSutra\Tests\Support\FailingRateLimitStore;
use Acme\Discovery\FlowClient;
use Acme\Discovery\Requests\DiscoveryDtoRequest;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\RateLimiting\BackoffStrategy;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Resolver\ClassMapProvider;
use ApiSutra\Resolver\ClientDiscoveryCache;
use ApiSutra\Resolver\ClientDiscoveryService;
use ApiSutra\Resolver\ClientRegistry;
use ApiSutra\Resolver\ClientResolver;
use ApiSutra\Resolver\DiscoveryOptions;
use ApiSutra\Resolver\RequestNamespaceDetector;
use ApiSutra\Resolver\RequestScanner;
use ApiSutra\Result\PaginatedResult;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use ApiSutra\Tests\Stubs\Requests\PaginatedRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

describe('Core flow integration', function () {
    it('happy‑path проходит полный flow', function () {
        $transport = new MockTransport();
        $transport->fake([
            DiscoveryDtoRequest::class => MockResponse::success(['id' => 1, 'name' => 'User']),
        ]);

        $client = new FlowClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $cache = new ClientDiscoveryCache();
        $service = new ClientDiscoveryService($registry, $detector, $cache);
        $service->registerAuto($client, DiscoveryOptions::forceOff());

        $resolver = new ClientResolver($registry);
        $request = new DiscoveryDtoRequest();
        $request->setClient($resolver->resolve($request));

        $handle = $request->send();
        $result = $handle->raw();

        expect($result->isSuccess())->toBeTrue();
        expect($result->data)->toBeInstanceOf(SimpleResponseDto::class);
        expect($result->data->id)->toBe(1);

        $resolved = $handle->resolved();
        expect($resolved->isSuccess())->toBeTrue();
        expect($resolved->data())->toBeInstanceOf(SimpleResponseDto::class);
    });

    it('error‑path корректно обрабатывает retry и результат', function () {
        $transport = new MockTransport();
        $transport->fake([
            DiscoveryDtoRequest::class => MockResponse::sequence([
                MockResponse::serverError(),
                MockResponse::serverError(),
            ]),
        ]);

        $client = new FlowClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                retry: new RetryConfig(
                    attempts: 2,
                    baseDelay: 0,
                    maxDelay: 0,
                    backoff: BackoffStrategy::Constant,
                    jitter: false,
                    retryOn: [500],
                ),
                environment: Environment::Testing,
            ),
            $transport,
        );

        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $cache = new ClientDiscoveryCache();
        $service = new ClientDiscoveryService($registry, $detector, $cache);
        $service->registerAuto($client, DiscoveryOptions::forceOff());

        $resolver = new ClientResolver($registry);
        $request = new DiscoveryDtoRequest();
        $request->setClient($resolver->resolve($request));

        $handle = $request->send();
        $result = $handle->raw();

        expect($result->isFailed())->toBeTrue();
        expect($result->errors->first()?->code)->toBe(ErrorCode::ServerError);
        expect($transport->getRecorded())->toHaveCount(2);

        $resolved = $handle->resolved();
        expect($resolved->isFailed())->toBeTrue();
    });

    it('pagination‑flow интеграция', function () {
        $transport = new MockTransport();
        $transport->fake([
            PaginatedRequest::class => MockResponse::sequence([
                MockResponse::success([
                    'data' => [1],
                    'meta' => [
                        'page' => 1,
                        'per_page' => 1,
                        'total' => 2,
                        'has_more' => true,
                        'next_cursor' => 'page-2',
                    ],
                ]),
                MockResponse::success([
                    'data' => [2],
                    'meta' => [
                        'page' => 2,
                        'per_page' => 1,
                        'total' => 2,
                        'has_more' => false,
                    ],
                ]),
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                paginationRule: PaginationRule::all(),
                environment: Environment::Testing,
            ),
            $transport,
        );

        $request = new PaginatedRequest();
        $request->setClient($client);

        $result = $client->send($request)->raw();

        expect($result)->toBeInstanceOf(PaginatedResult::class);
        expect($result->items())->toBe([1, 2]);
        expect($transport->getRecorded())->toHaveCount(2);
    });
});

it('пять HTTP методов проходят path query header body и readonly Nested mapping без провайдера', function (HttpMethod $method): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success([
        'is_active' => false, 'count' => 0, 'note' => null,
        'group' => ['owners' => [['person' => ['name' => 'fixture']]]],
    ])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport);
    $handle = $client->send(new ContractFlowRequest($method));
    $dto = $handle->dataOrFail();
    $sent = $transport->getRecorded()[0];
    expect($sent->method)->toBe($method)->and($sent->url)->toContain('/items/a%2Fb?offset=0')
        ->and($sent->headers['X-Fixture'])->toBe('fixture')
        ->and($dto->active)->toBeFalse()->and($dto->count)->toBe(0)
        ->and($dto->note)->toBeNull()->and($dto->missing)->toBe('default')
        ->and($dto->group->owners[0])->toBeInstanceOf(PolymorphicOwnerPersonDto::class)
        ->and($dto->group->owners[0]->name)->toBe('fixture');
    if (in_array($method, [HttpMethod::POST, HttpMethod::PUT, HttpMethod::PATCH], true)) {
        expect(json_decode($sent->body, true, flags: JSON_THROW_ON_ERROR)['enabled'])->toBeFalse();
    }
})->with([HttpMethod::GET, HttpMethod::POST, HttpMethod::PUT, HttpMethod::PATCH, HttpMethod::DELETE]);

it('withCache без TTL сохраняет прежний TTL после отключения, disabled rate limit не обращается к store', function (): void {
    $cache = new SpyCache();
    $store = new FailingRateLimitStore();
    $store->failure = 'read';
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 1, 'name' => 'fixture'])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', cacheConfig: new CacheConfig(store: $cache), rateLimit: new RateLimitConfig(store: $store)), $transport);
    $execution = (new DiscoveryDtoRequest())->setClient($client)->withCache(10)->withoutCache()->withCache()
        ->withRateLimit(1, 60)->withoutRateLimit();
    expect($execution->send()->raw()->isSuccess())->toBeTrue()
        ->and($cache->lastSetTtl)->toBe(10)->and($store->reads)->toBe(0);
    expect($execution->send()->raw()->isSuccess())->toBeTrue()
        ->and($transport->getRecorded())->toHaveCount(1);
});
