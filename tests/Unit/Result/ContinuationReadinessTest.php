<?php

declare(strict_types=1);

use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Continuation\ContinuationAwaitOptions;
use ApiSutra\Continuation\ContinuationContext;
use ApiSutra\Continuation\ContinuationOutcome;
use ApiSutra\Continuation\ContinuationService;
use ApiSutra\Continuation\ContinuationState;
use ApiSutra\Continuation\FinalPathStateResolver;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Continuation\ContinuationMode;
use ApiSutra\Enums\Continuation\ContinuationStatus;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Exceptions\Configuration\ContinuationConfigurationException;
use ApiSutra\Exceptions\Continuation\ContinuationAwaitException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Result\ContinuationTokenExtractorInterface;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResolvedResultFactory;
use ApiSutra\Result\ResultHandle;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Continuation\CountingFinalDto;
use ApiSutra\Tests\Stubs\Continuation\CompositeFinalRequest;
use ApiSutra\Tests\Stubs\Continuation\ContextFinalDto;
use ApiSutra\Tests\Stubs\Continuation\ContextRequest;
use ApiSutra\Tests\Stubs\Continuation\ContextStateResolver;
use ApiSutra\Tests\Stubs\Continuation\DefaultsFinalRequest;
use ApiSutra\Tests\Stubs\Continuation\DelegatingClient;
use ApiSutra\Tests\Stubs\Continuation\InvalidFinalDto;
use ApiSutra\Tests\Stubs\Continuation\InvalidResolverRequest;
use ApiSutra\Tests\Stubs\Continuation\RecordingStateResolver;
use ApiSutra\Tests\Stubs\Continuation\TerminalRequest;
use ApiSutra\Tests\Stubs\Continuation\WithoutCriterionRequest;
use ApiSutra\Tests\Stubs\Dto\ContinuationFinalDto;
use ApiSutra\Tests\Stubs\MetadataIsolation\CastDto;
use ApiSutra\Tests\Stubs\MetadataIsolation\DefaultsDto;
use ApiSutra\Tests\Stubs\Requests\ContinuationPollRequest;
use ApiSutra\Tests\Stubs\Requests\ContinuationStartRequest;
use ApiSutra\Tests\Stubs\Continuation\UndeclaredRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Pipeline\PipelineContext;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * @param array<string, mixed> $start
 * @param list<array<string, mixed>> $polls
 * @param array<string, mixed> $overrides
 * @return array{TestClient, MockTransport}
 */
function continuationReadinessClient(array $start = [], array $polls = [], array $overrides = []): array
{
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $fakes = [];
    foreach (
        [ContinuationStartRequest::class, DefaultsFinalRequest::class, WithoutCriterionRequest::class,
        TerminalRequest::class, InvalidResolverRequest::class, UndeclaredRequest::class, ContextRequest::class] as $class
    ) {
        $fakes[$class] = MockResponse::success($start);
    }
    if ($polls !== []) {
        $fakes[ContinuationPollRequest::class] = MockResponse::sequence(array_map(
            static fn (array $payload): MockResponse => MockResponse::success($payload),
            $polls,
        ));
    }
    $transport->fake($fakes);
    $extractor = new class implements ContinuationTokenExtractorInterface {
        public function extract(ExecutionResult $result): ?string
        {
            $token = $result->response?->json('operationToken');
            return is_string($token) ? $token : null;
        }
    };
    $config = new ClientConfig(...array_replace([
        'baseUrl' => 'https://continuation.test',
        'continuationTokenExtractor' => $extractor,
        'defaultPollRequest' => ContinuationPollRequest::class,
    ], $overrides));
    return [new TestClient($config, $transport), $transport];
}

function captureContinuationError(callable $operation): ContinuationAwaitException
{
    try {
        $operation();
    } catch (ContinuationAwaitException $exception) {
        return $exception;
    }
    throw new LogicException('Ожидалась ContinuationAwaitException');
}

beforeEach(function (): void {
    CountingFinalDto::$created = 0;
});

it('возвращает Ready в Sync и Auto без polling', function (ContinuationMode $mode): void {
    [$client, $transport] = continuationReadinessClient(['data' => ['value' => 'ready']], overrides: [
        'defaultContinuationMode' => $mode,
    ]);
    $handle = $client->send(new ContinuationStartRequest('job'));
    $result = $handle->await();
    expect($result->value)->toBe('ready')->and($handle->awaitAs(ContinuationFinalDto::class))->toBe($result);
    $transport->assertNotSent(ContinuationPollRequest::class);
})->with([ContinuationMode::Sync, ContinuationMode::Auto]);

