<?php

declare(strict_types=1);

use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Contracts\Interfaces\Auth\AuthLockLeaseInterface;
use ApiSutra\Contracts\Interfaces\Auth\AuthLockProviderInterface;
use ApiSutra\Exceptions\Auth\AuthRefreshLockTimeoutException;
use ApiSutra\Pipeline\Auth\AuthRefreshLock;
use ApiSutra\Pipeline\Auth\LocalAuthLockProvider;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Auth\LockAwareAuthenticator;
use ApiSutra\Tests\Stubs\Dto\TokenResponseDto;
use ApiSutra\Tests\Stubs\Requests\AuthRequest;
use ApiSutra\Tests\Stubs\Requests\RefreshTokenRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Tracing\MemoryLogger;
use ApiSutra\Tests\Support\ArrayCache;
use ApiSutra\Tests\Support\LockingCache;
use ApiSutra\Tests\Support\TestAuthLockProvider;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Transport\MockTransport;

it('старый владелец не освобождает новый lease после истечения TTL', function (): void {
    $clock = new VirtualClock();
    $provider = new LocalAuthLockProvider($clock);
    $first = $provider->acquire('fixture', 1);
    expect($provider->acquire('fixture', 1))->toBeNull();
    $clock->advance(1000);
    $second = $provider->acquire('fixture', 1);
    expect($first->release())->toBeFalse()
        ->and($provider->acquire('fixture', 1))->toBeNull()
        ->and($second->release())->toBeTrue()
        ->and($second->release())->toBeFalse()
        ->and($provider->acquire('fixture', 1))->not->toBeNull();
});

it('add-only cache автоматически использует локальный backend', function (): void {
    $cache = new LockingCache();
    $lock = new AuthRefreshLock($cache);
    $owner = $lock->acquire('fixture', 30);
    expect($cache->lastAddKey)->toBeNull();
    $lock->release('fixture', 'wrong');
    expect($lock->acquire('fixture', 30))->toBeNull();
    $lock->release('fixture', $owner);
    expect($lock->acquire('fixture', 30))->not->toBeNull();
});

it('исчерпание ожидания прекращает исполнение и сохраняет приоритет deadline', function (bool $deadline, bool $after401): void {
    $clock = new VirtualClock();
    $locks = new TestAuthLockProvider($clock);
    $locks->busy = true;
    $auth = new LockAwareAuthenticator();
    if ($after401) {
        $auth->processTokenResponse(new TokenResponseDto('old'));
    }
    $transport = new MockTransport();
    $transport->fake([AuthRequest::class => MockResponse::make('rejected', 401)]);
    $config = new ClientConfig(
        baseUrl: 'https://fixture.test', auth: $auth, timeout: 5,
        cacheConfig: new CacheConfig(locks: $locks),
        retry: new RetryConfig(totalTimeoutMs: $deadline ? 100 : null, retryExceptions: [Throwable::class]),
    );
    $client = new TestClient($config, $transport, $clock, $clock);
    $result = (new AuthRequest('fixture'))->setClient($client)->send()->raw();
    expect($result->errors->first()->code->value)->toBe('timeout')
        ->and($result->errors->first()->context['reason'])->toBe($deadline ? 'execution_deadline_exceeded' : 'auth_refresh_lock_timeout')
        ->and($transport->getRecorded())->toHaveCount($after401 ? 1 : 0)
        ->and($auth->refreshCalls)->toBe(1);
    if ($after401) {
        expect($result->response->status)->toBe(401);
    }
})->with([false, true])->with([false, true]);

it('ошибка lock backend не включает fallback и не раскрывает его сообщение', function (bool $release): void {
    $locks = new TestAuthLockProvider();
    $locks->failAcquire = !$release;
    $locks->failRelease = $release;
    $transport = new MockTransport();
    $transport->fake([RefreshTokenRequest::class => MockResponse::success(['token' => 'fixture'])]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', auth: new LockAwareAuthenticator(), cacheConfig: new CacheConfig(locks: $locks),
    ), $transport);
    $result = (new AuthRequest('fixture'))->setClient($client)->send()->raw();
    expect($result->errors->first()->code->value)->toBe('execution_error')
        ->and($result->errors->first()->context['reason'])->toBe('auth_lock_backend_error')
        ->and($result->errors->first()->message)->not->toContain('fixture-backend-secret')
        ->and($transport->getRecorded())->toHaveCount($release ? 1 : 0);
})->with([false, true]);

