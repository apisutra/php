<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Core\SequenceHttpClient;
use ApiSutra\Tests\Stubs\Core\TestHttpClient;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Stubs\Requests\TimeoutPolicyRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\Transport\MockTransport;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;

it('передаёт runtime таймауты до транспорта', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', timeout: 77, connectTimeout: 11), $transport);
    $result = (new RetryPolicyRequest())->setClient($client)->withTimeout(42, 7)->send()->raw();
    expect($result->isSuccess())->toBeTrue()
        ->and($transport->getRecorded()[0]->transportOptions->timeoutMs)->toBe(42000)
        ->and($transport->getRecorded()[0]->transportOptions->connectTimeoutMs)->toBe(7000);
});

it('наследует поля независимо и не теряет явный ноль', function (?int $timeout, ?int $connect, int $expectedTimeout, int $expectedConnect): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport);
    $request = (new TimeoutPolicyRequest())->setClient($client);
    $execution = $timeout === null ? $request : $request->withTimeout($timeout, $connect);
    expect($execution->send()->raw()->isSuccess())->toBeTrue();
    $options = $transport->getRecorded()[0]->transportOptions;
    expect($options->timeoutMs)->toBe($expectedTimeout)->and($options->connectTimeoutMs)->toBe($expectedConnect);
})->with([[null, null, 8000, 4000], [5, null, 5000, 4000], [0, 0, 0, 0], [5, 0, 5000, 0]]);

it('отклоняет отрицательные значения и переполнение до HTTP', function (int $timeout, ?int $connect): void {
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport);
    $result = (new RetryPolicyRequest())->setClient($client)->withTimeout($timeout, $connect)->send()->raw();
    expect($result->errors->first()->code->value)->toBe('configuration_error')->and($transport->getRecorded())->toHaveCount(0);
})->with([[-1, null], [5, -1], [PHP_INT_MAX, null]]);

it('отказывает неподдерживаемому PSR клиенту до HTTP', function (): void {
    $http = new TestHttpClient();
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), new HttpTransport($http, $factory, $factory));
    $result = (new RetryPolicyRequest())->setClient($client)->send()->raw();
    expect($result->errors->first()->code->value)->toBe('configuration_error')->and($http->lastRequest)->toBeNull();
});

it('не ждёт если delay или Retry-After не помещается в бюджет', function (bool $delay): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('fixture 503', 503, ['Retry-After' => '2'])]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', delay: $delay ? 2000 : 0,
        retry: new RetryConfig(attempts: 3, totalTimeoutMs: 1000),
    ), $transport, $clock, $clock);
    $result = (new RetryPolicyRequest())->setClient($client)->send()->raw();
    expect($result->exception)->toBeInstanceOf(ExecutionDeadlineException::class)
        ->and($result->errors->first()->context['reason'])->toBe('execution_deadline_exceeded')
        ->and($result->response?->status)->toBe($delay ? null : 503)
        ->and($transport->getRecorded())->toHaveCount($delay ? 0 : 1)
        ->and($clock->waits)->toBe([]);
})->with([false, true]);

it('пересчитывает остаток после задержки даже без retry и при timeout ноль', function (): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', timeout: 0, connectTimeout: 0, delay: 750,
        retry: new RetryConfig(totalTimeoutMs: 1000),
    ), $transport, $clock, $clock);
    $result = (new RetryPolicyRequest())->setClient($client)->withoutRetry()->send()->raw();
    expect($result->isSuccess())->toBeTrue()
        ->and($transport->getRecorded()[0]->transportOptions->effective()->timeoutMs)->toBe(250)
        ->and($clock->waits)->toBe([750]);
});

it('поздний HTTP ответ становится окончательным timeout', function (bool $async): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $transport->fake(['*' => static function () use ($clock): MockResponse {
        $clock->advance(1001);
        return MockResponse::success(['ok' => true]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(totalTimeoutMs: 1000)), $transport, $clock, $clock);
    $request = (new RetryPolicyRequest())->setClient($client);
    $result = ($async ? $request->sendAsync()->wait() : $request->send())->raw();
    expect($result->errors->first()->code->value)->toBe('timeout')
        ->and($result->errors->first()->context['stage'])->toBe('http_response')
        ->and($result->response->status)->toBe(200)
        ->and($transport->getRecorded())->toHaveCount(1);
})->with([false, true]);

it('учитывает rate limit wait в бюджете', function (): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', rateLimit: new RateLimitConfig(limit: 1, period: 10),
        retry: new RetryConfig(totalTimeoutMs: 1000),
    ), $transport, $clock, $clock);
    $request = (new RetryPolicyRequest())->setClient($client);
    expect($request->send()->raw()->isSuccess())->toBeTrue();
    $result = $request->send()->raw();
    expect($result->exception)->toBeInstanceOf(ExecutionDeadlineException::class)
        ->and($result->exception->stage)->toBe('rate_limit_wait')
        ->and($transport->getRecorded())->toHaveCount(1)->and($clock->waits)->toBe([]);
});

it('передаёт дефолты и runtime в адаптер HTTP клиента, сбрасывая connect override через null', function (): void {
    $http = new SequenceHttpClient([new Response(204), new Response(204), new Response(204)]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), new HttpTransport($http, $factory, $factory));
    $request = (new RetryPolicyRequest())->setClient($client);
    expect($request->send()->raw()->isSuccess())->toBeTrue();
    expect($request->withTimeout(42, 7)->send()->raw()->isSuccess())->toBeTrue();
    expect($request->withTimeout(42, 7)->withTimeout(20)->send()->raw()->isSuccess())->toBeTrue();
    expect(array_map(static fn ($options): array => [$options->timeoutMs, $options->connectTimeoutMs], $http->options))
        ->toBe([[30000, 10000], [42000, 7000], [20000, 10000]]);
});

it('разрешает простой PSR клиент когда все SDK лимиты явно отключены', function (): void {
    $http = new TestHttpClient();
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', timeout: 0, connectTimeout: 0), new HttpTransport($http, $factory, $factory));
    (new RetryPolicyRequest())->setClient($client)->send()->raw();
    expect($http->lastRequest)->not->toBeNull();
});

it('использует календарные часы только для HTTP-date Retry-After', function (): void {
    $clock = new VirtualClock();
    $clock->wallTime = 1800000000;
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('fixture 503', 503, ['Retry-After' => gmdate('D, d M Y H:i:s', $clock->wallTime + 2) . ' GMT'])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(totalTimeoutMs: 1000)), $transport, $clock, $clock);
    $result = (new RetryPolicyRequest())->setClient($client)->send()->raw();
    expect($result->exception)->toBeInstanceOf(ExecutionDeadlineException::class)
        ->and($result->exception->stage)->toBe('retry_wait')->and($clock->waits)->toBe([])
        ->and($transport->getRecorded())->toHaveCount(1);
});