it('завершает Ready с неправильным типом поля немедленной ошибкой с полным путём', function (ContinuationMode $mode, bool $token): void {
    $payload = ['data' => ['value' => []]];
    if ($token) {
        $payload['operationToken'] = 'synthetic-token';
    }
    [$client, $transport] = continuationReadinessClient($payload, overrides: ['defaultContinuationMode' => $mode]);
    $handle = $client->send(new ContinuationStartRequest('job'));
    $error = captureContinuationError(fn () => $handle->await());
    expect($error->reason)->toBe('final_hydration_failed')->and($error->attempts)->toBe(1)
        ->and($error->lastResult)->toBe($handle->raw())
        ->and($error->getPrevious())->toBeInstanceOf(HydrationException::class)
        ->and($error->getPrevious()->path)->toBe('data.value');
    $transport->assertNotSent(ContinuationPollRequest::class);
})->with([ContinuationMode::Sync, ContinuationMode::Auto])->with([false, true]);

it('не принимает DTO с defaults за финал при отсутствующем или null unwrap', function (bool $hasNull): void {
    $start = ['operationToken' => 'synthetic-token'];
    if ($hasNull) {
        $start['data'] = null;
    }
    [$client, $transport] = continuationReadinessClient($start, [['data' => ['value' => 'done']]]);
    $dto = $client->send(new DefaultsFinalRequest())->await(new ContinuationAwaitOptions(1, 0));
    expect($dto->value)->toBe('done');
    $transport->assertSent(ContinuationPollRequest::class, times: 1);
})->with([false, true]);

it('в Sync возвращает final_not_ready для Pending', function (): void {
    [$client, $transport] = continuationReadinessClient(['operationToken' => 'synthetic-token'], overrides: [
        'defaultContinuationMode' => ContinuationMode::Sync,
    ]);
    $error = captureContinuationError(fn () => $client->send(new ContinuationStartRequest('job'))->await());
    expect($error->reason)->toBe('final_not_ready')->and($error->attempts)->toBe(1);
    $transport->assertNotSent(ContinuationPollRequest::class);
});

it('разделяет лимит poll-запросов и число оценённых результатов без паузы после лимита', function (ContinuationMode $mode): void {
    [$client, $transport] = continuationReadinessClient(
        ['operationToken' => 'synthetic-token'],
        [['operationToken' => 'synthetic-token']],
        ['defaultContinuationMode' => $mode],
    );
    // Большая пауза выявляет ошибочное ожидание после последней разрешённой попытки.
    $error = captureContinuationError(fn () => $client->send(new ContinuationStartRequest('job'))
        ->await(new ContinuationAwaitOptions(1, 5000)));
    expect($error->reason)->toBe('attempts_exhausted')
        ->and($error->attempts)->toBe($mode === ContinuationMode::Auto ? 2 : 1)
        ->and($error->lastResult->requestClass)->toBe(ContinuationPollRequest::class);
    $transport->assertSent(ContinuationPollRequest::class, times: 1);
})->with([ContinuationMode::Auto, ContinuationMode::Async]);

it('продолжает несколько Pending и не оценивает старт в Async', function (): void {
    $resolver = new RecordingStateResolver();
    [$client, $transport] = continuationReadinessClient(
        ['phase' => 'failed', 'operationToken' => 'synthetic-token'],
        [['phase' => 'pending', 'operationToken' => 'next'], ['phase' => 'ready', 'data' => ['value' => 'ok']]],
        ['defaultContinuationMode' => ContinuationMode::Async, 'continuationStateResolver' => $resolver],
    );
    $dto = $client->send(new WithoutCriterionRequest())->await(new ContinuationAwaitOptions(2, 0));
    expect($dto->value)->toBe('ok')->and($resolver->contexts)->toHaveCount(2)
        ->and($resolver->contexts[0])->toBe($resolver->contexts[1]);
    $transport->assertSent(ContinuationPollRequest::class, times: 2);
});

