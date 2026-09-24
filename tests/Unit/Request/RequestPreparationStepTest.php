<?php

declare(strict_types=1);

use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Pipeline\Flow\RequestPreparationStep;
use ApiSutra\Pipeline\Preparation\PreparedRequestFactory;
use ApiSutra\Pipeline\Preparation\RequestPreparer;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Serialization\Serializer;
use ApiSutra\Tests\Stubs\Dto\OutputAddressDto;
use ApiSutra\Tests\Stubs\Dto\OutputItemDto;
use ApiSutra\Tests\Stubs\Dto\OutputUserDto;
use ApiSutra\Tests\Stubs\Requests\DtoBodyRequest;
use ApiSutra\Tests\Stubs\Requests\IdempotentRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('RequestPreparationStep', function () {
    it('готовит запрос и применяет overrides', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $preparer = new RequestPreparer($config);
        $step = new RequestPreparationStep(
            new PreparedRequestFactory(new Serializer(new CastRegistry()), $preparer),
            new AuditLogger($config),
        );

        $request = new IdempotentRequest();
        $options = RequestOptions::empty()
            ->withHeader('X-Extra', '2')
            ->withIdempotencyKey('idem-key');

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
            options: $options,
        );

        $prepared = $step->prepare($request, $context);

        expect($prepared->headers['X-Extra'] ?? null)->toBe('2');
        expect($prepared->headers['X-Idempotency'] ?? null)->toBe('idem-key');
        expect($context->preparedRequest)->toBe($prepared);
    });

    it('готовит запрос с DTO body через DtoSerializer', function () {
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            environment: Environment::Testing,
        );
        $preparer = new RequestPreparer($config);
        $step = new RequestPreparationStep(
            new PreparedRequestFactory(new Serializer(new CastRegistry()), $preparer),
            new AuditLogger($config),
        );

        $request = new DtoBodyRequest(new OutputUserDto(
            userId: 20,
            address: new OutputAddressDto('Omsk', '644000'),
            items: [new OutputItemDto(5, 'Fifth')],
            title: 'hello',
            tags: ['x'],
            plainValue: null,
        ));

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $step->prepare($request, $context);
        $body = json_decode($prepared->body ?? '', true);

        expect($body['payload']['user_id'] ?? null)->toBe(20);
        expect($body['payload']['profile']['city'] ?? null)->toBe('Omsk');
        expect($body['payload']['items'][0]['label'] ?? null)->toBe('Fifth');
    });
});
