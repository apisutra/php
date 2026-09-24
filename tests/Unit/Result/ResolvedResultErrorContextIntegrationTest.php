<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Response\ClientResponse;
use ApiSutra\Response\ClientResponseFactoryInterface;
use ApiSutra\Result\ResolvedResultInterface;
use ApiSutra\Tests\Stubs\Core\TestClientErrorMapper;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Testing\MockResponse;
use ApiSutra\VO\Errors\ClientError;
use ApiSutra\VO\Errors\ErrorContextFactoryInterface;

function makeIntegrationErrorContextFactory(): ErrorContextFactoryInterface
{
    return new class implements ErrorContextFactoryInterface
    {
        public function make(ClientError $error): ?object
        {
            return (object) ['code' => $error->sdkCode->value];
        }
    };
}

function makeIntegrationResponseFactory(): ClientResponseFactoryInterface
{
    return new class implements ClientResponseFactoryInterface
    {
        public function make(ResolvedResultInterface $result): ClientResponse
        {
            return new ClientResponse(status: 200, headers: [], body: 'ok');
        }
    };
}

describe('ResolvedResult error context integration', function () {
    it('прокидывает errorContextFactory из ClientConfig', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::make(['message' => 'fail'], 500),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                errorMapper: new TestClientErrorMapper(),
                errorContextFactory: makeIntegrationErrorContextFactory(),
                responseFactory: makeIntegrationResponseFactory(),
            ),
            $transport,
        );

        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $context = $request->send()->resolved()->errorContext();

        expect($context?->code)->toBe('server_error');
    });
});
