<?php

declare(strict_types=1);

use ApiSutra\Auth\TokenAuthenticator;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Pipeline\Auth\AuthRefreshLock;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Auth\TokenCacheProbeAuthenticator;
use ApiSutra\Tests\Stubs\CacheIdentity;
use ApiSutra\Tests\Stubs\Dto\TokenLoginResponse;
use ApiSutra\Tests\Stubs\Requests\AuthRequest;
use ApiSutra\Tests\Stubs\Requests\TokenLoginRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\ArrayCache;
use ApiSutra\Tests\Support\StrictCache;
use ApiSutra\Tests\Support\TestAuthLockProvider;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Http\PreparedRequest;

it('освобождает локальную блокировку при обычном PSR-16 store', function (): void {
    $lock = new AuthRefreshLock(new ArrayCache());
    $owner = $lock->acquire('fixture', 30);
    $lock->release('fixture', $owner);
    expect($lock->acquire('fixture', 30))->not->toBeNull();
});

it('разделяет credentials в прямом кеше и принимает специальные символы username', function (): void {
    $store = new StrictCache();
    $a = new TokenAuthenticator('fixture:user@example.test', 'password-a');
    $b = new TokenAuthenticator('fixture:user@example.test', 'password-b');
    $a->setCache($store);
    $a->processTokenResponse(new TokenLoginResponse('token-a'));
    $b->setCache($store);
    expect($b->shouldRefresh())->toBeTrue()
        ->and($a->getCacheKey())->not->toBe($b->getCacheKey());
});

it('не переносит токен при смене store', function (): void {
    $auth = new TokenAuthenticator('fixture', 'password');
    $auth->setCache(new ArrayCache());
    $auth->processTokenResponse(new TokenLoginResponse('token-a'));
    $auth->setCache(new ArrayCache());
    expect($auth->authenticate(new PreparedRequest(HttpMethod::GET, 'https://fixture.test'))->headers)
        ->not->toHaveKey('Authorization');
});

it('изолирует токены клиентов и повторно использованный auth без дополнительных параметров', function (bool $sameObject, bool $sameServer): void {
    $store = new ArrayCache();
    $a = new TokenAuthenticator('fixture', 'password-a', refreshRequestClass: TokenLoginRequest::class);
    $b = $sameObject ? $a : new TokenAuthenticator('fixture', 'password-b', refreshRequestClass: TokenLoginRequest::class);
    $transports = [];
    $clients = [];
    foreach ([$a, $b] as $i => $auth) {
        $transport = new MockTransport();
        $transport->fake([
            TokenLoginRequest::class => MockResponse::success(['accessToken' => 'token-' . $i, 'expiresIn' => 600]),
            AuthRequest::class => MockResponse::success(['id' => 1, 'name' => 'fixture']),
        ]);
        $transports[] = $transport;
        $clients[] = new TestClient(new ClientConfig(
            baseUrl: !$sameServer && $i === 1 ? 'https://b.fixture.test' : 'https://a.fixture.test',
            auth: $auth, cacheConfig: new CacheConfig(store: $store),
        ), $transport);
    }
    foreach ([0, 1, 0] as $i) {
        expect((new AuthRequest('fixture'))->setClient($clients[$i])->withoutCache()->send()->raw()->isSuccess())->toBeTrue();
    }
    $recordsA = $transports[0]->getRecorded();
    $recordsB = $transports[1]->getRecorded();
    expect($recordsA)->toHaveCount(3)
        ->and($recordsB)->toHaveCount(2)
        ->and($recordsA[2]->headers['Authorization'])->toBe('Bearer token-0')
        ->and($recordsB[1]->headers['Authorization'])->toBe('Bearer token-1');
})->with([[false, true], [false, false], [true, false]]);

it('перечитывает токен после ожидания и отличает новый токен от отвергнутого при 401', function (bool $force, bool $changed): void {
    $clock = new VirtualClock();
    $store = new StrictCache();
    $locks = new TestAuthLockProvider($clock);
    $transport = new MockTransport();
    $transport->fake([
        TokenLoginRequest::class => MockResponse::success(['accessToken' => 'old-token', 'expiresIn' => 600]),
        AuthRequest::class => MockResponse::success(['id' => 1, 'name' => 'fixture']),
    ]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', timeout: 5,
        auth: new TokenAuthenticator('fixture', 'password', refreshRequestClass: TokenLoginRequest::class),
        cacheConfig: new CacheConfig(store: $store, locks: $locks),
    ), $transport, $clock, $clock);
    $request = (new AuthRequest('fixture'))->setClient($client)->withoutCache();
    expect($request->send()->raw()->isSuccess())->toBeTrue();
    $key = $store->keys[0];
    if (!$force) {
        $clock->advance(601000);
    } else {
        $transport->fake([AuthRequest::class => MockResponse::sequence([
            MockResponse::make('rejected', 401), MockResponse::success(['id' => 1, 'name' => 'fixture']),
        ])]);
    }
    $locks->busy = true;
    $locks->onAcquire = static function () use ($store, $key, $clock, $changed): void {
        $store->set($key, ['token' => $changed ? 'new-token' : 'old-token', 'expires_at' => $clock->unixTime() + 600], 600);
    };
    $result = $request->send()->raw();
    $records = $transport->getRecorded();
    if ($force && !$changed) {
        expect($result->errors->first()->context['reason'])->toBe('auth_refresh_lock_timeout')
            ->and($result->response->status)->toBe(401);
    } else {
        expect($result->isSuccess())->toBeTrue()
            ->and($records[array_key_last($records)]->headers['Authorization'])->toBe('Bearer ' . ($changed ? 'new-token' : 'old-token'));
    }
    $refreshes = array_filter($records, static fn (PreparedRequest $record): bool => str_ends_with($record->url, '/token'));
    expect($refreshes)->toHaveCount(1);
})->with([false, true])->with([false, true]);

