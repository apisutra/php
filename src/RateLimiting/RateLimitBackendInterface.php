<?php

declare(strict_types=1);

namespace ApiSutra\RateLimiting;

interface RateLimitBackendInterface
{
    /**
     * Получить весь набор без ожидания квоты; denied не расходует ни одной квоты.
     * Исключение может означать неизвестный исход записи: повтор или refund не предполагается.
     *
     * @param list<RateLimitQuota> $quotas
     * @param int|null $timeoutMs Доступная длительность обращения в миллисекундах.
     */
    public function tryAcquire(array $quotas, ?int $timeoutMs = null): RateLimitDecision;
}
