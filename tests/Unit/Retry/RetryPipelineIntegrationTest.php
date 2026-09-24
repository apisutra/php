<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Tests\Stubs\Requests\MultipartUploadRequest;
use ApiSutra\Tests\Stubs\Requests\RetryIdempotentRequest;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Stubs\Retry\ConsumingHttpClient;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\FakeSleeper;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\VO\Files\FileInput;
use ApiSutra\Execution\BatchExecutor;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Exceptions\Request\ServiceUnavailableException;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

it('повторяет реальный multipart с теми же байтами boundary и исходной позицией файла', function (string $content, bool $seekable): void {
    $http = new ConsumingHttpClient([new Response(503, [], 'fixture'), new Response(200, [], '{}')]);
    $factory = new HttpFactory();
    $sleeper = new FakeSleeper();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', retry: new RetryConfig(attempts: 2, baseDelay: 10, jitter: false, safeMethods: [HttpMethod::POST]),
    ), new HttpTransport($http, $factory, $factory), $sleeper);
    $stream = Utils::streamFor('prefix:' . $content);
    $stream->seek(7);
    if (!$seekable) {
        $stream = new NoSeekStream($stream);
    }
    $request = (new MultipartUploadRequest([FileInput::fromStream($stream, 'fixture.txt')], 'comment'))->setClient($client);
    $result = $request->send()->raw();
    expect($http->bodies)->toHaveCount($seekable ? 2 : 1)
        ->and($http->bodies[0])->not->toContain('prefix:')
        ->and($stream->isReadable())->toBeTrue();
    if ($seekable) {
        expect($result->isSuccess())->toBeTrue()
            ->and($http->bodies[1])->toBe($http->bodies[0])
            ->and($http->requests[1]->getHeaderLine('Content-Type'))->toBe($http->requests[0]->getHeaderLine('Content-Type'));
    } else {
        expect($result->errors->first()->code->value)->toBe('service_unavailable')
            ->and($result->errors->first()->context['retryRefusalReason'])->toBe('body_not_replayable')
            ->and($sleeper->calls)->toBe(0);
    }
})->with(['fixture bytes', ''])->with([true, false]);

it('сохраняет ключ идемпотентности в попытках и разделяет независимые выполнения', function (?string $provided, bool $header): void {
    $http = new ConsumingHttpClient([new Response(503), new Response(200, [], '{}'), new Response(503), new Response(200, [], '{}')]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', retry: new RetryConfig(attempts: 2, baseDelay: 0, jitter: false, safeMethods: [HttpMethod::POST]),
    ), new HttpTransport($http, $factory, $factory), new FakeSleeper());
    $request = (new RetryIdempotentRequest(HttpMethod::POST))->setClient($client);
    $execution = $provided === null ? $request : ($header
        ? $request->withHeader('idempotency-key', $provided)
        : $request->withIdempotencyKey($provided));
    expect($execution->send()->raw()->isSuccess())->toBeTrue()
        ->and($execution->send()->raw()->isSuccess())->toBeTrue();
    $keys = array_map(static fn ($request): string => $request->getHeaderLine('Idempotency-Key'), $http->requests);
    expect($keys[0])->not->toBe('')->and($keys[1])->toBe($keys[0])->and($keys[3])->toBe($keys[2]);
    if ($provided === null) {
        expect($keys[2])->not->toBe($keys[0]);
    } else {
        expect($keys)->toBe([$provided, $provided, $provided, $provided]);
    }
})->with([[null, false], ['fixture-key', false], ['fixture-header-key', true]]);

it('сохраняет отказ в retry через raw resolved dataOrFail и throwOnErrors', function (bool $async, bool $throw): void {
    $http = new ConsumingHttpClient([new Response(503, ['Content-Type' => 'text/plain'], 'fixture failure')]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', retry: new RetryConfig(), throwOnErrors: $throw,
    ), new HttpTransport($http, $factory, $factory), new FakeSleeper());
    $request = (new RetryPolicyRequest(HttpMethod::POST))->setClient($client);
    try {
        $handle = $async ? $request->sendAsync()->wait() : $request->send();
        if (!$throw) {
            expect($handle->raw()->errors->first()->context['retryRefusalReason'])->toBe('operation_not_safe')
                ->and($handle->resolved()->isFailed())->toBeTrue();
        }
        $handle->dataOrFail();
        test()->fail('Ожидалось HTTP-исключение');
    } catch (ServiceUnavailableException $exception) {
        expect($exception->response->status)->toBe(503)->and($exception->response->body)->toBe('fixture failure');
    }
    expect($http->requests)->toHaveCount(1);
})->with([false, true])->with([false, true]);

it('сохраняет независимые retry overrides в batch', function (): void {
    $http = new ConsumingHttpClient([new Response(503), new Response(503), new Response(200, [], '{}')]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', retry: new RetryConfig(attempts: 2, baseDelay: 0),
    ), new HttpTransport($http, $factory, $factory), new FakeSleeper());
    $request = (new RetryPolicyRequest())->setClient($client);
    $result = (new BatchExecutor($client, [$request->withoutRetry(), $request->withRetry(2)], failStrategy: FailStrategy::Partial))->send();
    expect($result->get(0)->isFailed())->toBeTrue()
        ->and($result->get(1)->isSuccess())->toBeTrue()
        ->and($http->requests)->toHaveCount(3);
});
