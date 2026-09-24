<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Support\RetryScenario;
use ApiSutra\VO\Http\PreparedRequest;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\MultipartStream;
use ApiSutra\Tests\Stubs\Retry\FaultyReplayStream;
use ApiSutra\Tests\Stubs\Core\PsrNetworkFailure;
use ApiSutra\Enums\RateLimiting\BackoffStrategy;
use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Psr7\Request;
use ApiSutra\Tests\Stubs\Auth\RefreshingAuthenticator;

it('восстанавливает фактически отправленные байты при HTTP и auth retry', function (bool $multipart, int $status): void {
    $stream = $multipart
        ? new MultipartStream([['name' => 'file', 'contents' => 'fixture bytes', 'filename' => 'fixture.txt']], 'fixture-boundary')
        : Utils::streamFor('prefix:fixture bytes');
    if (!$multipart) {
        $stream->seek(7);
    }
    $scenario = new RetryScenario(
        new ClientConfig(baseUrl: 'https://fixture.test', auth: new RefreshingAuthenticator(), retry: new RetryConfig(attempts: 2, baseDelay: 20, jitter: false), authRetryAttempts: 1),
        new RetryPolicyRequest(),
        new PreparedRequest(HttpMethod::GET, 'https://fixture.test', stream: $stream),
        [new Response($status, ['Retry-After' => '0']), new Response(200, [], '{}')],
    );
    expect($scenario->run()->status)->toBe(200)
        ->and($scenario->http->bodies)->toHaveCount(2)
        ->and($scenario->http->bodies[1])->toBe($scenario->http->bodies[0])
        ->and($scenario->http->bodies[0])->toContain('fixture bytes')
        ->and($scenario->sleeper->totalMs)->toBe($status === 401 ? 0 : 20);
})->with([false, true])->with([503, 401]);

it('не повторяет non-seekable тело и сохраняет исходный ответ', function (int $status): void {
    $scenario = new RetryScenario(
        new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(attempts: 3), authRetryAttempts: 1),
        new RetryPolicyRequest(),
        new PreparedRequest(HttpMethod::GET, 'https://fixture.test', stream: new NoSeekStream(Utils::streamFor('fixture bytes'))),
        [new Response($status, ['Retry-After' => '2'], 'fixture failure')],
    );
    $result = $scenario->run();
    expect($result->status)->toBe($status)
        ->and($result->body)->toBe('fixture failure')
        ->and($scenario->http->bodies)->toBe(['fixture bytes'])
        ->and($scenario->sleeper->calls)->toBe(0)
        ->and($scenario->context->retryRefusalReason)->toBe('body_not_replayable');
})->with([503, 401]);

it('не складывает Retry-After и backoff и не ждёт после последнего ответа', function (int $status, string $header, int $expected): void {
    $scenario = new RetryScenario(
        new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(attempts: 2, baseDelay: 200, jitter: false)),
        new RetryPolicyRequest(), new PreparedRequest(HttpMethod::GET, 'https://fixture.test'),
        [new Response($status, ['Retry-After' => $header]), new Response($status, ['Retry-After' => '100'])],
    );
    expect($scenario->run()->status)->toBe($status)
        ->and($scenario->sleeper->calls)->toBe(1)
        ->and($scenario->sleeper->totalMs)->toBe($expected);
})->with([429, 503])->with([
    ['2', 2000], ['0', 200], ['-2', 200], ['1.5', 200], ['tomorrow', 200],
    ['999999999999999999999999999999', 200],
    [gmdate('D, d M Y H:i:s \G\M\T', 1_800_000_002), 2000],
    [gmdate('D, d M Y H:i:s \G\M\T', 1_799_999_998), 200],
]);

it('останавливает повтор при ошибке позиции или перемотки', function (string $failure, string $reason): void {
    $scenario = new RetryScenario(
        new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig()), new RetryPolicyRequest(),
        new PreparedRequest(HttpMethod::GET, 'https://fixture.test', stream: new FaultyReplayStream(Utils::streamFor('fixture'), $failure)),
        [new Response(503)],
    );
    expect($scenario->run()->status)->toBe(503)
        ->and($scenario->http->bodies)->toBe(['fixture'])
        ->and($scenario->context->retryRefusalReason)->toBe($reason)
        ->and($scenario->sleeper->calls)->toBe(0);
})->with([['tell', 'body_not_replayable'], ['seek', 'body_rewind_failed'], ['silent', 'body_rewind_failed']]);

it('замена потока в hook не обходит проверку тела', function (): void {
    $scenario = new RetryScenario(
        new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig()), new RetryPolicyRequest(),
        new PreparedRequest(HttpMethod::GET, 'https://fixture.test', stream: Utils::streamFor('original')),
        [new Response(503)],
    );
    $scenario->hooks->on(Hook::AfterResponse, new class implements HookInterface {
        public function handle(PipelineContext $context): ?array
        {
            $context->preparedRequest = $context->preparedRequest->with(stream: Utils::streamFor('replacement'));
            return null;
        }
    });
    expect($scenario->run()->status)->toBe(503)
        ->and($scenario->http->bodies)->toBe(['original'])
        ->and($scenario->context->retryRefusalReason)->toBe('body_changed')
        ->and($scenario->sleeper->calls)->toBe(0);
});

it('восстанавливает тело после сетевого сбоя при чтении потока', function (): void {
    $scenario = new RetryScenario(
        new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(baseDelay: 0)), new RetryPolicyRequest(),
        new PreparedRequest(HttpMethod::GET, 'https://fixture.test', stream: Utils::streamFor('fixture')),
        [new PsrNetworkFailure(new Request('GET', 'https://fixture.test')), new Response(200)],
    );
    expect($scenario->run()->status)->toBe(200)->and($scenario->http->bodies)->toBe(['fixture', 'fixture']);
});

it('применяет backoff только между основными попытками', function (BackoffStrategy $strategy, int $expectedMs): void {
    $scenario = new RetryScenario(
        new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(attempts: 3, baseDelay: 10, backoff: $strategy, jitter: false)),
        new RetryPolicyRequest(), new PreparedRequest(HttpMethod::GET, 'https://fixture.test'),
        [new Response(503), new Response(503), new Response(503)],
    );
    expect($scenario->run()->status)->toBe(503)
        ->and($scenario->sleeper->calls)->toBe(2)
        ->and($scenario->sleeper->totalMs)->toBe($expectedMs);
})->with([[BackoffStrategy::Constant, 20], [BackoffStrategy::Linear, 30], [BackoffStrategy::Exponential, 30]]);

it('замена строки или очистка тела после ответа запрещает второй HTTP', function (string $operation, int $status): void {
    $scenario = new RetryScenario(
        new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(attempts: 3), authRetryAttempts: 1),
        new RetryPolicyRequest(),
        new PreparedRequest(HttpMethod::GET, 'https://fixture.test', stream: Utils::streamFor('original')),
        [new Response($status, [], 'original failure')],
    );
    $scenario->hooks->on(Hook::AfterResponse, new class ($operation) implements HookInterface {
        public function __construct(private string $operation)
        {
        }

        public function handle(PipelineContext $context): ?array
        {
            $context->preparedRequest = $this->operation === 'clear'
                ? $context->preparedRequest->withoutBody()
                : $context->preparedRequest->withBody('replacement');
            return null;
        }
    });
    expect($scenario->run()->body)->toBe('original failure')
        ->and($scenario->http->bodies)->toBe(['original'])
        ->and($scenario->context->retryRefusalReason)->toBe('body_changed')
        ->and($scenario->sleeper->calls)->toBe(0);
})->with(['clear', 'replace'])->with([503, 401]);
