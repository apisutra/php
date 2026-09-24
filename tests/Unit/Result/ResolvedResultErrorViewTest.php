<?php

declare(strict_types=1);

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResolvedResult;
use ApiSutra\Tests\Stubs\Core\TestClientErrorMapper;
use ApiSutra\VO\Errors\ClientError;
use ApiSutra\VO\Errors\ClientErrorFactory;
use ApiSutra\VO\Errors\ClientErrorMapperInterface;
use ApiSutra\VO\Errors\DefaultClientErrorMapper;
use ApiSutra\VO\Errors\RequestError;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;

describe('ResolvedResult error view', function () {
    it('возвращает пустые значения при отсутствии ошибок', function () {
        $execution = new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: new ErrorCollection([]),
        );
        $resolved = new ResolvedResult(
            $execution,
            new ClientErrorFactory(new DefaultClientErrorMapper()),
        );

        expect($resolved->error())->toBeNull()
            ->and($resolved->errorCode())->toBeNull()
            ->and($resolved->errorMessage())->toBeNull()
            ->and($resolved->errorStatus())->toBeNull()
            ->and($resolved->errorViews())->toBe([]);
    });

    it('читает данные первой ошибки', function () {
        $prepared = new PreparedRequest(HttpMethod::GET, 'https://api.test');
        $providerResponse = new ProviderResponse(
            status: 500,
            headers: [],
            body: '{}',
            request: $prepared,
            duration: 0.01,
        );
        $error = new RequestError(
            code: ErrorCode::ServerError,
            message: 'Ошибка',
            response: $providerResponse,
            requestClass: 'TestRequest',
        );
        $execution = new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: new ErrorCollection([$error]),
        );
        $resolved = new ResolvedResult(
            $execution,
            new ClientErrorFactory(new TestClientErrorMapper()),
        );

        expect($resolved->errorCode())->toBe('APP-001')
            ->and($resolved->errorMessage())->toBe('Кастомная ошибка')
            ->and($resolved->errorStatus())->toBe(500)
            ->and($resolved->errorViews())->toHaveCount(1);
    });

    it('кеширует маппинг ошибок', function () {
        $mapper = new class implements ClientErrorMapperInterface
        {
            public int $calls = 0;

            public function map(RequestError $error): ClientError
            {
                $this->calls++;

                return new ClientError(
                    providerCode: null,
                    sdkCode: $error->code,
                    clientCode: null,
                    appCode: null,
                    message: $error->message,
                    context: [],
                    nested: [],
                    requestClass: $error->requestClass,
                );
            }

            public function status(ErrorCollection $errors): int
            {
                return 500;
            }
        };
        $error = new RequestError(code: ErrorCode::NotFound, message: 'NF');
        $execution = new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: new ErrorCollection([$error]),
        );
        $resolved = new ResolvedResult(
            $execution,
            new ClientErrorFactory($mapper),
        );

        $resolved->errorCode();
        $resolved->errorViews();

        expect($mapper->calls)->toBe(1);
    });
});
