<?php

declare(strict_types=1);

use ApiSutra\Config\RetryConfig;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

describe('RetryConfig validation', function () {
    it('разрешает дефолтные значения', function () {
        $config = new RetryConfig();

        expect($config->attempts)->toBeGreaterThan(0);
    });

    it('не принимает attempts < 1', function () {
        expect(fn () => new RetryConfig(attempts: 0))
            ->toThrow(ConfigurationException::class);
    });

    it('не принимает отрицательный baseDelay', function () {
        expect(fn () => new RetryConfig(baseDelay: -1))
            ->toThrow(ConfigurationException::class);
    });

    it('не принимает maxDelay меньше baseDelay', function () {
        expect(fn () => new RetryConfig(baseDelay: 100, maxDelay: 50))
            ->toThrow(ConfigurationException::class);
    });
});
