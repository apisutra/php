<?php

declare(strict_types=1);

use ApiSutra\Auth\TokenAuthenticator;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Cache\CacheMode;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\CacheIdentity;
use ApiSutra\Tests\Stubs\Requests\AuthRequest;
use ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use ApiSutra\Tests\Stubs\Requests\TokenLoginRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\ClockCache;
use ApiSutra\Tests\Support\TestAuthLockProvider;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Transport\MockTransport;

it('читает сохранённые HTTP и token записи без повторной отправки', function (): void {
    $fixture = json_decode(file_get_contents(dirname(__DIR__, 2) . '/Fixtures/cache-persisted-entries.json'), true, flags: JSON_THROW_ON_ERROR);
    $clock = new VirtualClock();
    $clock->wallTime = $fixture['unixTime'];
    $store = new ClockCache($clock);
    $store->entries = $fixture['entries'];
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://cache-fixture.test',
        auth: new TokenAuthenticator('fixture-user', 'synthetic-password', refreshRequestClass: TokenLoginRequest::class),
        cacheConfig: new CacheConfig(store: $store, ttl: 60, prefix: 'fixture', identity: new CacheIdentity('tenant-1')),
    ), $transport, $clock, $clock);
    $dto = $client->send(new AuthRequest('fixture'))->dataOrFail();
    expect($dto->id)->toBe(7)->and($dto->name)->toBe('baseline')->and($transport->getRecorded())->toBe([])
        ->and($store->entries)->toBe($fixture['entries']);
    // Новый HTTP использует сохранённый токен; refresh запрещён fake-транспортом.
    $transport->fake([AuthRequest::class => MockResponse::success(['id' => 8, 'name' => 'fresh'])]);
    expect((new AuthRequest('fresh'))->setClient($client)->withoutCache()->dataOrFail()->id)->toBe(8)
        ->and($transport->getRecorded()[0]->headers['Authorization'])->toBe('Bearer synthetic-fixture-token');
    $transport->assertNotSent(TokenLoginRequest::class);
});

it('сохраняет store при копировании и меняет TTL только новых записей', function (bool $parameters): void {
    $clock = new VirtualClock();
    $store = new ClockCache($clock);
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake(['*' => MockResponse::success(['id' => 7])]);
    $config = new ClientConfig(baseUrl: 'https://copy.test', cacheConfig: new CacheConfig(store: $store, ttl: $parameters ? 60 : 3600));
    $client = new TestClient($config, $transport, $clock, $clock);
    foreach ([$config, $config->with(), $config->with(timeout: 7)] as $cfg) {
        $copy = new TestClient($cfg, $transport, $clock, $clock);
        expect($copy->send(new CacheProbeRequest('/old'))->raw()->response->status)->toBe(200);
    }
    expect($transport->getRecorded())->toHaveCount(1);
    $short = new TestClient($config->with(cacheConfig: $config->cacheConfig->with(ttl: 10)), $transport, $clock, $clock);
    $short->send(new CacheProbeRequest('/new'))->dataOrFail();
    $clock->advance(11000);
    $short->send(new CacheProbeRequest('/new'))->dataOrFail();
    $short->send(new CacheProbeRequest('/old'))->dataOrFail();
    $client->send(new CacheProbeRequest('/original-new'))->dataOrFail();
    $clock->advance(11000);
    $client->send(new CacheProbeRequest('/original-new'))->dataOrFail();
    expect($transport->getRecorded())->toHaveCount(4);
})->with([false, true]);

