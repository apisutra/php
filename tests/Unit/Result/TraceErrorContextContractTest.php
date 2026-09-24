<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Tests\Stubs\Core\TestClientErrorMapper;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Errors\ClientError;
use ApiSutra\VO\Errors\ErrorContextFactoryInterface;
use ApiSutra\VO\Errors\SystemErrorContextKeys;

function makeTraceContextFactory(): ErrorContextFactoryInterface
{
    return new class implements ErrorContextFactoryInterface
    {
        public function make(ClientError $error): ?object
        {
            $traceId = $error->context[SystemErrorContextKeys::TraceId->value] ?? null;

            return (object) ['traceId' => is_string($traceId) ? $traceId : null];
        }
    };
}

describe('Trace/error context contract', function () {
    it('request trace переопределяет client trace', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::serverError(),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                errorMapper: new TestClientErrorMapper(),
                errorContextFactory: makeTraceContextFactory(),
            ),
            $transport,
        );
        $client->setTraceId('trace-client');

        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $resolved = $request->withTraceId('trace-request')->send()->resolved();
        $error = $resolved->error();
        $context = $resolved->errorContext();

        expect($error?->context[SystemErrorContextKeys::TraceId->value] ?? null)->toBe('trace-request')
            ->and($context?->traceId)->toBe('trace-request');
    });

    it('client trace используется по умолчанию', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::serverError(),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                errorMapper: new TestClientErrorMapper(),
                errorContextFactory: makeTraceContextFactory(),
            ),
            $transport,
        );
        $client->setTraceId('trace-client');

        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $resolved = $request->send()->resolved();
        $error = $resolved->error();
        $context = $resolved->errorContext();

        expect($error?->context[SystemErrorContextKeys::TraceId->value] ?? null)->toBe('trace-client')
            ->and($context?->traceId)->toBe('trace-client');
    });
});
