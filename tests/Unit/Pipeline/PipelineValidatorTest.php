<?php

declare(strict_types=1);

use ApiSutra\Attributes\AttributeRegistry;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Extensions\ExtensionRegistry;
use ApiSutra\Hooks\HookRegistry;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Pipeline\Flow\ExecutionResultBuilder;
use ApiSutra\Pipeline\Flow\PipelineValidator;
use ApiSutra\Pipeline\Hydration\ResponseHydrator;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Tests\Stubs\Requests\CustomValidatableRequestStub;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\VO\Errors\ValidationError;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('PipelineValidator', function () {
    it('не возвращает ошибку при отсутствии правил', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $builder = new ExecutionResultBuilder(
            $config,
            new ResponseHydrator(
                $config,
                new Hydrator(new CastRegistry()),
                new ExtensionRegistry(new CastRegistry(), new HookRegistry(), new AttributeRegistry()),
            ),
        );
        $validator = new PipelineValidator($builder);

        $request = new SimpleGetRequest('q');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $audit = [];
        $result = $validator->validate($request, $context, $audit, microtime(true));

        expect($result)->toBeNull();
    });

    it('возвращает ExecutionResult при ошибках validateCustom', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $builder = new ExecutionResultBuilder(
            $config,
            new ResponseHydrator(
                $config,
                new Hydrator(new CastRegistry()),
                new ExtensionRegistry(new CastRegistry(), new HookRegistry(), new AttributeRegistry()),
            ),
        );
        $validator = new PipelineValidator($builder);

        $request = new CustomValidatableRequestStub('q');
        $request->customValidationErrors = [
            new ValidationError('document', 'file_valid', 'Файл повреждён или не поддерживается', null),
        ];
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $audit = [];
        $result = $validator->validate($request, $context, $audit, microtime(true));

        expect($result)->not->toBeNull()
            ->and($result->status)->toBe(ResultStatus::FAILED)
            ->and($result->validationErrors)->toHaveCount(1)
            ->and($result->validationErrors[0]->field)->toBe('document')
            ->and($result->validationErrors[0]->rule)->toBe('file_valid')
            ->and($result->validationErrors[0]->message)->toBe('Файл повреждён или не поддерживается');
    });

    it('пропускает custom validation при пустом списке ошибок', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $builder = new ExecutionResultBuilder(
            $config,
            new ResponseHydrator(
                $config,
                new Hydrator(new CastRegistry()),
                new ExtensionRegistry(new CastRegistry(), new HookRegistry(), new AttributeRegistry()),
            ),
        );
        $validator = new PipelineValidator($builder);

        $request = new CustomValidatableRequestStub('q');
        $request->customValidationErrors = [];
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $audit = [];
        $result = $validator->validate($request, $context, $audit, microtime(true));

        expect($result)->toBeNull();
    });
});
