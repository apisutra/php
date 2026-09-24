<?php

declare(strict_types=1);

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResolvedResult;
use ApiSutra\VO\Errors\ClientError;
use ApiSutra\VO\Errors\ClientErrorFactory;
use ApiSutra\VO\Errors\DefaultClientErrorMapper;
use ApiSutra\VO\Errors\ErrorContextFactoryInterface;
use ApiSutra\VO\Errors\RequestError;

function makeCountingErrorContextFactory(): ErrorContextFactoryInterface
{
    return new class implements ErrorContextFactoryInterface
    {
        public int $calls = 0;

        public function make(ClientError $error): ?object
        {
            $this->calls++;

            return (object) ['code' => $error->sdkCode->value];
        }
    };
}

function makeProviderTraceContextFactory(): ErrorContextFactoryInterface
{
    return new class implements ErrorContextFactoryInterface
    {
        public function make(ClientError $error): ?object
        {
            return (object) ['providerTraceId' => 'provider-trace'];
        }
    };
}

describe('ResolvedResult error context', function () {
    it('возвращает null и пустой массив без фабрики', function () {
        $errors = new ErrorCollection([
            new RequestError(code: ErrorCode::NotFound, message: 'NF'),
        ]);
        $execution = new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: $errors,
        );
        $resolved = new ResolvedResult(
            $execution,
            new ClientErrorFactory(new DefaultClientErrorMapper()),
        );

        expect($resolved->errorContext())->toBeNull()
            ->and($resolved->errorContexts())->toBe([]);
    });

    it('строит типизированный контекст по всем ошибкам', function () {
        $errors = new ErrorCollection([
            new RequestError(code: ErrorCode::NotFound, message: 'NF'),
            new RequestError(code: ErrorCode::ServerError, message: 'SE'),
        ]);
        $execution = new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: $errors,
        );
        $factory = makeCountingErrorContextFactory();
        $resolved = new ResolvedResult(
            $execution,
            new ClientErrorFactory(new DefaultClientErrorMapper()),
            $factory,
        );

        $contexts = $resolved->errorContexts();

        expect($contexts)->toHaveCount(2)
            ->and($contexts[0]?->code)->toBe('not_found')
            ->and($contexts[1]?->code)->toBe('server_error');
    });

    it('кеширует построение контекстов', function () {
        $errors = new ErrorCollection([
            new RequestError(code: ErrorCode::NotFound, message: 'NF'),
        ]);
        $execution = new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: $errors,
        );
        $factory = makeCountingErrorContextFactory();
        $resolved = new ResolvedResult(
            $execution,
            new ClientErrorFactory(new DefaultClientErrorMapper()),
            $factory,
        );

        $resolved->errorContext();
        $resolved->errorContexts();

        expect($factory->calls)->toBe(1);
    });

    it('возвращает provider trace из typed‑контекста', function () {
        $errors = new ErrorCollection([
            new RequestError(code: ErrorCode::NotFound, message: 'NF'),
        ]);
        $execution = new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: $errors,
        );
        $resolved = new ResolvedResult(
            $execution,
            new ClientErrorFactory(new DefaultClientErrorMapper()),
            makeProviderTraceContextFactory(),
        );

        expect($resolved->errorProviderTraceId())->toBe('provider-trace');
    });
});
