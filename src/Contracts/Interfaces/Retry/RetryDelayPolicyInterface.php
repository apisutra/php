<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Retry;

use ApiSutra\Config\RetryConfig;

/** Политика только вычисляет backoff: без I/O, ожидания и отправки HTTP. */
interface RetryDelayPolicyInterface
{
    /** Номер повтора начинается с 1; результат — неотрицательные миллисекунды. */
    public function delayMs(RetryConfig $config, int $retryNumber): int;
}
