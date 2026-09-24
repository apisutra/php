<?php

declare(strict_types=1);

use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Resolver\DiscoveryOptions;

describe('DiscoveryOptions', function () {
    it('включает кеш в prod и staging по умолчанию', function () {
        $options = DiscoveryOptions::auto();

        expect($options->isCacheEnabled(Environment::Production))->toBeTrue();
        expect($options->isCacheEnabled(Environment::Staging))->toBeTrue();
        expect($options->isCacheEnabled(Environment::Local))->toBeFalse();
        expect($options->isCacheEnabled(Environment::Testing))->toBeFalse();
    });

    it('принудительно включает кеш', function () {
        $options = DiscoveryOptions::forceOn();

        expect($options->isCacheEnabled(Environment::Production))->toBeTrue();
        expect($options->isCacheEnabled(Environment::Local))->toBeTrue();
    });

    it('принудительно выключает кеш', function () {
        $options = DiscoveryOptions::forceOff();

        expect($options->isCacheEnabled(Environment::Production))->toBeFalse();
        expect($options->isCacheEnabled(Environment::Local))->toBeFalse();
    });
});
