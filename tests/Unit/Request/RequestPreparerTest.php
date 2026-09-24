<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Pipeline\Preparation\RequestPreparer;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Tests\Stubs\Requests\IdempotentRequest;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\Requests\TraceOverrideRequest;
use ApiSutra\VO\Http\PreparedRequest;

describe('RequestPreparer', function () {
    it('приоритет traceId: options > request > переданные значения', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $preparer = new RequestPreparer($config);

        $request = new TraceOverrideRequest('trace-request');
        $options = RequestOptions::empty()->withTraceId('trace-options');

        $traceId = $preparer->resolveTraceId($request, 'trace', 'pipeline', $options);

        expect($traceId)->toBe('trace-options');
    });

    it('использует переданный traceId, если нет override', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $preparer = new RequestPreparer($config);

        $request = new SimpleGetRequest('q');

        $traceId = $preparer->resolveTraceId($request, 'trace', 'pipeline');

        expect($traceId)->toBe('trace');
    });

    it('генерирует traceId, если он не задан', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $preparer = new RequestPreparer($config);

        $request = new SimpleGetRequest('q');

        $traceId = $preparer->resolveTraceId($request, null, null);

        expect($traceId)->toMatch('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/');
    });

    it('применяет заголовки и идемпотентность', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $preparer = new RequestPreparer($config);

        $request = new IdempotentRequest();
        $prepared = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/idempotent',
            headers: ['X-Test' => '1'],
        );

        $options = RequestOptions::empty()
            ->withHeader('X-Extra', '2')
            ->withIdempotencyKey('idem-key');

        $result = $preparer->applyRequestOverrides($request, $prepared, $options);

        expect($result->headers['X-Test'] ?? null)->toBe('1');
        expect($result->headers['X-Extra'] ?? null)->toBe('2');
        expect($result->headers['X-Idempotency'] ?? null)->toBe('idem-key');
    });
});
