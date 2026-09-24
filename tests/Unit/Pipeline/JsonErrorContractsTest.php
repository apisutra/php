<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\DtoInterface;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use ApiSutra\Tests\Stubs\Requests\JsonPayloadRequest;
use ApiSutra\Tests\Stubs\Requests\UnwrapResponseRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Psr\Http\Client\ClientExceptionInterface;
use ApiSutra\Exceptions\Serialization\SerializationException;
use ApiSutra\Execution\BatchExecutor;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Exceptions\Request\ServiceUnavailableException;

beforeEach(function (): void {
    $this->transport = new MockTransport();
    $this->config = new ClientConfig(
        baseUrl: 'https://api.test',
        retry: new RetryConfig(attempts: 2, baseDelay: 0, maxDelay: 0, jitter: false),
        authRetryOn401: false,
        environment: Environment::Testing,
    );
    $this->client = new TestClient($this->config, $this->transport);
});

it('не отправляет невалидный JSON и сохраняет причину сериализации', function (mixed $payload): void {
    $this->transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $result = (new JsonPayloadRequest($payload))->setClient($this->client)->send()->raw();
    expect($result->isFailed())->toBeTrue()
        ->and($result->errors->first()->code->value)->toBe('serialization_error')
        ->and($result->exception->getPrevious())->toBeInstanceOf(JsonException::class)
        ->and($this->transport->getRecorded())->toHaveCount(0);
})->with(["invalid UTF-8" => ["\xB1"], 'INF' => [INF], 'NAN' => [NAN]]);

it('нормализует сетевое исключение до решения retry', function (): void {
    $calls = 0;
    $this->transport->fake(['*' => static function () use (&$calls): MockResponse {
        if (++$calls === 1) {
            throw new ConnectException('fixture network', new Request('GET', 'https://api.test'));
        }
        return MockResponse::success(['ok' => true]);
    }]);
    $result = (new CacheProbeRequest())->setClient($this->client)->send()->raw();
    expect($result->isSuccess())->toBeTrue()->and($calls)->toBe(2);
});

it('сохраняет последний HTTP-ответ после исчерпания повторов', function (int $status, string $code): void {
    $this->transport->fake(['*' => MockResponse::make(['message' => 'fixture provider message'], $status)]);
    $request = (new CacheProbeRequest())->setClient($this->client);
    $result = $request->send()->raw();
    expect($result->isFailed())->toBeTrue()
        ->and($result->response->status)->toBe($status)
        ->and($result->errors->first()->code->value)->toBe($code)
        ->and($result->errors->first()->message)->toBe('fixture provider message');
})->with([
    [400, 'bad_request'], [401, 'unauthorized'], [403, 'forbidden'], [404, 'not_found'],
    [409, 'client_error'], [422, 'validation_failed'], [429, 'rate_limited'],
    [500, 'server_error'], [502, 'bad_gateway'], [503, 'service_unavailable'], [504, 'gateway_timeout'],
]);

it('сетевая ошибка после HTTP не сохраняет прежний ответ как текущий', function (): void {
    $calls = 0;
    $original = new ConnectException('fixture network', new Request('GET', 'https://api.test'));
    $this->transport->fake(['*' => static function () use (&$calls, $original): MockResponse {
        if (++$calls === 1) {
            return MockResponse::serverError();
        }
        throw $original;
    }]);
    $result = (new CacheProbeRequest())->setClient($this->client)->send()->raw();
    expect($result->isFailed())->toBeTrue()
        ->and($result->errors->first()->code->value)->toBe('connection_failed')
        ->and($result->response)->toBeNull()
        ->and($result->exception->getPrevious())->toBe($original)
        ->and($calls)->toBe(2);
});

it('HTTP после сетевой ошибки определяет итог выполнения', function (): void {
    $calls = 0;
    $this->transport->fake(['*' => static function () use (&$calls): MockResponse {
        if (++$calls === 1) {
            throw new ConnectException('fixture network', new Request('GET', 'https://api.test'));
        }
        return MockResponse::make(['message' => 'fixture unavailable'], 503);
    }]);
    $result = (new CacheProbeRequest())->setClient($this->client)->send()->raw();
    expect($result->errors->first()->code->value)->toBe('service_unavailable')
        ->and($result->response->status)->toBe(503)
        ->and($calls)->toBe(2);
});