it('полная замена режима и отключение блока или store не меняют исходного клиента', function (string $detach): void {
    $clock = new VirtualClock();
    $store = new ClockCache($clock);
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake(['*' => MockResponse::success(['id' => 7])]);
    $config = new ClientConfig(baseUrl: 'https://copy.test', cacheConfig: new CacheConfig(store: $store, mode: CacheMode::Disabled));
    $disabled = new TestClient($config, $transport);
    for ($i = 0; $i < 2; $i++) {
        $disabled->send(new CacheProbeRequest())->dataOrFail();
    }
    expect($transport->getRecorded())->toHaveCount(2);
    $enabled = new TestClient($config->with(cacheConfig: new CacheConfig(store: $store)), $transport);
    for ($i = 0; $i < 2; $i++) {
        $enabled->send(new CacheProbeRequest())->dataOrFail();
    }
    expect($transport->getRecorded())->toHaveCount(3);
    $before = $store->entries;
    $store->events = [];
    $detached = match ($detach) {
        'block' => null,
        'store' => $config->cacheConfig->with(store: null),
        'replacement' => new CacheConfig(),
    };
    $removed = new TestClient($config->with(cacheConfig: $detached), $transport);
    for ($i = 0; $i < 2; $i++) {
        (new CacheProbeRequest())->setClient($removed)->withCache()->dataOrFail();
    }
    $removed->clearCache();
    (new CacheProbeRequest())->setClient($removed)->withCache()->clearCache();
    expect($transport->getRecorded())->toHaveCount(5)->and($store->events)->toBe([])->and($store->entries)->toBe($before);
    $enabled->send(new CacheProbeRequest())->dataOrFail();
    expect($transport->getRecorded())->toHaveCount(5);
})->with(['block', 'store', 'replacement']);

it('разделяет store, параметры namespace и исходный долгоживущий клиент', function (): void {
    $clock = new VirtualClock();
    $first = new ClockCache($clock);
    $second = new ClockCache($clock);
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake(['*' => MockResponse::success(['id' => 7])]);
    $config = new ClientConfig(baseUrl: 'https://copy.test', cacheConfig: new CacheConfig(store: $first));
    $configs = [$config, $config->with(cacheConfig: $config->cacheConfig->with(store: $second)), $config->with(cacheConfig: $config->cacheConfig->with(prefix: 'different')),
        $config->with(cacheConfig: $config->cacheConfig->with(identity: new CacheIdentity('tenant-2')))];
    foreach ($configs as $cfg) {
        $client = new TestClient($cfg, $transport);
        $client->send(new CacheProbeRequest())->dataOrFail();
        $client->send(new CacheProbeRequest())->dataOrFail();
    }
    expect($transport->getRecorded())->toHaveCount(4);
    $original = new TestClient($config, $transport);
    $original->send(new CacheProbeRequest())->dataOrFail();
    expect($transport->getRecorded())->toHaveCount(4);
    $original->clearCache();
    $original->send(new CacheProbeRequest())->dataOrFail();
    expect($transport->getRecorded())->toHaveCount(5)->and($second->entries)->not->toBe([]);
});

it('общий auth store переживает копирование, null блока снимает также явный locks', function (bool $parameters, string $detach): void {
    $clock = new VirtualClock();
    $store = new ClockCache($clock);
    $locks = new TestAuthLockProvider($clock);
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake([
        AuthRequest::class => MockResponse::success(['id' => 7, 'name' => 'auth']),
        TokenLoginRequest::class => MockResponse::success(['accessToken' => 'synthetic-token', 'expiresIn' => 600]),
    ]);
    $config = new ClientConfig(
        baseUrl: 'https://copy.test',
        cacheConfig: new CacheConfig(store: $store, mode: $parameters ? CacheMode::Disabled : CacheMode::Enabled, locks: $parameters ? $locks : null),
        auth: new TokenAuthenticator('synthetic-user', 'synthetic-password', refreshRequestClass: TokenLoginRequest::class)
    );
    foreach ([$config, $config->with(timeout: 7), $config->with(cacheConfig: $config->cacheConfig->with(mode: CacheMode::ReadOnly, locks: null))] as $cfg) {
        $client = new TestClient($cfg, $transport, $clock, $clock);
        (new AuthRequest('value'))->setClient($client)->withoutCache()->dataOrFail();
    }
    $transport->assertSent(TokenLoginRequest::class, times: 1);
    $snapshot = $store->entries;
    $store->events = [];
    $detached = $detach === 'store' ? $config->cacheConfig->with(store: null) : null;
    $removed = new TestClient($config->with(cacheConfig: $detached), $transport, $clock, $clock);
    (new AuthRequest('value'))->setClient($removed)->withoutCache()->dataOrFail();
    $transport->assertSent(TokenLoginRequest::class, times: 2);
    expect($store->events)->toBe([])->and($store->entries)->toBe($snapshot);
    if ($parameters) {
        $expectedLocks = $detach === 'store' ? 2 : 1;
        expect($locks->keys)->toHaveCount($expectedLocks)->and($locks->releases)->toBe($expectedLocks);
    }
})->with([false, true])->with(['store', 'block']);
