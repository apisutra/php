<?php

declare(strict_types=1);

namespace ApiSutra\RateLimiting;

use ApiSutra\Localization\Message;
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\RateLimiting\RateLimitBackendException;
use ApiSutra\Exceptions\Request\RateLimitException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\RateLimiting\Backends\LocalRateLimitBackend;
use ApiSutra\Timing\ExecutionBudget;
use ApiSutra\Timing\SystemClock;
use ApiSutra\Timing\CooperativeSleeper;
use Psr\SimpleCache\CacheInterface;
use Throwable;
use ApiSutra\Execution\Admission\AdmissionScope;

/**
 * Лимитер запросов с in-memory и/или PSR-16 хранилищем.
 *
 * Нюансы:
 * - Без внешнего store лимиты хранятся в памяти экземпляра (per-client).
 * - Ключ лимита формируется снаружи и должен включать нужный scope (например, baseUrl/endpoint).
 * - Не следует хешировать параметры запроса по умолчанию, чтобы не размазывать лимиты.
 * - Store позволяет обмениваться счётчиком; get/set не обеспечивают атомарную квоту.
 */
final class RateLimiter
{
    /**
     * @var array<string, array{count: int, reset: int}> Локальное состояние лимитов
     */
    private array $memory = [];

    public function __construct(
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly SleeperInterface $sleeper = new CooperativeSleeper(),
        private ?RateLimitBackendInterface $backend = null,
    ) {
    }

    /**
     * Один цикл ожидания для всех атомарных backend.
     *
     * @internal Используется pipeline после разрешения конфигурации квот.
     * @param list<RateLimitQuota> $quotas
     * @param array<string, RateLimitBehavior> $behaviors
     */
    public function acquireAll(array $quotas, array $behaviors, ?ExecutionBudget $budget = null): void
    {
        $quotas = RateLimitQuota::normalize($quotas);
        if ($quotas === []) {
            return;
        }
        $budget ??= new ExecutionBudget($this->clock);
        $known = [];
        foreach ($quotas as $quota) {
            if (!isset($behaviors[$quota->key])) {
                throw new ConfigurationException(new Message('ratelimiting.quota_behavior_is_not_specified'));
            }
            $known[$quota->key] = true;
        }
        $backend = $this->backend ??= new LocalRateLimitBackend($this->clock);
        while (true) {
            $budget->check('rate_limit');
            try {
                $decision = $backend->tryAcquire($quotas, $budget->remainingMs());
            } catch (Throwable $exception) {
                $budget->check('rate_limit_store', $exception);
                if ($exception instanceof ConfigurationException || $exception instanceof ExecutionDeadlineException) {
                    throw $exception;
                }
                throw $exception instanceof RateLimitBackendException
                    ? $exception
                    : new RateLimitBackendException($exception);
            }
            $budget->check('rate_limit_store');
            if ($decision->granted) {
                return;
            }
            $throw = false;
            foreach ($decision->blockedIds as $id) {
                if (!isset($known[$id])) {
                    throw new RateLimitBackendException();
                }
                $throw = $throw || $behaviors[$id] === RateLimitBehavior::Throw;
            }
            if ($decision->retryAfterMs > intdiv(PHP_INT_MAX, 1000)) {
                throw new RateLimitBackendException();
            }
            AdmissionScope::refuse('local_rate_limit_exceeded', $decision->retryAfterMs, new RateLimitException(
                new Message('ratelimiting.rate_limit_exceeded'),
                null,
                (int) ceil($decision->retryAfterMs / 1000),
            ));
            if ($throw) {
                throw new RateLimitException(
                    new Message('ratelimiting.rate_limit_exceeded'),
                    null,
                    intdiv($decision->retryAfterMs, 1000) + ($decision->retryAfterMs % 1000 === 0 ? 0 : 1),
                );
            }
            $budget->wait($decision->retryAfterMs, $this->sleeper, 'rate_limit_wait');
        }
    }