it('не превращает ошибку hook в сетевую и не повторяет HTTP', function (Hook $stage): void {
    $this->transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $original = new RuntimeException('fixture hook');
    $hook = new class($original) implements HookInterface {
        public function __construct(private RuntimeException $exception) {}
        public function handle(PipelineContext $context): ?array
        {
            throw $this->exception;
        }
    };
    $this->client->hooks()->on($stage, $hook);
    $result = (new CacheProbeRequest())->setClient($this->client)->send()->raw();
    expect($result->errors->first()->code->value)->toBe('hook_error')
        ->and($result->exception)->toBe($original)
        ->and($this->transport->getRecorded())->toHaveCount($stage === Hook::BeforeSend ? 0 : 1);
})->with([Hook::BeforeSend, Hook::AfterResponse, Hook::BeforeHydrate, Hook::AfterHydrate]);

it('различает ошибку данных DTO и ошибку соединения', function (): void {
    $this->transport->fake(['*' => MockResponse::success(['data' => ['item' => ['id' => [], 'name' => []]]])]);
    $result = (new UnwrapResponseRequest())->setClient($this->client)->send()->raw();
    expect($result->isFailed())->toBeTrue()
        ->and($result->errors->first()->code->value)->toBe('hydration_error')
        ->and($result->response->status)->toBe(200);
});

it('останавливает циклические массивы и неподдерживаемые JSON-значения до HTTP', function (string $kind): void {
    $this->transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $value = [];
    $resource = null;
    if ($kind === 'array') {
        $value['self'] = &$value;
    } elseif ($kind === 'dto') {
        $value = new class implements DtoInterface {
            public ?object $child = null;
            public static function from(array|object $data): static
            {
                return new static();
            }
        };
        $value->child = $value;
    } elseif ($kind === 'object') {
        $value = new stdClass();
        $value->self = $value;
    } else {
        $value = $resource = fopen('php://temp', 'r+');
    }
    try {
        $result = (new JsonPayloadRequest($value))->setClient($this->client)->send()->raw();
        expect($result->errors->first()->code->value)->toBe('serialization_error')
            ->and($this->transport->getRecorded())->toHaveCount(0);
    } finally {
        if (is_resource($resource)) {
            fclose($resource);
        }
    }
})->with(['array', 'object', 'dto', 'resource']);

it('не повторяет произвольный сбой транспорта как сетевой', function (): void {
    $original = new RuntimeException('fixture transport bug');
    $this->transport->fake(['*' => static fn () => throw $original]);
    $result = (new CacheProbeRequest())->setClient($this->client)->send()->raw();
    expect($result->errors->first()->code->value)->toBe('execution_error')
        ->and($result->exception)->toBe($original)
        ->and($this->transport->getRecorded())->toHaveCount(1);
});

it('определяет timeout по metadata, а не по тексту сообщения', function (array $metadata, string $code): void {
    $original = new ConnectException('timeout DNS fixture', new Request('GET', 'https://api.test'), handlerContext: $metadata);
    $this->transport->fake(['*' => static fn () => throw $original]);
    $result = (new CacheProbeRequest())->setClient($this->client)->send()->raw();
    expect($result->errors->first()->code->value)->toBe($code)
        ->and($result->exception->getPrevious())->toBe($original)
        ->and($this->transport->getRecorded())->toHaveCount(2);
})->with([[['errno' => 28], 'timeout'], [[], 'connection_failed']]);

it('сохраняет HTTP-ошибку при HTML и нестроковом message', function (string $body, string $contentType): void {
    $this->transport->fake(['*' => MockResponse::make($body, 503, ['Content-Type' => $contentType])]);
    $result = (new CacheProbeRequest())->setClient($this->client)->send()->raw();
    expect($result->errors->first()->code->value)->toBe('service_unavailable')
        ->and($result->response->body)->toBe($body)
        ->and($result->exception->response)->toBe($result->response)
        ->and($result->errors->first()->message)->toBe('HTTP 503');
})->with([['<html>unavailable</html>', 'text/html'], ['{"message": []}', 'application/json'], ['{broken', 'application/json']]);

it('сохраняет настроенный класс исходного исключения после нормализации', function (): void {
    $calls = 0;
    $this->transport->fake(['*' => static function () use (&$calls): MockResponse {
        if (++$calls === 1) {
            throw new ConnectException('fixture', new Request('GET', 'https://api.test'));
        }
        return MockResponse::success(['ok' => true]);
    }]);
    $client = new TestClient($this->config->with(retry: new RetryConfig(
        attempts: 2, baseDelay: 0, maxDelay: 0, jitter: false, retryExceptions: [ConnectException::class],
    )), $this->transport);
    $result = (new CacheProbeRequest())->setClient($client)->send()->raw();
    expect($result->isSuccess())->toBeTrue()->and($calls)->toBe(2);
});