it('после Ready с ошибкой не расходует оставшиеся poll-попытки', function (): void {
    [$client, $transport] = continuationReadinessClient(['operationToken' => 'start'], [
        ['operationToken' => 'next', 'data' => ['value' => []]],
        ['data' => ['value' => 'late']],
    ]);
    $error = captureContinuationError(fn () => $client->send(new ContinuationStartRequest('job'))
        ->await(new ContinuationAwaitOptions(2, 0)));
    expect($error->reason)->toBe('final_hydration_failed')->and($error->attempts)->toBe(2);
    $transport->assertSent(ContinuationPollRequest::class, times: 1);
});

it('различает missing token и отсутствующий extractor', function (): void {
    [$client] = continuationReadinessClient();
    $error = captureContinuationError(fn () => $client->send(new ContinuationStartRequest('job'))->await());
    expect($error->reason)->toBe('continuation_token_missing');
    [$withoutExtractor] = continuationReadinessClient(overrides: ['continuationTokenExtractor' => null]);
    expect(fn () => $withoutExtractor->send(new ContinuationStartRequest('job'))->await())
        ->toThrow(ContinuationConfigurationException::class, 'continuationTokenExtractor');
});

it('доставляет Failed даже при token и сохраняет тип исключения провайдера', function (bool $failed): void {
    $resolver = new RecordingStateResolver();
    $resolver->state = ContinuationState::failed();
    [$client, $transport] = continuationReadinessClient(overrides: ['continuationStateResolver' => $resolver]);
    $providerError = new RuntimeException('synthetic provider failure');
    $result = new ExecutionResult(
        ['operationToken' => 'synthetic-token'],
        $failed ? ResultStatus::FAILED : ResultStatus::SUCCESS,
        new ErrorCollection([]),
        exception: $failed ? $providerError : null,
    );
    if ($failed) {
        expect(fn () => $client->continuation()->resolveFromStartResult($result))->toThrow($providerError);
    } else {
        $error = captureContinuationError(fn () => $client->continuation()->resolveFromStartResult($result));
        expect($error->reason)->toBe('continuation_failed')->and($error->lastResult)->toBe($result);
    }
    $transport->assertNotSent(ContinuationPollRequest::class);
})->with([false, true]);

it('не поглощает исключение resolver и переносит его через ClientConfig::with', function (): void {
    $resolver = new RecordingStateResolver();
    $resolver->error = new LogicException('synthetic resolver failure');
    [$client, $transport] = continuationReadinessClient(overrides: ['continuationStateResolver' => $resolver]);
    expect($client->getConfig()->with()->continuationStateResolver)->toBe($resolver)
        ->and($client->getConfig()->with(continuationStateResolver: null)->continuationStateResolver)->toBeNull();
    expect(fn () => $client->send(new WithoutCriterionRequest())->await())->toThrow($resolver->error);
    $transport->assertNotSent(ContinuationPollRequest::class);
});

it('соблюдает приоритет атрибута, unwrap и resolver клиента', function (): void {
    $resolver = new RecordingStateResolver();
    $resolver->error = new LogicException('нижний resolver не должен вызываться');
    [$client] = continuationReadinessClient(['data' => ['value' => 'ok']], overrides: [
        'continuationStateResolver' => $resolver,
    ]);
    $error = captureContinuationError(fn () => $client->send(new TerminalRequest())->await());
    expect($error->reason)->toBe('continuation_failed');
    expect($client->send(new ContinuationStartRequest('job'))->await()->value)->toBe('ok')
        ->and($resolver->contexts)->toBe([]);
});

it('отклоняет ожидание без критерия и неверный класс resolver до polling', function (string $class): void {
    [$client, $transport] = continuationReadinessClient(['operationToken' => 'synthetic-token']);
    expect(fn () => $client->send(new $class())->await())->toThrow(ContinuationConfigurationException::class);
    $transport->assertNotSent(ContinuationPollRequest::class);
})->with([WithoutCriterionRequest::class, InvalidResolverRequest::class, UndeclaredRequest::class]);

it('передаёт контекст для необъявленного await и возвращает raw payload, включая null', function (mixed $payload): void {
    $resolver = new RecordingStateResolver();
    $resolver->state = ContinuationState::ready($payload);
    [$client, $transport] = continuationReadinessClient(overrides: ['continuationStateResolver' => $resolver]);
    $handle = $client->send(new UndeclaredRequest());
    expect($handle->await())->toBe($payload)->and($handle->await())->toBe($payload);
    $context = $resolver->contexts[0];
    expect($context->finalType)->toBeNull()->and($context->unwrap)->toBeNull()
        ->and($context->sourceRequestClass)->toBe(UndeclaredRequest::class)
        ->and($context->mode)->toBe(ContinuationMode::Auto)->and($resolver->contexts)->toHaveCount(1);
    $transport->assertNotSent(ContinuationPollRequest::class);
})->with([null, false, 0, 'ready', [['value' => 'raw']]]);

