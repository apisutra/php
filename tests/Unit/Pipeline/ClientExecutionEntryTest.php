<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Tests\Stubs\Requests\InvalidCompositeRequest;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\Requests\ThrowingCompositeRequest;
use ApiSutra\Tests\Stubs\Requests\ThrowingEndpointRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;

describe('Канонический вход исполнения', function () {
    it('прямой executor выполняет ту же валидацию до composite', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'User']),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $pipeline = $client->execution();
        $request = new InvalidCompositeRequest();
        $request->setClient($client);

        $result = $pipeline->execute($request);

        expect($result->isFailed())->toBeTrue()
            ->and($result->trace)->not->toBeNull()
            ->and($transport->getRecorded())->toHaveCount(0);
    });

    it('прямой executor не обходит декларацию составного запроса', function () {
        $transport = new MockTransport();
        $transport->fake([
            InvalidCompositeRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $pipeline = $client->execution();
        $request = new InvalidCompositeRequest();
        $request->setClient($client);

        $result = $pipeline->execute($request);

        expect($result->isFailed())->toBeTrue()
            ->and($result->trace)->not->toBeNull()
            ->and($transport->getRecorded())->toHaveCount(0);
    });

    it('останавливается на ошибке в composite и не вызывает транспорт', function () {
        $transport = new MockTransport();
        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $pipeline = $client->execution();
        $request = new ThrowingCompositeRequest();
        $request->setClient($client);

        $result = $pipeline->execute($request);

        expect($result->isFailed())->toBeTrue()
            ->and($transport->getRecorded())->toHaveCount(0);
    });

    it('останавливается на ошибке подготовки и не вызывает транспорт', function () {
        $transport = new MockTransport();
        $transport->fake([
            ThrowingEndpointRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $pipeline = $client->execution();
        $request = new ThrowingEndpointRequest();
        $request->setClient($client);

        $result = $pipeline->execute($request);

        expect($result->isFailed())->toBeTrue()
            ->and($transport->getRecorded())->toHaveCount(0);
    });
});
