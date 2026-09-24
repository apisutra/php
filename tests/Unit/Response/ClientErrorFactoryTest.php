<?php

declare(strict_types=1);

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Tests\Stubs\Core\TestClientErrorMapper;
use ApiSutra\VO\Errors\ClientError;
use ApiSutra\VO\Errors\ClientErrorFactory;
use ApiSutra\VO\Errors\ClientErrorMapperInterface;
use ApiSutra\VO\Errors\RequestError;
use ApiSutra\VO\Errors\SystemErrorContextKeys;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;

describe('ClientErrorFactory', function () {
    it('мапит одну ошибку через mapper', function () {
        $factory = new ClientErrorFactory(new TestClientErrorMapper());
        $prepared = new PreparedRequest(HttpMethod::GET, 'https://api.test');
        $response = new ProviderResponse(
            status: 500,
            headers: [],
            body: '{}',
            request: $prepared,
            duration: 0.01,
        );
        $error = new RequestError(
            code: ErrorCode::ValidationFailed,
            message: 'Ошибка',
            response: $response,
            context: [SystemErrorContextKeys::TraceId->value => 'trace-1'],
            requestClass: 'TestRequest',
        );

        $mapped = $factory->make($error);

        expect($mapped->clientCode)->toBe('client.custom')
            ->and($mapped->appCode)->toBe('APP-001')
            ->and($mapped->providerCode)->toBe('P-1')
            ->and($mapped->sdkCode)->toBe(ErrorCode::ValidationFailed)
            ->and($mapped->context['source'] ?? null)->toBe('test')
            ->and($mapped->context[SystemErrorContextKeys::TraceId->value] ?? null)->toBe('trace-1')
            ->and($mapped->context[SystemErrorContextKeys::HttpStatus->value] ?? null)->toBe(500)
            ->and($mapped->context[SystemErrorContextKeys::RequestClass->value] ?? null)->toBe('TestRequest')
            ->and($mapped->context[SystemErrorContextKeys::ProviderCode->value] ?? null)->toBe('P-1');
    });

    it('соблюдает порядок merge контекста', function () {
        $factory = new ClientErrorFactory(new class implements ClientErrorMapperInterface
        {
            public function map(RequestError $error): ClientError
            {
                return new ClientError(
                    providerCode: 'P-2',
                    sdkCode: $error->code,
                    clientCode: null,
                    appCode: null,
                    message: $error->message,
                    context: [
                        SystemErrorContextKeys::TraceId->value => 'trace-mapper',
                        'source' => 'mapper',
                    ],
                    nested: [],
                    requestClass: $error->requestClass,
                );
            }

            public function status(ErrorCollection $errors): int
            {
                return 500;
            }
        });
        $error = new RequestError(
            code: ErrorCode::ServerError,
            message: 'Ошибка',
            context: [
                SystemErrorContextKeys::TraceId->value => 'trace-request',
                'custom' => 'request',
            ],
            requestClass: 'TestRequest',
        );

        $mapped = $factory->make($error);

        expect($mapped->context[SystemErrorContextKeys::TraceId->value] ?? null)->toBe('trace-mapper')
            ->and($mapped->context['custom'] ?? null)->toBe('request')
            ->and($mapped->context['source'] ?? null)->toBe('mapper')
            ->and($mapped->context[SystemErrorContextKeys::ProviderCode->value] ?? null)->toBe('P-2');
    });

    it('мапит коллекцию ошибок', function () {
        $factory = new ClientErrorFactory(new TestClientErrorMapper());
        $errors = new ErrorCollection([
            new RequestError(code: ErrorCode::ServerError, message: 'A'),
            new RequestError(code: ErrorCode::NotFound, message: 'B'),
        ]);

        $mapped = $factory->makeMany($errors);

        expect($mapped)->toHaveCount(2)
            ->and($mapped[0])->toBeInstanceOf(ClientError::class);
    });
});
