<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Core\AbstractClient;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Extension\ExtensionConflictException;
use ApiSutra\Exceptions\Extension\ExtensionDisabledException;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Extensions\ConflictResponseExtension;
use ApiSutra\Tests\Stubs\Extensions\DisabledResponseExtension;
use ApiSutra\Tests\Stubs\Extensions\TestResponseExtension;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('ExtensionRegistry', function () {
    it('выбрасывает исключение при отключённом расширении', function () {
        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            extensions: [new DisabledResponseExtension()],
            environment: Environment::Testing,
        );

        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $client = new TestClient($config, $transport);
        $request = new SimpleGetRequest('payload');
        $request->setClient($client);

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );
        $response = new ProviderResponse(
            status: 200,
            headers: ['Content-Type' => ['application/json']],
            body: '{"ok":true}',
            request: new PreparedRequest(HttpMethod::GET, 'https://provider.test'),
            duration: 0,
        );

        $property = new ReflectionProperty(AbstractClient::class, 'extensions');
        $registry = $property->getValue($client);

        expect(fn () => $registry->resolveResponseHandler($response, $context))
            ->toThrow(ExtensionDisabledException::class);
    });

    it('блокирует конфликт обработчиков без override', function () {
        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            extensions: [
                new TestResponseExtension(),
                new ConflictResponseExtension(),
            ],
            environment: Environment::Testing,
        );

        $transport = new MockTransport();

        expect(fn () => new TestClient($config, $transport))
            ->toThrow(ExtensionConflictException::class);
    });
});