it('согласованно доставляет HTTP-ошибку через raw resolved dataOrFail и async', function (bool $async): void {
    $this->transport->fake(['*' => MockResponse::make(['message' => 'fixture unavailable'], 503)]);
    $request = (new CacheProbeRequest())->setClient($this->client);
    $handle = $async ? $request->sendAsync()->wait() : $request->send();
    expect($handle->raw()->errors->first()->code->value)->toBe('service_unavailable')
        ->and($handle->resolved()->error()->sdkCode->value)->toBe('service_unavailable')
        ->and(fn () => $handle->dataOrFail())->toThrow(ServiceUnavailableException::class)
        ->and($this->transport->getRecorded())->toHaveCount(2);
})->with([false, true]);

it('не выдаёт malformed JSON за успешный ответ и сохраняет HTTP-контекст', function (string $contentType): void {
    $this->transport->fake(['*' => MockResponse::make('{broken', 200, ['Content-Type' => $contentType])]);
    $result = (new CacheProbeRequest())->setClient($this->client)->send()->raw();
    expect($result->isFailed())->toBeTrue()
        ->and($result->errors->first()->code->value)->toBe('response_decoding_error')
        ->and($result->response->status)->toBe(200)
        ->and($result->response->body)->toBe('{broken')
        ->and($result->exception->getPrevious())->toBeInstanceOf(JsonException::class)
        ->and($this->transport->getRecorded())->toHaveCount(1);
})->with(['application/json', 'Application/JSON; charset=utf-8', 'application/problem+json']);

it('сохраняет false и пустой список в JSON отдельно от query', function (mixed $payload, string $body): void {
    $this->transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $result = (new JsonPayloadRequest($payload))->setClient($this->client)->send()->raw();
    $prepared = $this->transport->getRecorded()[0];
    expect($result->isSuccess())->toBeTrue()
        ->and($prepared->body)->toBe($body)
        ->and($prepared->url)->toContain('query=fixture-query');
})->with([[false, 'false'], [[], '[]'], [['flag' => false, 'items' => []], '{"flag":false,"items":[]}']]);

it('не повторяет PSR request failure и общий client failure как сетевые', function (bool $invalidRequest): void {
    $original = $invalidRequest
        ? new GuzzleRequestException('fixture invalid request', new Request('GET', 'https://api.test'))
        : new class('fixture protocol failure') extends RuntimeException implements ClientExceptionInterface {};
    $this->transport->fake(['*' => static fn () => throw $original]);
    $result = (new CacheProbeRequest())->setClient($this->client)->send()->raw();
    expect($result->errors->first()->code->value)->toBe($invalidRequest ? 'invalid_request' : 'transport_error')
        ->and($result->exception->getPrevious())->toBe($original)
        ->and($this->transport->getRecorded())->toHaveCount(1);
})->with([false, true]);

it('throwOnErrors сохраняет тип ошибки JSON в sync и promise API', function (bool $async): void {
    $client = new TestClient($this->config->with(throwOnErrors: true), $this->transport);
    $request = (new JsonPayloadRequest("\xB1"))->setClient($client);
    expect(static function () use ($request, $async): void {
        ($async ? $request->sendAsync()->wait() : $request->send())->raw();
    })->toThrow(SerializationException::class)
        ->and($this->transport->getRecorded())->toHaveCount(0);
})->with([false, true]);

it('batch сохраняет отдельные ошибки сериализации и JSON-ответа', function (): void {
    $this->transport->fake(['*' => MockResponse::sequence([
        MockResponse::make('{broken', 200),
        MockResponse::success(['ok' => true]),
    ])]);
    $batch = (new BatchExecutor($this->client, [
        new JsonPayloadRequest("\xB1"), new CacheProbeRequest(), new CacheProbeRequest(),
    ]))->withFailStrategy(FailStrategy::Partial)->send();
    expect($batch->get(0)->errors->first()->code->value)->toBe('serialization_error')
        ->and($batch->get(1)->errors->first()->code->value)->toBe('response_decoding_error')
        ->and($batch->get(2)->isSuccess())->toBeTrue()
        ->and($this->transport->getRecorded())->toHaveCount(2);
});