it('одинаково оборачивает scalar Ready в ожидании и сохранённом outcome', function (?string $path): void {
    $resolver = new RecordingStateResolver();
    $resolver->state = ContinuationState::ready('secret-scalar', $path);
    [$client] = continuationReadinessClient(overrides: ['continuationStateResolver' => $resolver]);
    $handle = $client->send(new WithoutCriterionRequest());
    $error = captureContinuationError(fn () => $handle->await());
    expect($error->reason)->toBe('final_hydration_failed')
        ->and($error->getPrevious()->reason)->toBe('unexpected_response_shape')
        ->and($error->getPrevious()->path)->toBe($path ?? '$');
    $outcome = new ContinuationOutcome('secret-scalar', 'secret-scalar', $path, $handle->raw(), 3);
    $cached = captureContinuationError(fn () => $client->continuation()->hydrateOutcome($outcome, CountingFinalDto::class));
    expect($cached->attempts)->toBe(3)->and($cached->getPrevious()->path)->toBe($path ?? '$')
        ->and(CountingFinalDto::$created)->toBe(0)
        ->and(json_encode($cached->context()))->not->toContain('secret-scalar');
})->with([null, 'data']);

it('меняет тип cached await из исходного payload и сохраняет кеш при ошибке', function (): void {
    [$client, $transport] = continuationReadinessClient(['data' => ['value' => 'raw-value']]);
    $handle = $client->send(new ContinuationStartRequest('job'));
    $first = $handle->awaitAs(CountingFinalDto::class);
    expect($first->renamed)->toBe('raw-value')->and(CountingFinalDto::$created)->toBe(1)
        ->and($handle->awaitAs(CountingFinalDto::class))->toBe($first);
    $second = $handle->awaitAs(ContinuationFinalDto::class);
    expect($second->value)->toBe('raw-value');
    $error = captureContinuationError(fn () => $handle->awaitAs(InvalidFinalDto::class));
    expect($error->getPrevious()->path)->toBe('data.count')->and($error->lastResult)->toBe($handle->raw())
        ->and($error->attempts)->toBe(1)->and($handle->await())->toBe($second)
        ->and($handle->awaitAs(ContinuationFinalDto::class))->toBe($second);
    $transport->assertNotSent(ContinuationPollRequest::class);
});

it('использует metadata cache клиента через sync, async и вложенные ResultHandle', function (string $entry): void {
    [$client] = continuationReadinessClient(['data' => ['value' => 'ok']]);
    $request = new ContinuationStartRequest('job');
    $context = new PipelineContext($request, $client->getConfig(), 'parent');
    $handle = match ($entry) {
        'sync' => $client->send($request),
        'async' => $client->sendAsync($request)->wait(),
        'nested-sync' => $client->sendInContext($request, $context, RequestRole::Nested),
        'nested-async' => $client->sendInContextAsync($request, $context, RequestRole::Nested)->wait(),
    };
    expect($handle->awaitAs(CountingFinalDto::class)->renamed)->toBe('ok')
        ->and($client->getAttributeMetadataCache()->get(CountingFinalDto::class . ':hydration-plan'))->not->toBeNull();
})->with(['sync', 'async', 'nested-sync', 'nested-async']);

it('требует гидратор у стороннего клиента и использует переданный экземпляр', function (): void {
    [$client] = continuationReadinessClient(['data' => ['value' => 'ok']]);
    expect(fn () => new ContinuationService($client))->toThrow(ArgumentCountError::class);
    $cache = new AttributeMetadataCache();
    $other = new DelegatingClient($client, new Hydrator(new CastRegistry(), $cache));
    $request = new ContinuationStartRequest('job');
    $start = $client->send($request)->raw();
    expect($other->continuation()->awaitFromStartResult($start, $request)->value)->toBe('ok')
        ->and($cache->get(ContinuationFinalDto::class . ':hydration-plan'))->not->toBeNull();
    $standalone = new DelegatingClient($client, Hydrator::default());
    expect($standalone->continuation()->awaitFromStartResult($start, $request)->value)->toBe('ok');
});

