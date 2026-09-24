<?php

declare(strict_types=1);

use ApiSutra\Config\HydrationConfig;
use ApiSutra\Auth\BearerAuthenticator;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use ApiSutra\Enums\Cache\CacheMode;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Tests\Support\ClockCache;
use ApiSutra\Tests\Support\TestAuthLockProvider;
use ApiSutra\Tests\Support\TestClientFactory;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\VO\Http\PreparedRequest;
use Psr\SimpleCache\CacheInterface;

it('сохраняет единый блок при пустой и несвязанной копии', function (bool $hasStore, bool $hasConfig): void {
    $store = $hasStore ? new ClockCache(new VirtualClock()) : null;
    $cache = $hasConfig ? new CacheConfig(ttl: 60, prefix: 'copy', mode: CacheMode::Disabled, store: $store) : null;
    $config = new ClientConfig(baseUrl: 'https://copy.test', cacheConfig: $cache);
    foreach ([$config, $config->with(), $config->with(timeout: 7), $config->with()->with(timeout: 7)] as $copy) {
        expect($copy->cacheConfig)->toBe($cache)->and($copy->cacheConfig?->store)->toBe($hasConfig ? $store : null);
    }
    expect($config->with())->not->toBe($config)->and($config->timeout)->toBe(30)
        ->and($config->with(timeout: 7)->timeout)->toBe(7);
})->with([true, false])->with([true, false]);

it('заменяет блок целиком без наследования прежнего store и параметров', function (): void {
    $store = new ClockCache(new VirtualClock());
    $cache = new CacheConfig(ttl: 60, prefix: 'old', mode: CacheMode::Disabled, store: $store);
    $config = new ClientConfig(baseUrl: 'https://copy.test', cacheConfig: $cache);
    $replacement = new CacheConfig(ttl: 10);
    $changed = $config->with(cacheConfig: $replacement);
    expect($changed->cacheConfig)->toBe($replacement)->and($replacement->store)->toBeNull()
        ->and($replacement->ttl)->toBe(10)->and($replacement->prefix)->toBe('')
        ->and($replacement->mode)->toBe(CacheMode::Enabled)
        ->and($config->with(cacheConfig: null)->with(timeout: 7)->cacheConfig)->toBeNull()
        ->and($config->cacheConfig)->toBe($cache)->and($cache->store)->toBe($store);
});

it('копирует отдельные поля блока без IO, очищения и клонирования зависимостей', function (): void {
    $clock = new VirtualClock();
    $store = new ClockCache($clock);
    $store->entries = ['existing' => ['value' => 'untouched', 'expires' => null]];
    $other = new ClockCache($clock);
    $identity = new class implements CacheIdentityProviderInterface {
        public function getCacheIdentity(?PreparedRequest $request = null): ?string
        {
            throw new LogicException('Конфигурирование не должно читать identity');
        }
    };
    $locks = new TestAuthLockProvider($clock);
    $cache = new CacheConfig(ttl: 60, prefix: 'old', mode: CacheMode::Disabled, identity: $identity, locks: $locks, store: $store);
    $config = new ClientConfig(baseUrl: 'https://copy.test', cacheConfig: $cache);
    $same = $cache->with();
    expect($same)->not->toBe($cache)->and($same)->toEqual($cache);
    foreach ([$same, $cache->with(ttl: 10), $cache->with(prefix: 'new')] as $copy) {
        expect($copy->store)->toBe($store)->and($copy->identity)->toBe($identity)
            ->and($copy->locks)->toBe($locks)->and($copy->mode)->toBe(CacheMode::Disabled);
    }
    expect($cache->with(ttl: 10)->ttl)->toBe(10)->and($cache->with(prefix: 'new')->prefix)->toBe('new')
        ->and($cache->with(mode: CacheMode::ReadOnly)->mode)->toBe(CacheMode::ReadOnly);
    $moved = $config->with(cacheConfig: $cache->with(store: $other));
    expect($moved->cacheConfig->store)->toBe($other)->and($moved->cacheConfig->ttl)->toBe(60)
        ->and($moved->cacheConfig->prefix)->toBe('old')->and($moved->cacheConfig->identity)->toBe($identity)
        ->and($moved->cacheConfig->locks)->toBe($locks);
    foreach (
        [$cache->with(store: $other, ttl: 10), $cache->with(store: $other)->with(ttl: 10),
        $cache->with(ttl: 10)->with(store: $other)] as $copy
    ) {
        expect($copy->store)->toBe($other)->and($copy->ttl)->toBe(10)->and($copy->locks)->toBe($locks);
    }
    $detached = $cache->with(store: null)->with();
    expect($detached->store)->toBeNull()->and($detached->identity)->toBe($identity)
        ->and($detached->locks)->toBe($locks)->and($detached->mode)->toBe(CacheMode::Disabled);
    expect($cache->with(identity: null)->identity)->toBeNull()->and($cache->with(identity: null)->locks)->toBe($locks)
        ->and($cache->with(locks: null)->locks)->toBeNull()->and($cache->with(locks: null)->identity)->toBe($identity)
        ->and($cache->with(identity: null, locks: null, store: null)->store)->toBeNull()
        ->and($cache->store)->toBe($store)->and($cache->ttl)->toBe(60)
        ->and($config->cacheConfig)->toBe($cache)->and($config->with(cacheConfig: null)->cacheConfig)->toBeNull()
        ->and($store->entries)->toBe(['existing' => ['value' => 'untouched', 'expires' => null]])
        ->and($store->events)->toBe([])->and($other->events)->toBe([])->and($locks->keys)->toBe([]);
});

