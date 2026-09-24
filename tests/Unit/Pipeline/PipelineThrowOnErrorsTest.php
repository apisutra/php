<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Tests\Stubs\Requests\ThrowingCompositeRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

describe('Pipeline throwOnErrors', function () {
    it('возвращает ExecutionResult при исключении до flowRunner', function () {
        $transport = new MockTransport();
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            throwOnErrors: false,
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport);

        $request = new ThrowingCompositeRequest();
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->isFailed())->toBeTrue();
        expect($result->exception)->toBeInstanceOf(RuntimeException::class);
        expect($result->errors->first()?->message)->toBe('Ошибка построения composite');
    });

    it('пробрасывает исключение до flowRunner при throwOnErrors', function () {
        $transport = new MockTransport();
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            throwOnErrors: true,
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport);

        $request = new ThrowingCompositeRequest();
        $request->setClient($client);

        expect(fn () => $request->send())->toThrow(RuntimeException::class, 'Ошибка построения composite');
    });
});