it('сохраняет исправление 031 при ожиданиях с кешем и без него', function (Environment $environment): void {
    [$client] = continuationReadinessClient(['data' => ['value' => 'ok', 'number' => 0]], overrides: [
        'environment' => $environment,
    ]);
    $first = $client->send(new DefaultsFinalRequest())->await();
    $second = $client->send(new DefaultsFinalRequest())->await();
    $first->state->value = 99;
    expect($second->state->value)->toBe(0)->and($second->state)->not->toBe($first->state);
    foreach ([1, 2] as $unused) {
        expect($client->send(new DefaultsFinalRequest())->awaitAs(CastDto::class)->number)->toBe(1);
    }
})->with([Environment::Production, Environment::Testing]);

it('отклоняет FinalPathStateResolver без пути и ResultHandle без клиента', function (): void {
    [$client] = continuationReadinessClient();
    $result = $client->send(new ContinuationStartRequest('job'))->raw();
    expect(fn () => (new FinalPathStateResolver())->resolve($result, new ContinuationContext(null, null, null, ContinuationMode::Auto)))
        ->toThrow(ContinuationConfigurationException::class);
    expect(fn () => (new ResultHandle($result, new ResolvedResultFactory()))->await())
        ->toThrow(ContinuationConfigurationException::class);
});

it('строит контекст всех входов и применяет runtime mode поверх декларации', function (string $entry): void {
    [$client] = continuationReadinessClient(['operationToken' => 'start'], [[]], [
        'continuationStateResolver' => new ContextStateResolver(),
        'defaultContinuationMode' => ContinuationMode::Auto,
    ]);
    $request = new ContextRequest();
    $dto = match ($entry) {
        'declared' => $client->send($request)->await(),
        'override' => $client->send($request->asProviderAsync())->awaitAs(ContextFinalDto::class, new ContinuationAwaitOptions(1, 0)),
        'direct' => $client->continuation()->awaitFromStartResult($client->send($request)->raw(), $request),
        'by-token' => $client->continuation()->awaitByToken('token', ContextRequest::class, new ContinuationAwaitOptions(1, 0)),
        'by-token-as' => $client->continuation()->awaitByTokenAs('token', ContextFinalDto::class, new ContinuationAwaitOptions(1, 0)),
        'undeclared-as' => $client->send(new UndeclaredRequest())->awaitAs(ContextFinalDto::class),
    };
    $context = $dto->context;
    expect($context->finalType)->toBe(ContextFinalDto::class)
        ->and($context->unwrap)->toBe(in_array($entry, ['by-token-as', 'undeclared-as'], true) ? null : 'data')
        ->and($context->sourceRequestClass)->toBe(match ($entry) {
            'by-token-as' => null,
            'undeclared-as' => UndeclaredRequest::class,
            default => ContextRequest::class,
        })
        ->and($context->mode)->toBe(match ($entry) {
            'override', 'by-token', 'by-token-as' => ContinuationMode::Async,
            'undeclared-as' => ContinuationMode::Auto,
            default => ContinuationMode::Sync,
        });
})->with(['declared', 'override', 'direct', 'by-token', 'by-token-as', 'undeclared-as']);

it('определяет присутствие финала по JSON-ответу и не принимает корневой список за объект', function (string $body, ContinuationStatus $status, mixed $payload): void {
    [$client, $transport] = continuationReadinessClient();
    $transport->fake([UndeclaredRequest::class => MockResponse::make($body, headers: ['Content-Type' => 'application/json'])]);
    $result = $client->send(new UndeclaredRequest())->raw();
    $state = (new FinalPathStateResolver())->resolve($result, new ContinuationContext(null, 'data', null, ContinuationMode::Auto));
    expect($state->status)->toBe($status)->and($state->payload)->toBe($payload);
})->with([
    ['{"data":false}', ContinuationStatus::Ready, false],
    ['{"data":0}', ContinuationStatus::Ready, 0],
    ['{"data":[]}', ContinuationStatus::Ready, []],
    ['{"data":null}', ContinuationStatus::Pending, null],
    ['{}', ContinuationStatus::Pending, null],
    ['[{"data":{"value":"wrong-root"}}]', ContinuationStatus::Pending, null],
    ['null', ContinuationStatus::Pending, null],
]);

