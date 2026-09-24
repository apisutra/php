<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Tests\Stubs\ProviderA\Dto\ProviderASyncResponseDto;
use ApiSutra\Tests\Stubs\ProviderA\Enums\ProviderAResultCode;
use ApiSutra\Tests\Stubs\ProviderA\Requests\ProviderABasicCheckRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

describe('Fixtures loading', function () {
    it('загружает фикстуру по request.class', function () {
        $transport = new MockTransport();
        $transport->loadFixtures(__DIR__ . '/../../Fixtures/provider-a');

        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new ProviderABasicCheckRequest('123');
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->data)->toBeInstanceOf(ProviderASyncResponseDto::class);
        expect($result->data->resultCode)->toBe(ProviderAResultCode::Ok);
        expect($result->data->operationToken)->toBe('op-001');
    });
});
