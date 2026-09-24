<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\OneOfAtLeastOneRequest;
use ApiSutra\Tests\Stubs\Requests\OneOfNestedSignatureRequest;
use ApiSutra\Tests\Stubs\Requests\OneOfSignatureRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

describe('Request oneOf contract', function () {
    it('останавливает запрос до транспорта при нарушении контракта', function () {
        $transport = new MockTransport();
        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                debug: true,
                environment: Environment::Testing,
            ),
            $transport,
        );

        $request = new OneOfSignatureRequest(
            type: 'KONTUR_UC',
            contents: 'payload',
            certificateId: null,
            goskeyData: null,
        );
        $request->setClient($client);

        $result = $request->send()->raw();
        $error = $result->errors->first();

        expect($result->isFailed())->toBeTrue()
            ->and($error?->code)->toBe(ErrorCode::RequestContractViolation)
            ->and($error?->context['contract'] ?? null)->toBe('signature_payload')
            ->and(is_array($error?->context['violations'] ?? null))->toBeTrue();

        $transport->assertNothingSent();
    });

    it('добавляет oneOf диагностику в requestDebug при успешной отправке', function () {
        $transport = new MockTransport();
        $transport->fake([
            OneOfSignatureRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                debug: true,
                environment: Environment::Testing,
            ),
            $transport,
        );

        $request = new OneOfSignatureRequest(
            type: 'KONTUR_UC',
            contents: 'payload',
            certificateId: 'cert-1',
            goskeyData: null,
        );
        $request->setClient($client);

        $debug = $request->send()->requestDebug();

        expect($debug)->not->toBeNull()
            ->and($debug['oneOf']['contract'] ?? null)->toBe('signature_payload')
            ->and($debug['oneOf']['matchedVariant'] ?? null)->toBe('cloudcrypt')
            ->and($debug['oneOf']['discriminator']['field'] ?? null)->toBe('type')
            ->and($debug['oneOf']['discriminator']['variant'] ?? null)->toBe('cloudcrypt');
    });

    it('поддерживает AtLeastOne с несколькими вариантами при успешной отправке', function () {
        $transport = new MockTransport();
        $transport->fake([
            OneOfAtLeastOneRequest::class => MockResponse::success(['ok' => true]),
        ]);
        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                debug: true,
                environment: Environment::Testing,
            ),
            $transport,
        );

        $request = new OneOfAtLeastOneRequest(
            email: 'a@test.local',
            phone: '+79001234567',
        );
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->isSuccess())->toBeTrue();
    });

    it('показывает oneOf диагностику для dot-path контракта', function () {
        $transport = new MockTransport();
        $transport->fake([
            OneOfNestedSignatureRequest::class => MockResponse::success(['ok' => true]),
        ]);
        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                debug: true,
                environment: Environment::Testing,
            ),
            $transport,
        );

        $request = new OneOfNestedSignatureRequest(
            type: 'KONTUR_UC',
            signature: [
                'contents' => 'payload',
                'certificateId' => 'cert-1',
            ],
        );
        $request->setClient($client);

        $debug = $request->send()->requestDebug();

        expect($debug['oneOf']['contract'] ?? null)->toBe('signature_payload_nested')
            ->and($debug['oneOf']['matchedVariant'] ?? null)->toBe('cloudcrypt');
    });
});
