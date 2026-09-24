<?php

declare(strict_types=1);

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Audit\DebugInfo;
use ApiSutra\VO\Http\PreparedRequest;
use GuzzleHttp\Psr7\Utils;

describe('ExecutionResult request debug', function () {
    it('возвращает null если debug info отсутствует', function () {
        $result = new ExecutionResult(
            data: null,
            status: ResultStatus::SUCCESS,
            errors: new ErrorCollection([]),
        );

        expect($result->requestDebug())->toBeNull()
            ->and($result->requestDebugJson())->toBeNull();
    });

    it('возвращает снимок подготовленного запроса c маскировкой по умолчанию', function () {
        $prepared = new PreparedRequest(
            method: HttpMethod::PATCH,
            url: 'https://api.test/v1/resource?id=1',
            headers: [
                'Authorization' => 'Bearer secret-token',
                'X-Trace-Id' => 'trace-123',
                'Cookie' => 'session=secret',
            ],
            body: '{"a":1}',
        );
        $result = new ExecutionResult(
            data: null,
            status: ResultStatus::SUCCESS,
            errors: new ErrorCollection([]),
            debug: new DebugInfo(preparedRequest: $prepared),
        );

        $snapshot = $result->requestDebug();

        expect($snapshot)->not->toBeNull()
            ->and($snapshot['method'] ?? null)->toBe('PATCH')
            ->and($snapshot['url'] ?? null)->toBe('https://api.test/v1/resource?id=1')
            ->and($snapshot['headers']['Authorization'] ?? null)->toBe('***')
            ->and($snapshot['headers']['Cookie'] ?? null)->toBe('***')
            ->and($snapshot['headers']['X-Trace-Id'] ?? null)->toBe('trace-123')
            ->and($snapshot['bodyRaw'] ?? null)->toBe('{"a":1}')
            ->and($snapshot['hasStream'] ?? null)->toBeFalse();
    });

    it('может вернуть заголовки без маскировки и json-представление', function (bool $useStream): void {
        $prepared = new PreparedRequest(
            method: HttpMethod::POST,
            url: 'https://api.test/v1/resource',
            headers: [
                'X-Api-Key' => 'my-secret-key',
                'X-Mode' => 'raw',
            ],
            body: $useStream ? null : '{"x":"y"}',
            stream: $useStream ? Utils::streamFor('payload') : null,
        );
        $result = new ExecutionResult(
            data: null,
            status: ResultStatus::SUCCESS,
            errors: new ErrorCollection([]),
            debug: new DebugInfo(preparedRequest: $prepared),
        );

        $snapshot = $result->requestDebug(false);
        $json = $result->requestDebugJson(false);
        $decoded = is_string($json) ? json_decode($json, true) : null;

        expect($snapshot['headers']['X-Api-Key'] ?? null)->toBe('my-secret-key')
            ->and($snapshot['hasStream'] ?? null)->toBe($useStream)
            ->and($decoded['method'] ?? null)->toBe('POST')
            ->and($decoded['headers']['X-Api-Key'] ?? null)->toBe('my-secret-key')
            ->and($decoded['bodyRaw'] ?? null)->toBe($useStream ? null : '{"x":"y"}')
            ->and($decoded['hasStream'] ?? null)->toBe($useStream);
    })->with([false, true]);

    it('возвращает oneOf диагностику из meta prepared запроса', function () {
        $prepared = new PreparedRequest(
            method: HttpMethod::POST,
            url: 'https://api.test/v1/signatures',
            headers: ['X-Mode' => 'test'],
            body: '{"contents":"abc"}',
            meta: [
                'oneOf' => [
                    'contract' => 'signature_payload',
                    'matchedVariant' => 'cloudcrypt',
                    'discriminator' => [
                        'field' => 'type',
                        'value' => 'KONTUR_UC',
                        'variant' => 'cloudcrypt',
                    ],
                ],
            ],
        );
        $result = new ExecutionResult(
            data: null,
            status: ResultStatus::SUCCESS,
            errors: new ErrorCollection([]),
            debug: new DebugInfo(preparedRequest: $prepared),
        );

        $snapshot = $result->requestDebug();

        expect($snapshot['oneOf']['contract'] ?? null)->toBe('signature_payload')
            ->and($snapshot['oneOf']['matchedVariant'] ?? null)->toBe('cloudcrypt')
            ->and($snapshot['oneOf']['discriminator']['field'] ?? null)->toBe('type');
    });

    it('маскирует секреты в body/query/form по secretKeys enrichment', function () {
        $prepared = new PreparedRequest(
            method: HttpMethod::POST,
            url: 'https://api.test/v1/credentials?api_key=secret',
            headers: [
                'Authorization' => 'Bearer token',
                'Content-Type' => 'multipart/form-data; boundary=abc',
            ],
            body: '{"api_key":"secret-key","safe":"ok"}',
            meta: [
                'body' => [
                    'api_key' => 'secret-key',
                    'safe' => 'ok',
                ],
                'query' => [
                    'api_key' => ['value' => 'secret-key', 'format' => null],
                    'q' => ['value' => 'ok', 'format' => null],
                ],
                'credentialsEnrichment' => [
                    'applied' => true,
                    'scope' => 'system',
                    'mergeMode' => 'fill-missing',
                    'fields' => ['body' => ['api_key'], 'query' => ['api_key'], 'form' => ['api_key']],
                    'secretKeys' => ['api_key'],
                ],
            ],
        );
        $result = new ExecutionResult(
            data: null,
            status: ResultStatus::SUCCESS,
            errors: new ErrorCollection([]),
            debug: new DebugInfo(preparedRequest: $prepared),
        );

        $snapshot = $result->requestDebug();

        expect($snapshot['headers']['Authorization'] ?? null)->toBe('***')
            ->and($snapshot['body']['api_key'] ?? null)->toBe('***')
            ->and($snapshot['query']['api_key']['value'] ?? null)->toBe('***')
            ->and($snapshot['form']['api_key'] ?? null)->toBe('***')
            ->and($snapshot['bodyRaw'] ?? null)->toContain('"api_key":"***"')
            ->and($snapshot['credentialsEnrichment']['applied'] ?? null)->toBeTrue();
    });
});
