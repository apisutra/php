<?php

declare(strict_types=1);

namespace ApiSutra\Retry;

use ApiSutra\Config\RetryConfig;
use ApiSutra\Contracts\Interfaces\Retry\RetryDelayPolicyInterface;
use ApiSutra\Enums\RateLimiting\BackoffStrategy;
use Closure;

final readonly class RetryDelayCalculator implements RetryDelayPolicyInterface
{
    private ?Closure $jitterResolver;

    public function __construct(?callable $jitterResolver = null)
    {
        $this->jitterResolver = $jitterResolver === null ? null : Closure::fromCallable($jitterResolver);
    }

    public function delayMs(RetryConfig $config, int $retryNumber): int
    {
        $delay = match ($config->backoff) {
            BackoffStrategy::Constant => $config->baseDelay,
            BackoffStrategy::Linear => $config->baseDelay * $retryNumber,
            BackoffStrategy::Exponential => $config->baseDelay * (2 ** ($retryNumber - 1)),
        };

        $delay = (int) min($delay, $config->maxDelay);

        if ($config->jitter) {
            $jitter = $this->jitterResolver !== null
                ? (int) ($this->jitterResolver)($config, $retryNumber)
                : random_int(0, (int) ($config->baseDelay * 0.5));
            $delay += min(max(0, $jitter), $config->maxDelay - $delay);
        }

        return min($delay, $config->maxDelay);
    }
}
