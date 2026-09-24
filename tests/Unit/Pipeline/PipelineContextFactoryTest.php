<?php

declare(strict_types=1);

use ApiSutra\Attributes\AttributeRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Enums\Pipeline\PipelineStage;
use ApiSutra\Pipeline\Attributes\StageProcessor;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Pipeline\Flow\PipelineContextFactory;
use ApiSutra\Pipeline\Preparation\RequestPreparer;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\Requests\TraceOverrideRequest;

describe('PipelineContextFactory', function () {
    it('создаёт контекст с role override без изменения состояния запроса', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $factory = new PipelineContextFactory(
            $config,
            new RequestPreparer($config),
            new StageProcessor(new AttributeRegistry()),
        );

        $options = RequestOptions::empty()->withRole(RequestRole::Nested)->withTraceId('trace-opt');
        $request = new SimpleGetRequest('q');

        $context = $factory->create($request, RequestRole::Root, null, null, null, $options);

        expect($context->role)->toBe(RequestRole::Nested);
        expect($context->traceId)->toBe('trace-opt');
        expect($request->getContext())->toBeNull();
    });

    it('использует override из запроса, если options не заданы', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $factory = new PipelineContextFactory(
            $config,
            new RequestPreparer($config),
            new StageProcessor(new AttributeRegistry()),
        );

        $request = new TraceOverrideRequest('trace-request', RequestRole::Nested);

        $context = $factory->create($request, RequestRole::Root, null, null, null, null);

        expect($context->role)->toBe(RequestRole::Nested);
        expect($context->traceId)->toBe('trace-request');
    });

    it('не дублирует диагностику владельца запуска при обработке старта', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $factory = new PipelineContextFactory(
            $config,
            new RequestPreparer($config),
            new StageProcessor(new AttributeRegistry()),
        );

        $request = new SimpleGetRequest('q');
        $context = $factory->create($request, RequestRole::Root, null, null, null, null);
        $audit = [];

        $start = $factory->start($request, $context, $audit);

        expect($start)->toBeFloat();
        expect($audit)->toBe([]);
    });
});