it('передаёт безопасный контекст ошибки в автоматический лог без debug', function (): void {
    $logger = new class extends AbstractLogger {
        /** @var list<array{message: string, context: array<string, mixed>}> */
        public array $entries = [];
        public function log(mixed $level, Stringable|string $message, array $context = []): void
        {
            $this->entries[] = ['message' => (string) $message, 'context' => $context];
        }
    };
    [$client] = continuationReadinessClient([
        'operationToken' => 'secret-token', 'data' => ['value' => ['secret-payload']],
    ], overrides: ['debug' => false, 'logger' => $logger, 'logLevel' => LogLevel::ERROR]);
    $handle = $client->send(new ContinuationStartRequest('job'));
    $error = captureContinuationError(fn () => $handle->await());
    expect($error->context())->toMatchArray([
        'reason' => 'final_hydration_failed', 'attempts' => 1,
        'httpStatus' => 200, 'traceId' => $handle->raw()->traceId,
    ])->and($error->context()['hydration']['path'])->toBe('data.value')
        ->and($logger->entries)->toHaveCount(1)
        ->and($logger->entries[0]['context'])->toMatchArray($error->context())
        ->and($logger->entries[0]['context']['trace'])->toBe($error->trace->traceId)
        ->and($logger->entries[0]['context']['event'])->toBe('failed')
        ->and(json_encode($logger->entries))->not->toContain('secret-token', 'secret-payload')
        ->and($error->lastResult->response->json('operationToken'))->toBe('secret-token');
});

it('доставляет исключение failed Pending без token без обёртки', function (): void {
    $resolver = new RecordingStateResolver();
    $resolver->state = ContinuationState::pending();
    [$client] = continuationReadinessClient(overrides: ['continuationStateResolver' => $resolver]);
    $exception = new RuntimeException('ошибка провайдера');
    $result = new ExecutionResult(null, ResultStatus::FAILED, new ErrorCollection([]), exception: $exception);
    expect(fn () => $client->continuation()->awaitFromStartResult($result))->toThrow($exception);
});

it('использует гидратор клиента для финала composite в sync и async', function (bool $async): void {
    [$client] = continuationReadinessClient(['value' => 'aggregate'], overrides: [
        'continuationStateResolver' => new RecordingStateResolver(),
    ]);
    $request = (new CompositeFinalRequest())->setClient($client);
    $handle = $async ? $client->sendAsync($request)->wait() : $client->send($request);
    expect($handle->await()->renamed)->toBe('aggregate')
        ->and($client->getAttributeMetadataCache()->get(CountingFinalDto::class . ':hydration-plan'))->not->toBeNull();
})->with([false, true]);

it('ожидает результаты pool через ContinuationService клиента', function (): void {
    [$client] = continuationReadinessClient(['data' => ['value' => 'pooled']]);
    $values = [];
    $client->pool([new ContinuationStartRequest('one'), new ContinuationStartRequest('two')])
        ->withResponseHandler(function (ExecutionResult $result, RequestInterface $request) use ($client, &$values): void {
            $values[] = $client->continuation()->awaitFromStartResult($result, $request, CountingFinalDto::class)->renamed;
        })->send();
    expect($values)->toBe(['pooled', 'pooled'])
        ->and($client->getAttributeMetadataCache()->get(CountingFinalDto::class . ':hydration-plan'))->not->toBeNull();
});

it('awaitByTokenAs требует resolver клиента до отправки poll', function (): void {
    [$client, $transport] = continuationReadinessClient();
    expect(fn () => $client->continuation()->awaitByTokenAs('token', ContinuationFinalDto::class))
        ->toThrow(ContinuationConfigurationException::class);
    $transport->assertNotSent(ContinuationPollRequest::class);
});

it('сохраняет result-first и throwOnErrors стартового запроса независимо от await', function (): void {
    [$client, $transport] = continuationReadinessClient();
    $transport->fake([UndeclaredRequest::class => MockResponse::make(['message' => 'bad request'], 400)]);
    $result = $client->send(new UndeclaredRequest())->raw();
    expect($result->isFailed())->toBeTrue()->and($result->response->status)->toBe(400)
        ->and($result->response->json('message'))->toBe('bad request');
    $throwing = new TestClient($client->getConfig()->with(throwOnErrors: true), $transport);
    expect(fn () => $throwing->send(new UndeclaredRequest())->raw())->toThrow($result->exception::class);
});
