<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\BodyRootPatchListRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

describe('BodyRoot request debug', function () {
    it('показывает корректный raw body для root list', function () {
        $transport = new MockTransport();
        $transport->fake([
            BodyRootPatchListRequest::class => MockResponse::success(['ok' => true]),
        ]);
        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                debug: true,
                environment: Environment::Testing,
            ),
            $transport,
        );

        $operations = [
            ['op' => 'replace', 'path' => '/params', 'value' => ['enabled' => true]],
        ];
        $request = new BodyRootPatchListRequest(
            id: '77',
            dryRun: true,
            mode: 'patch',
            operations: $operations,
        );
        $request->setClient($client);

        $debug = $request->send()->requestDebug();
        $decoded = json_decode($debug['bodyRaw'] ?? '', true);

        expect($debug)->not->toBeNull()
            ->and($debug['method'] ?? null)->toBe('PATCH')
            ->and($debug['bodyRaw'] ?? null)->toStartWith('[')
            ->and($decoded)->toBe($operations);
    });
});