    /**
     * Получить слот лимита по ключу или применить ожидание/исключение.
     */
    public function acquire(RateLimitConfig $config, string $key, ?ExecutionBudget $budget = null): void
    {
        $budget ??= new ExecutionBudget($this->clock);
        $store = $config->store;

        while (true) {
            $budget->check('rate_limit');
            $now = $budget->clock->unixTime();
            $data = $this->loadState($store, $key, $config->period, $now);
            $budget->check('rate_limit_store');
            if ($this->consumeIfAvailable($store, $key, $data, $config->limit, $config->period)) {
                $budget->check('rate_limit_store');
                return;
            }

            // После ожидания другой участник уже мог занять новое окно.
            $this->handleLimitExceeded($config, $data, $now, $budget);
        }
    }

    /**
     * Загрузить состояние лимита из store или локальной памяти.
     *
     * @return array{count: int, reset: int}
     */
    private function loadState(?CacheInterface $store, string $key, int $period, int $now): array
    {
        try {
            $data = $store !== null ? $store->get($key) : ($this->memory[$key] ?? null);
        } catch (Throwable $exception) {
            throw new RateLimitBackendException($exception);
        }
        if (!is_array($data) || ($data['reset'] ?? 0) <= $now) {
            return $this->startNewWindow($period, $now);
        }

        return $data;
    }

    /**
     * Попробовать списать лимит. Возвращает true при успехе.
     *
     * @param array{count: int, reset: int} $data
     */
    private function consumeIfAvailable(?CacheInterface $store, string $key, array $data, int $limit, int $period): bool
    {
        if ($data['count'] >= $limit) {
            return false;
        }

        // Лимит ещё не исчерпан
        $data['count']++;
        $this->store($store, $key, $data, $period);

        return true;
    }

    /**
     * Обработать превышение лимита: исключение или ожидание.
     *
     * @param array{count: int, reset: int} $data
     */
    private function handleLimitExceeded(RateLimitConfig $config, array $data, int $now, ExecutionBudget $budget): void
    {
        AdmissionScope::refuse('local_rate_limit_exceeded', max(0, $data['reset'] - $now) * 1000, $this->buildException($data, $now));
        if ($config->behavior === RateLimitBehavior::Throw) {
            // Явная ошибка при превышении лимита
            throw $this->buildException($data, $now);
        }

        $sleepFor = max(0, $data['reset'] - $now);
        if ($sleepFor > 0) {
            if ($sleepFor > intdiv(PHP_INT_MAX, 1_000_000)) {
                throw new ConfigurationException(new Message('ratelimiting.rate_limit_wait_must_be_representable_in_microseconds_for'));
            }
            // Ожидание следующего окна лимита
            $budget->wait($sleepFor * 1000, $this->sleeper, 'rate_limit_wait');
        }
    }

    /**
     * Сформировать исключение о превышении лимита.
     *
     * @param array{count: int, reset: int} $data
     */
    private function buildException(array $data, int $now): RateLimitException
    {
        return new RateLimitException(
            new Message('ratelimiting.rate_limit_exceeded'),
            null,
            max(0, $data['reset'] - $now),
        );
    }

    /**
     * Создать новое окно лимита.
     *
     * @return array{count: int, reset: int}
     */
    private function startNewWindow(int $period, int $now): array
    {
        if ($now > PHP_INT_MAX - $period) {
            throw new ConfigurationException(new Message('ratelimiting.ratelimitconfig_period_must_allow_computing_the_window_end_without'));
        }
        return ['count' => 0, 'reset' => $now + $period];
    }

    /**
     * Сохранить состояние лимита в store или локально.
     *
     * @param array{count: int, reset: int} $data
     */
    private function store(?CacheInterface $store, string $key, array $data, int $ttl): void
    {
        if ($store !== null) {
            try {
                $saved = $store->set($key, $data, $ttl);
            } catch (Throwable $exception) {
                throw new RateLimitBackendException($exception);
            }
            if (!$saved) {
                throw new RateLimitBackendException();
            }
            return;
        }

        $this->memory[$key] = $data;
    }
}
