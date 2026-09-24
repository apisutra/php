<?php

declare(strict_types=1);

use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\RateLimiting\BackoffStrategy;
use ApiSutra\Retry\RetryDelayCalculator;

describe('RetryDelayCalculator delay', function () {
    it('не превышает maxDelay при jitter', function () {
        $config = new RetryConfig(
            attempts: 1,
            baseDelay: 1000,
            maxDelay: 1000,
            backoff: BackoffStrategy::Constant,
            jitter: true,
        );

        $calculator = new RetryDelayCalculator();

        $delay = $calculator->delayMs($config, 1);

        expect($delay)->toBeLessThanOrEqual(1000);
    });

    it('не ограничивает jitter если maxDelay выше base', function () {
        $config = new RetryConfig(
            attempts: 1,
            baseDelay: 100,
            maxDelay: 10000,
            backoff: BackoffStrategy::Constant,
            jitter: true,
        );

        $calculator = new RetryDelayCalculator();

        $delay = $calculator->delayMs($config, 1);

        expect($delay)->toBeGreaterThanOrEqual(100);
        expect($delay)->toBeLessThanOrEqual(10000);
    });

    it('ограничивает delay по maxDelay при backoff', function () {
        $config = new RetryConfig(
            attempts: 1,
            baseDelay: 1000,
            maxDelay: 1500,
            backoff: BackoffStrategy::Exponential,
            jitter: false,
        );

        $calculator = new RetryDelayCalculator();

        $delay = $calculator->delayMs($config, 2);

        expect($delay)->toBe(1500);
    });

    it('рассчитывает constant backoff', function () {
        $config = new RetryConfig(
            attempts: 1,
            baseDelay: 250,
            maxDelay: 1000,
            backoff: BackoffStrategy::Constant,
            jitter: false,
        );

        $calculator = new RetryDelayCalculator();

        $delay = $calculator->delayMs($config, 3);

        expect($delay)->toBe(250);
    });

    it('рассчитывает linear backoff', function () {
        $config = new RetryConfig(
            attempts: 1,
            baseDelay: 100,
            maxDelay: 1000,
            backoff: BackoffStrategy::Linear,
            jitter: false,
        );

        $calculator = new RetryDelayCalculator();

        $delay = $calculator->delayMs($config, 3);

        expect($delay)->toBe(300);
    });

    it('рассчитывает exponential backoff', function () {
        $config = new RetryConfig(
            attempts: 1,
            baseDelay: 100,
            maxDelay: 1000,
            backoff: BackoffStrategy::Exponential,
            jitter: false,
        );

        $calculator = new RetryDelayCalculator();

        $delay = $calculator->delayMs($config, 3);

        expect($delay)->toBe(400);
    });

    it('применяет jitter через resolver', function () {
        $config = new RetryConfig(
            attempts: 1,
            baseDelay: 100,
            maxDelay: 1000,
            backoff: BackoffStrategy::Constant,
            jitter: true,
        );

        $calculator = new RetryDelayCalculator(static fn (): int => 50);

        $delay = $calculator->delayMs($config, 1);

        expect($delay)->toBe(150);
    });
});
