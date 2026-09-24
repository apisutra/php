<?php

declare(strict_types=1);

use ApiSutra\Attributes\Behavior\Retry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Contracts\Interfaces\Concurrency\RetrySafetyPolicyInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestOptionsProviderInterface;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Transport\ConnectionException;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Auth\RefreshingAuthenticator;
use ApiSutra\Tests\Stubs\Requests\ConditionalRetryRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\Tests\Support\RetryScenario;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\Psr7\Response;

it('разрешает POST только после 429, сохраняя сетевой retry GET', function (HttpMethod $method, int $status, int $calls): void {
    RefreshingAuthenticator::reset();
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $count = 0;
    $transport->fake(['*' => static function () use (&$count, $status): MockResponse {
        if (++$count === 1) {
            if ($status === 0) {
                throw new ConnectionException('fixture');
            }
            return MockResponse::make('{}', $status);
        }
        return MockResponse::success();
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', auth: new RefreshingAuthenticator(), retry: new RetryConfig(attempts: 2, baseDelay: 0, jitter: false)), $transport, $clock, $clock);
    $request = new ConditionalRetryRequest($method);
    $result = $client->send($request->withHeader('X-Fixture', '1'))->raw();
    expect($count)->toBe($calls)->and($result->isSuccess())->toBe($calls === 2);
    expect($request->safetyChecks)->toHaveCount(1);
    if ($status === 0) {
        expect($request->safetyChecks[0][0])->toBeNull()
            ->and($request->safetyChecks[0][1])->toBeInstanceOf(ConnectionException::class);
    }
    if ($status === 401) {
        expect(RefreshingAuthenticator::$refreshCalls)->toBe(0);
    }
})->with([
    [HttpMethod::POST, 429, 2], [HttpMethod::POST, 503, 1], [HttpMethod::POST, 0, 1],
    [HttpMethod::POST, 401, 1], [HttpMethod::GET, 0, 2],
]);

it('явный safe выше контракта, а null сохраняет контракт', function (?bool $safe): void {
    $request = match ($safe) {
        true => new #[Retry(safe: true, baseDelay: 0)] class(HttpMethod::POST) extends ConditionalRetryRequest {},
        false => new #[Retry(safe: false)] class(HttpMethod::POST) extends ConditionalRetryRequest {},
        null => new #[Retry(safe: null, baseDelay: 0)] class(HttpMethod::POST) extends ConditionalRetryRequest {},
    };
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::sequence([MockResponse::make('{}', 429), MockResponse::success()])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport, $clock, $clock);
    $result = $client->send($request)->raw();
    expect($result->isSuccess())->toBe($safe !== false)
        ->and($request->safetyChecks)->toHaveCount($safe === null ? 1 : 0);
})->with([true, false, null]);

it('ошибка safety прекращает вызов даже если Throwable разрешён для retry', function (): void {
    $failure = new ConnectionException('fixture-sensitive-payload');
    $request = new ConditionalRetryRequest(HttpMethod::POST);
    $request->safetyFailure = $failure;
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('{}', 429)]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(retryExceptions: [Throwable::class])), $transport);
    $result = $client->send($request)->raw();
    expect($result->errors->first()->code->value)->toBe('execution_error')
        ->and($result->errors->first()->context['reason'])->toBe('retry_safety_check_failed')
        ->and($result->errors->first()->message)->not->toContain('fixture-sensitive-payload')
        ->and($result->exception->getPrevious())->toBe($failure)
        ->and($transport->getRecorded())->toHaveCount(1)->and($request->safetyChecks)->toHaveCount(1);
});

it('не запускает safety без причины повторять или с отключённым retry', function (bool $disabled): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('{}', $disabled ? 429 : 200)]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport);
    $request = new ConditionalRetryRequest(HttpMethod::POST);
    $client->send($disabled ? $request->withoutRetry() : $request)->raw();
    expect($request->safetyChecks)->toBe([])->and($transport->getRecorded())->toHaveCount(1);
})->with([false, true]);

it('не подставляет предыдущий 429 в safety текущей сетевой попытки', function (): void {
    $transport = new MockTransport();
    $count = 0;
    $transport->fake(['*' => static function () use (&$count): MockResponse {
        if (++$count === 1) {
            return MockResponse::make('{}', 429);
        }
        throw new ConnectionException('fixture');
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(attempts: 3, baseDelay: 0, jitter: false)), $transport);
    $request = new ConditionalRetryRequest(HttpMethod::POST);
    $client->send($request)->raw();
    expect($count)->toBe(2)->and($request->safetyChecks[0][0])->toBe(429)
        ->and($request->safetyChecks[1][0])->toBeNull()
        ->and($request->safetyChecks[1][1])->toBeInstanceOf(ConnectionException::class);
});

it('поддерживает контракт на RequestInterface без AbstractRequest', function (): void {
    $request = new class implements RequestInterface, RequestOptionsProviderInterface, RetrySafetyPolicyInterface {
        public function getMethod(): HttpMethod { return HttpMethod::POST; }
        public function getEndpoint(): string { return '/fixture'; }
        public function getResponseType(): ?string { return null; }
        public function getOptions(): RequestOptions { return RequestOptions::empty()->withRawResponse(); }
        public function isRetrySafe(HttpMethod $method, ?ProviderResponse $response, ?Throwable $exception): ?bool
        {
            return $exception === null && $response?->status === 429;
        }
    };
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::sequence([MockResponse::make('{}', 429), MockResponse::success(['ok' => true])])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(baseDelay: 0, jitter: false)), $transport);
    $result = $client->send($request)->raw();
    expect($result->data)->toBe('{"ok":true}')->and($transport->getRecorded())->toHaveCount(2);
});

it('разрешённый условной safety POST не обходит восстановление тела', function (): void {
    $scenario = new RetryScenario(
        new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig()),
        new ConditionalRetryRequest(HttpMethod::POST),
        new PreparedRequest(HttpMethod::POST, 'https://fixture.test', stream: new NoSeekStream(Utils::streamFor('fixture'))),
        [new Response(429)],
    );
    expect($scenario->run()->status)->toBe(429)
        ->and($scenario->context->retryRefusalReason)->toBe('body_not_replayable')
        ->and($scenario->http->bodies)->toBe(['fixture'])->and($scenario->sleeper->calls)->toBe(0);
});

it('бизнес-повтор POST использует остаток бюджета одной операции', function (): void {
    $request = new class(HttpMethod::POST) extends ConditionalRetryRequest {
        protected function shouldRetry(ProviderResponse $response, int $attempt): bool
        {
            return $response->json('error') === 'fixture.not.ready';
        }
        public function isRetrySafe(HttpMethod $method, ?ProviderResponse $response, ?Throwable $exception): ?bool
        {
            return $exception === null && $response?->json('error') === 'fixture.not.ready';
        }
    };
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $timeouts = [];
    $transport->fake(['*' => static function () use ($clock, $transport, &$timeouts): MockResponse {
        $sent = $transport->getRecorded();
        $remaining = $sent[array_key_last($sent)]->transportOptions->effective()->timeoutMs;
        $timeouts[] = $remaining;
        $clock->advance(min(600, $remaining));
        return MockResponse::success(['error' => 'fixture.not.ready']);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(baseDelay: 0, jitter: false, totalTimeoutMs: 1000)), $transport, $clock, $clock);
    $result = $client->send($request)->raw();
    expect($timeouts)->toBe([1000, 400])->and($clock->milliseconds)->toBe(2000)
        ->and($result->errors->first()->context['reason'])->toBe('execution_deadline_exceeded');
});
