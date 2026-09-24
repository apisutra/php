<?php

declare(strict_types=1);

use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\RateLimiting\RateLimitBackendException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\RateLimiting\RateLimiter;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Tests\Support\ArrayCache;
use ApiSutra\Tests\Support\FailingRateLimitStore;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Timing\ExecutionBudget;

it('отклоняет недопустимый лимит при создании конфига и runtime options', function (int $limit, int $period): void {
    expect(fn () => new RateLimitConfig(limit: $limit, period: $period))->toThrow(ConfigurationException::class)
        ->and(fn () => RequestOptions::empty()->withRateLimit($limit, $period))->toThrow(ConfigurationException::class);
})->with([[0, 60], [-1, 60], [1, 0], [1, -1], [1, PHP_INT_MAX], [1, intdiv(PHP_INT_MAX, 1_000_000) + 1]]);

it('отклоняет переполнение конца окна до записи', function (): void {
    $clock = new VirtualClock();
    $clock->wallTime = PHP_INT_MAX - 1;
    $store = new FailingRateLimitStore();
    expect(fn () => (new RateLimiter($clock, $clock))->acquire(new RateLimitConfig(store: $store), 'quota'))
        ->toThrow(ConfigurationException::class);
    expect($store->writes)->toBe(0)->and($clock->waits)->toBe([]);
});

it('после пробуждения сохраняет чужую квоту и перечитывает окно', function (bool $deadline): void {
    $clock = new VirtualClock();
    $store = new ArrayCache();
    $store->set('quota', ['count' => 1, 'reset' => $clock->unixTime() + 1]);
    $sleeper = new class($clock, $store) implements SleeperInterface {
        public function __construct(private VirtualClock $clock, private ArrayCache $store) {}
        public function sleepMs(int $milliseconds): void
        {
            $this->clock->sleepMs($milliseconds);
            if (count($this->clock->waits) <= 2) {
                $this->store->set('quota', ['count' => 1, 'reset' => $this->clock->unixTime() + 2]);
            }
        }
    };
    $limiter = new RateLimiter($clock, $sleeper);
    $config = new RateLimitConfig(limit: 1, period: 2, store: $store);
    $budget = new ExecutionBudget($clock, $deadline ? 1500 : null);
    if ($deadline) {
        expect(fn () => $limiter->acquire($config, 'quota', $budget))->toThrow(ExecutionDeadlineException::class);
        expect($clock->waits)->toBe([1000]);
    } else {
        $limiter->acquire($config, 'quota', $budget);
        expect($clock->waits)->toBe([1000, 2000, 2000]);
    }
    expect($store->get('quota'))->toBe(['count' => 1, 'reset' => $clock->unixTime() + 2]);
})->with([false, true]);

it('не подставляет локальную память при cache miss во внешнем store', function (): void {
    $clock = new VirtualClock();
    $limiter = new RateLimiter($clock, $clock);
    $limiter->acquire(new RateLimitConfig(limit: 1, behavior: RateLimitBehavior::Throw), 'quota');
    $store = new ArrayCache();
    $limiter->acquire(new RateLimitConfig(limit: 1, behavior: RateLimitBehavior::Throw, store: $store), 'quota');
    expect($store->get('quota')['count'])->toBe(1)->and($clock->waits)->toBe([]);
});

it('не выдаёт разрешение после ошибки backend и сохраняет previous', function (string $failure): void {
    $store = new FailingRateLimitStore();
    $store->failure = $failure;
    $clock = new VirtualClock();
    try {
        (new RateLimiter($clock, $clock))->acquire(new RateLimitConfig(store: $store), 'quota');
        test()->fail('Лимитер выдал разрешение');
    } catch (RateLimitBackendException $exception) {
        expect($exception->getMessage())->not->toContain('fixture-store-secret');
        if ($failure === 'false') {
            expect($exception->getPrevious())->toBeNull();
        } else {
            expect($exception->getPrevious()?->getMessage())->toBe('fixture-store-secret');
        }
    }
    expect($store->reads)->toBe(1)->and($store->writes)->toBe($failure === 'read' ? 0 : 1)
        ->and($clock->waits)->toBe([]);
})->with(['read', 'write', 'false']);

it('проверяет бюджет после подтверждённой записи без второго списания', function (): void {
    $clock = new VirtualClock();
    $store = new class($clock) extends ArrayCache {
        public int $writes = 0;
        public function __construct(private VirtualClock $clock) {}
        public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
        {
            $this->writes++;
            $this->clock->advance(100);
            return parent::set($key, $value, $ttl);
        }
    };
    expect(fn () => (new RateLimiter($clock, $clock))->acquire(
        new RateLimitConfig(store: $store), 'quota', new ExecutionBudget($clock, 100),
    ))->toThrow(ExecutionDeadlineException::class);
    expect($store->writes)->toBe(1)->and($store->get('quota')['count'])->toBe(1);
});

it('отклоняет непредставимое ожидание из общего счётчика до sleeper', function (): void {
    $clock = new VirtualClock();
    $store = new ArrayCache();
    $store->set('quota', ['count' => 1, 'reset' => $clock->unixTime() + intdiv(PHP_INT_MAX, 1_000_000) + 1]);
    expect(fn () => (new RateLimiter($clock, $clock))->acquire(new RateLimitConfig(limit: 1, store: $store), 'quota'))
        ->toThrow(ConfigurationException::class);
    expect($clock->waits)->toBe([]);
});