it('отклоняет удалённые входы и неверные типы без обращения к backend', function (): void {
    $store = new ClockCache(new VirtualClock());
    $cache = new CacheConfig(store: $store);
    $config = new ClientConfig(baseUrl: 'https://copy.test', cacheConfig: $cache);
    foreach (['cache', 'cacheStore'] as $name) {
        foreach ([$store, null] as $value) {
            expect(fn () => new ClientConfig(...['baseUrl' => 'https://copy.test', $name => $value]))->toThrow(Error::class);
            expect(fn () => $config->with(...[$name => $value]))->toThrow(Error::class);
        }
    }
    expect(fn () => $cache->with(unknown: 1))->toThrow(Error::class)
        ->and(fn () => $cache->with(10))->toThrow(Error::class)
        ->and(fn () => $config->with(cacheConfig: $store))->toThrow(TypeError::class)
        ->and(fn () => new CacheConfig(store: $cache))->toThrow(TypeError::class);
    foreach (['ttl' => null, 'prefix' => null, 'mode' => null, 'store' => $cache, 'identity' => $store, 'locks' => $store] as $key => $value) {
        expect(fn () => $cache->with(...[$key => $value]))->toThrow(TypeError::class);
    }
    expect(fn () => $cache->with(ttl: '10'))->toThrow(TypeError::class)
        ->and($store->events)->toBe([])->and($config->cacheConfig)->toBe($cache);
});

it('поддерживает новый порядок ClientConfig, прежние позиции CacheConfig, массивы', function (): void {
    $store = new ClockCache(new VirtualClock());
    $cache = new CacheConfig(10, 'prefix', CacheMode::ReadOnly, null, null, $store);
    $config = new ClientConfig('https://copy.test', null, [], null, true, 1, null, 'info', $cache, 7);
    expect($config->cacheConfig)->toBe($cache)->and($cache->store)->toBe($store)->and($config->timeout)->toBe(7);
    $class = new ReflectionClass(ClientConfig::class);
    $block = new ReflectionClass(CacheConfig::class);
    expect($class->hasProperty('cache'))->toBeFalse()->and($class->hasProperty('cacheStore'))->toBeFalse()
        ->and($class->getProperty('cacheConfig')->isReadOnly())->toBeTrue()->and($block->isReadOnly())->toBeTrue()
        ->and((string) $block->getProperty('store')->getType())->toBe('?' . CacheInterface::class);
    foreach (
        [new ClientConfig(...['baseUrl' => 'https://copy.test', 'cacheConfig' => $cache])] as $copy
    ) {
        expect($copy->cacheConfig)->toBe($cache)->and($copy->cacheConfig->store)->toBe($store);
    }
    $retry = new RetryConfig();
    $config = $config->with(auth: new BearerAuthenticator('synthetic'), hydration: new HydrationConfig(rules: HydrationRules::create()));
    $copy = $config->with(auth: null, hydration: null, retry: $retry, authScopes: []);
    expect($copy->auth)->toBeNull()->and($copy->hydration)->toBeNull()->and($copy->retry)->toBe($retry)
        ->and($copy->cacheConfig)->toBe($cache)->and($copy->authScopes)->toBe([]);
});

it('TestClientFactory сохраняет явный null и блок без store без запасного подключения', function (): void {
    foreach ([null, new CacheConfig(ttl: 10)] as $cache) {
        $client = TestClientFactory::make(overrides: ['cacheConfig' => $cache]);
        expect($client->getConfig()->cacheConfig)->toBe($cache)
            ->and($client->getConfig()->cacheConfig?->store)->toBeNull();
    }
});
