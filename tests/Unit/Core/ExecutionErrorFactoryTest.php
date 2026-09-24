<?php

declare(strict_types=1);

use ApiSutra\Execution\ExecutionErrorFactory;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Auth\OAuth2\OAuth2FailureReason;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Diagnostics\RedactionPolicy;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Auth\OAuth2Exception;
use ApiSutra\Pipeline\Diagnostics\ExecutionEnvelope;
use ApiSutra\VO\Audit\DebugInfo;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('ExecutionErrorFactory', function () {
    it('наследует trace, локализацию и redaction из контекста envelope', function (): void {
        $request = new SimpleGetRequest('q');
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            redaction: new RedactionPolicy(fields: ['customSecret']),
            localization: new LocalizationConfig('ru', ['ru' => ['oauth2.operation_failed' => 'Ошибка: {reason}']]),
        );
        $prepared = new PreparedRequest(HttpMethod::POST, 'https://api.test', body: 'fixture', meta: [
            'body' => ['customSecret' => 'private', 'localSecret' => 'local', 'visible' => 'ok'],
            'sensitiveFields' => ['localSecret'],
        ]);
        $context = new PipelineContext($request, $config, 'error-trace', preparedRequest: $prepared);
        $envelope = new ExecutionEnvelope($request);
        $envelope->context = $context;
        $result = (new ExecutionErrorFactory())->buildExceptionResult($envelope, new OAuth2Exception(OAuth2FailureReason::TokenPersistenceFailed));
        expect($result->trace)->toBe($context->trace)->and($result->traceId)->toBe('error-trace')
            ->and($result->errors->first()->message)->toBe('Ошибка: oauth2_token_persistence_failed')
            ->and($result->exception->getMessage())->toBe($result->errors->first()->message);
        $debug = $result->withDiagnostics($context->trace, [], new DebugInfo($prepared))->requestDebug();
        expect($debug['body'])->toBe(['customSecret' => '***', 'localSecret' => '***', 'visible' => 'ok']);
    });

    it('создаёт результат для исключения', function () {
        $factory = new ExecutionErrorFactory();
        $request = new SimpleGetRequest('q');
        $exception = new RuntimeException('Ошибка');

        $result = $factory->buildExceptionResult($request, $exception);

        expect($result->status)->toBe(ResultStatus::FAILED);
        expect($result->errors->first()?->code)->toBe(ErrorCode::ExecutionError);
        expect($result->errors->first()?->message)->toBe('Ошибка');
        expect($result->exception)->toBe($exception);
    });

    it('формирует сообщения об ошибочных элементах', function () {
        $factory = new ExecutionErrorFactory();

        $message = $factory->unsupportedItemMessage('items', 2, 'foo');
        $invalid = $factory->invalidItemMessage('items', 3, new SimpleGetRequest('q'));

        expect($message)->toContain('items[2]');
        expect($message)->toContain('string(foo)');
        expect($invalid)->toContain('items[3]');
        expect($invalid)->toContain(SimpleGetRequest::class);
    });
});
