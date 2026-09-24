<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Pipeline\Transport\RetryConfigResolver;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Tests\Stubs\Requests\RetryableRequest;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;

describe('RetryConfigResolver', function () {
    it('создаёт конфиг при override attempts и отсутствии базового', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', retry: null, environment: Environment::Testing);
        $resolver = new RetryConfigResolver($config);

        $request = new SimpleGetRequest('q');
        $options = RequestOptions::empty()->withRetry(5);

        $retry = $resolver->resolve($request, $options);

        expect($retry)->toBeInstanceOf(RetryConfig::class);
        expect($retry?->attempts)->toBe(5);
    });

    it('возвращает null при withoutRetry', function () {
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            retry: new RetryConfig(attempts: 3),
            environment: Environment::Testing,
        );
        $resolver = new RetryConfigResolver($config);

        $request = new SimpleGetRequest('q');
        $options = RequestOptions::empty()->withoutRetry();

        $retry = $resolver->resolve($request, $options);

        expect($retry)->toBeNull();
    });

    it('runtime override имеет приоритет над атрибутом и конфигом', function () {
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            retry: new RetryConfig(attempts: 2),
            environment: Environment::Testing,
        );
        $resolver = new RetryConfigResolver($config);

        $request = new RetryableRequest('payload');
        $options = RequestOptions::empty()->withRetry(5);

        $retry = $resolver->resolve($request, $options);

        expect($retry)->toBeInstanceOf(RetryConfig::class);
        expect($retry?->attempts)->toBe(5);
    });
});