it('ошибка release сохраняет исходный отказ refresh', function (): void {
    $logger = new MemoryLogger();
    $locks = new TestAuthLockProvider();
    $locks->failRelease = true;
    $transport = new MockTransport();
    $transport->fake([RefreshTokenRequest::class => MockResponse::make('rejected', 401)]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', logger: $logger, authRetryOn401: false, auth: new LockAwareAuthenticator(), cacheConfig: new CacheConfig(locks: $locks),
    ), $transport);
    $result = (new AuthRequest('fixture'))->setClient($client)->send()->raw();
    expect($result->response->status)->toBe(401)
        ->and($result->errors->first()->code->value)->not->toBe('execution_error')
        ->and($locks->releases)->toBe(1)
        ->and($transport->getRecorded())->toHaveCount(1)
        ->and($result->nested)->toHaveCount(1)
        ->and($result->nested[0]->trace->traceId)->toBe($result->traceId)
        ->and($result->nested[0]->trace->parentExecutionId)->toBe($result->trace->executionId);
    $releaseLogs = array_values(array_filter($logger->records,
        static fn (array $record): bool => ($record['context']['event'] ?? null) === 'pipeline.failed_to_release_the_auth_lock'));
    expect($releaseLogs)->toHaveCount(1)
        ->and($releaseLogs[0]['context'])->toMatchArray($result->trace->logContext());
});

it('store с capability выбирается автоматически, явный locks имеет приоритет', function (bool $explicit): void {
    $cache = new class extends ArrayCache implements AuthLockProviderInterface {
        public int $calls = 0;
        public function acquire(string $key, int $ttlSeconds): ?AuthLockLeaseInterface
        {
            $this->calls++;
            return null;
        }
    };
    $provider = new TestAuthLockProvider();
    $config = new ClientConfig(baseUrl: 'https://fixture.test', cacheConfig: new CacheConfig(store: $cache, locks: $explicit ? $provider : null));
    $copy = $config->with(debug: true);
    $lock = new AuthRefreshLock($copy->cacheConfig->store, $copy->cacheConfig->locks);
    $lease = $lock->acquireLease('fixture', 5);
    expect($lease !== null)->toBe($explicit)->and($cache->calls)->toBe($explicit ? 0 : 1);
})->with([false, true]);

it('deadline после медленного захвата освобождает lease даже при ошибке release', function (): void {
    $clock = new VirtualClock();
    $locks = new TestAuthLockProvider($clock);
    $locks->failRelease = true;
    $locks->onAcquire = static fn () => $clock->advance(150);
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', auth: new LockAwareAuthenticator(), cacheConfig: new CacheConfig(locks: $locks),
        retry: new RetryConfig(totalTimeoutMs: 100),
    ), $transport, $clock, $clock);
    $result = (new AuthRequest('fixture'))->setClient($client)->send()->raw();
    expect($result->errors->first()->context['reason'])->toBe('execution_deadline_exceeded')
        ->and($locks->releases)->toBe(1)->and($transport->getRecorded())->toBe([]);
});

it('lock timeout сохраняет throwOnErrors и async контракт', function (bool $throws): void {
    $clock = new VirtualClock();
    $locks = new TestAuthLockProvider($clock);
    $locks->busy = true;
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', timeout: 5, auth: new LockAwareAuthenticator(),
        cacheConfig: new CacheConfig(locks: $locks), throwOnErrors: $throws,
    ), $transport, $clock, $clock);
    $request = (new AuthRequest('fixture'))->setClient($client);
    if ($throws) {
        expect(fn () => $request->sendAsync()->wait()->raw())->toThrow(AuthRefreshLockTimeoutException::class);
    } else {
        expect($request->sendAsync()->wait()->raw()->errors->first()->context['reason'])->toBe('auth_refresh_lock_timeout');
    }
    expect($transport->getRecorded())->toBe([]);
})->with([false, true]);
