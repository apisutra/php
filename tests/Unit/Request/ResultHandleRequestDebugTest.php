<?php

declare(strict_types=1);

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResolvedResultFactory;
use ApiSutra\Result\ResultHandle;
use ApiSutra\VO\Audit\DebugInfo;
use ApiSutra\VO\Http\PreparedRequest;

describe('ResultHandle request debug sugar', function () {
    it('делегирует requestDebug в ExecutionResult', function () {
        $result = new ExecutionResult(
            data: null,
            status: ResultStatus::SUCCESS,
            errors: new ErrorCollection([]),
            debug: new DebugInfo(
                preparedRequest: new PreparedRequest(
                    method: HttpMethod::GET,
                    url: 'https://api.test/users',
                    headers: ['Authorization' => 'Bearer secret'],
                    body: null,
                ),
            ),
        );
        $handle = new ResultHandle($result, new ResolvedResultFactory());

        $snapshot = $handle->requestDebug();

        expect($snapshot)->not->toBeNull()
            ->and($snapshot['method'] ?? null)->toBe('GET')
            ->and($snapshot['headers']['Authorization'] ?? null)->toBe('***');
    });

    it('делегирует requestDebugJson в ExecutionResult', function () {
        $result = new ExecutionResult(
            data: null,
            status: ResultStatus::SUCCESS,
            errors: new ErrorCollection([]),
            debug: new DebugInfo(
                preparedRequest: new PreparedRequest(
                    method: HttpMethod::POST,
                    url: 'https://api.test/users',
                    headers: ['X-Mode' => 'test'],
                    body: '{"x":1}',
                ),
            ),
        );
        $handle = new ResultHandle($result, new ResolvedResultFactory());

        $json = $handle->requestDebugJson();
        $decoded = is_string($json) ? json_decode($json, true) : null;

        expect($decoded['method'] ?? null)->toBe('POST')
            ->and($decoded['url'] ?? null)->toBe('https://api.test/users')
            ->and($decoded['headers']['X-Mode'] ?? null)->toBe('test')
            ->and($decoded['bodyRaw'] ?? null)->toBe('{"x":1}');
    });
});
