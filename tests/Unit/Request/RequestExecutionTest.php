<?php

declare(strict_types=1);

use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Request\RequestExecution;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Support\ContainerProviderRegistry;
use ApiSutra\Support\NullContainerProvider;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;

beforeEach(function () {
    ContainerProviderRegistry::set(new NullContainerProvider());
});

describe('RequestExecution', function () {
    it('выбрасывает исключение без клиента', function () {
        $execution = new RequestExecution(
            request: new SimpleGetRequest('q'),
            options: RequestOptions::empty(),
        );

        expect(fn () => $execution->send())
            ->toThrow(ConfigurationException::class);
    });

    it('выбрасывает исключение без клиента при sendAsync', function () {
        $execution = new RequestExecution(
            request: new SimpleGetRequest('q'),
            options: RequestOptions::empty(),
        );

        expect(fn () => $execution->sendAsync()->wait())
            ->toThrow(ConfigurationException::class);
    });
});
