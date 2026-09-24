<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Pipeline\Transport\DelayApplier;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;

describe('DelayApplier', function () {
    it('использует override из options', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', delay: 50, environment: Environment::Testing);
        $applier = new DelayApplier($config);

        $request = new SimpleGetRequest('q');
        $options = RequestOptions::empty()->withDelay(5);

        $start = microtime(true);
        $applier->apply($request, $options);
        $elapsed = microtime(true) - $start;

        expect($elapsed)->toBeGreaterThan(0.004);
    });

    it('при override 0 не задерживает выполнение', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', delay: 50, environment: Environment::Testing);
        $applier = new DelayApplier($config);

        $request = new SimpleGetRequest('q');
        $options = RequestOptions::empty()->withDelay(0);

        $start = microtime(true);
        $applier->apply($request, $options);
        $elapsed = microtime(true) - $start;

        expect($elapsed)->toBeLessThan(0.02);
    });
});
