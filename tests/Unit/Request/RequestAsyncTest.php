<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Request\RequestExecution;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Contracts\Interfaces\Execution\ResultPromiseInterface;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\Requests\ThrowingCompositeRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

describe('Request async', function () {
    it('AbstractRequest::sendAsync возвращает Promise с результатом', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'A']),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $handle = $request->sendAsync();

        expect($handle)->toBeInstanceOf(ResultPromiseInterface::class);

        $result = $handle->wait()->raw();
        expect($result->data)->toBeInstanceOf(SimpleResponseDto::class);
        expect($result->data->id)->toBe(1);
    });

    it('RequestExecution::sendAsync возвращает Promise с результатом', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['id' => 2, 'name' => 'B']),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $execution = new RequestExecution(
            request: $request,
            options: RequestOptions::empty(),
        );

        $result = $execution->sendAsync()->wait()->raw();

        expect($result->data)->toBeInstanceOf(SimpleResponseDto::class);
        expect($result->data->id)->toBe(2);
    });

    it('возвращает failed результат при ошибке', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::serverError(),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $result = $request->sendAsync()->wait()->raw();

        expect($result->isFailed())->toBeTrue();
    });

    it('rejection при throwOnErrors', function () {
        $transport = new MockTransport();
        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                throwOnErrors: true,
                environment: Environment::Testing,
            ),
            $transport,
        );

        $request = new ThrowingCompositeRequest();
        $request->setClient($client);

        expect(fn () => $request->sendAsync()->wait()->raw())
            ->toThrow(RuntimeException::class);
    });
});