it('без store работает zero-config и не переносит память auth в другой клиент', function (): void {
    $auth = new TokenAuthenticator('fixture', 'password', refreshRequestClass: TokenLoginRequest::class);
    foreach (['a', 'b'] as $name) {
        $transport = new MockTransport();
        $transport->fake([
            TokenLoginRequest::class => MockResponse::success(['accessToken' => 'token-' . $name, 'expiresIn' => 600]),
            AuthRequest::class => MockResponse::success(['id' => 1, 'name' => 'fixture']),
        ]);
        $client = new TestClient(new ClientConfig(baseUrl: 'https://' . $name . '.fixture.test', auth: $auth), $transport);
        $request = (new AuthRequest('fixture'))->setClient($client);
        expect($request->send()->raw()->isSuccess())->toBeTrue()
            ->and($request->send()->raw()->isSuccess())->toBeTrue()
            ->and($transport->getRecorded())->toHaveCount(3)
            ->and($transport->getRecorded()[2]->headers['Authorization'])->toBe('Bearer token-' . $name);
    }
    expect($auth->shouldRefresh())->toBeTrue();
});

it('разделяет объявленные scope, tenant и base path, сохраняя общий кеш одинакового контекста', function (string $variant): void {
    $store = new StrictCache();
    $auth = new TokenAuthenticator('fixture', 'password', refreshRequestClass: TokenLoginRequest::class);
    foreach ([0, 1] as $i) {
        $transport = new MockTransport();
        $transport->fake([
            TokenLoginRequest::class => MockResponse::success(['accessToken' => 'token-' . $i, 'expiresIn' => 600]),
            AuthRequest::class => MockResponse::success(['id' => 1, 'name' => 'fixture']),
        ]);
        $client = new TestClient(new ClientConfig(
            baseUrl: 'https://fixture.test/' . ($variant === 'base_path' && $i === 1 ? 'b' : 'a'),
            auth: $auth, authScopes: ['secondary' => $auth],
            cacheConfig: new CacheConfig(store: $store, identity: new CacheIdentity($variant === 'tenant' && $i === 1 ? 'b' : 'a')),
        ), $transport);
        $request = (new AuthRequest('fixture'))->setClient($client)->withoutCache();
        if ($variant === 'scope' && $i === 1) {
            $request = $request->withAuthScope('secondary');
        }
        expect($request->send()->raw()->isSuccess())->toBeTrue();
        $records = $transport->getRecorded();
        $shares = $variant === 'same' && $i === 1;
        expect($records)->toHaveCount($shares ? 1 : 2)
            ->and($records[array_key_last($records)]->headers['Authorization'])->toBe('Bearer token-' . ($shares ? 0 : $i));
    }
    foreach ($store->keys as $key) {
        expect($key)->toMatch('/^[a-f0-9]{64}$/D');
    }
})->with(['same', 'scope', 'tenant', 'base_path']);

it('не читает старый username key и не теряет локальный токен при cache write miss', function (): void {
    $store = new StrictCache();
    $store->set('auth_token_fixture', ['token' => 'legacy-token', 'expires_at' => time() + 600], 600);
    $store->failWrites = true;
    $transport = new MockTransport();
    $transport->fake([
        TokenLoginRequest::class => MockResponse::success(['accessToken' => 'fresh-token', 'expiresIn' => 600]),
        AuthRequest::class => MockResponse::success(['id' => 1, 'name' => 'fixture']),
    ]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', cacheConfig: new CacheConfig(store: $store),
        auth: new TokenAuthenticator('fixture', 'password', refreshRequestClass: TokenLoginRequest::class),
    ), $transport);
    $request = (new AuthRequest('fixture'))->setClient($client)->withoutCache();
    expect($request->send()->raw()->isSuccess())->toBeTrue()
        ->and($request->send()->raw()->isSuccess())->toBeTrue()
        ->and($transport->getRecorded())->toHaveCount(3)
        ->and($transport->getRecorded()[2]->headers['Authorization'])->toBe('Bearer fresh-token')
        ->and($store->get('auth_token_fixture')['token'])->toBe('legacy-token');
});

it('собственная auth разделяет token store только при объявленной identity', function (?string $identity): void {
    $store = new StrictCache();
    foreach ([0, 1] as $i) {
        $auth = new TokenCacheProbeAuthenticator($identity);
        $transport = new MockTransport();
        $transport->fake([AuthRequest::class => MockResponse::success(['id' => 1, 'name' => 'fixture'])]);
        $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', auth: $auth, cacheConfig: new CacheConfig(store: $store)), $transport);
        $request = (new AuthRequest('fixture'))->setClient($client)->withoutCache();
        expect($request->send()->raw()->isSuccess())->toBeTrue();
        if ($i === 0) {
            $auth->cache->set($auth->getCacheKey(), 'shared-token');
        } else {
            expect($transport->getRecorded()[0]->headers['Authorization'])->toBe('Bearer ' . ($identity === null ? 'empty' : 'shared-token'));
        }
    }
})->with([null, 'fixture-identity']);
