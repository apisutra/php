<?php

declare(strict_types=1);

use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Continuation\ContinuationMode;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Exceptions\Configuration\ContinuationConfigurationException;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Serialization\Serializer;
use ApiSutra\Tests\Stubs\Continuation\TestContinuationModeApplicator;
use ApiSutra\Tests\Stubs\Requests\ContinuationModeRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('ContinuationModeApplicator', function () {
    $serializerFactory = static fn (): Serializer => new Serializer(new CastRegistry());

    $contextFactory = static function (
        object $request,
        ClientConfig $config,
        ?RequestOptions $options = null,
    ): PipelineContext {
        return new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
            options: $options,
        );
    };

    it('добавляет provider mode-флаг в query через applicator', function () use ($serializerFactory, $contextFactory) {
        $serializer = $serializerFactory();
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            environment: Environment::Testing,
            continuationModeApplicator: new TestContinuationModeApplicator(),
        );
        $request = new ContinuationModeRequest();
        $options = RequestOptions::empty()->withContinuationMode(ContinuationMode::Async);

        $prepared = $serializer->serialize($request, $contextFactory($request, $config, $options));

        expect($prepared->meta['query']['provider_async']['value'] ?? null)->toBeTrue()
            ->and($prepared->meta['continuationMode'] ?? null)->toBe('async');
    });

    it('бросает continuation configuration exception при конфликте mode и ручного флага', function () use ($serializerFactory, $contextFactory) {
        $serializer = $serializerFactory();
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            environment: Environment::Testing,
            continuationModeApplicator: new TestContinuationModeApplicator(),
        );
        $request = new ContinuationModeRequest(manual_async: true);
        $options = RequestOptions::empty()->withContinuationMode(ContinuationMode::Sync);

        expect(fn () => $serializer->serialize($request, $contextFactory($request, $config, $options)))
            ->toThrow(ContinuationConfigurationException::class, 'Конфликт mode и ручного provider-флага async');
    });

    it('использует defaultContinuationMode из ClientConfig при отсутствии runtime override', function () use ($serializerFactory, $contextFactory) {
        $serializer = $serializerFactory();
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            environment: Environment::Testing,
            defaultContinuationMode: ContinuationMode::Async,
            continuationModeApplicator: new TestContinuationModeApplicator(),
        );
        $request = new ContinuationModeRequest();

        $prepared = $serializer->serialize($request, $contextFactory($request, $config));

        expect($prepared->meta['query']['provider_async']['value'] ?? null)->toBeTrue()
            ->and($prepared->meta['continuationMode'] ?? null)->toBe('async');
    });
});
