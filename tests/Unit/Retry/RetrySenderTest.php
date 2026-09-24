<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\RetryableRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

describe('RetrySender', function () {
    it('повторяет запрос при ошибке и возвращает успешный ответ', function () {
        $transport = new MockTransport();
        $transport->fake([
            RetryableRequest::class => MockResponse::sequence([
                MockResponse::serverError(),
                MockResponse::success(['value' => 1]),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new RetryableRequest('payload');
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->data)->toBe(['value' => 1]);
        expect($transport->getRecorded())->toHaveCount(2);
    });
});
