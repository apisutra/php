<?php

declare(strict_types=1);

use ApiSutra\Tests\Stubs\Execution\CountingMetaExtractor;
use ApiSutra\Diagnostics\ExecutionTrace;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Tests\Stubs\Execution\RecordingExecutor;
use ApiSutra\Localization\Message;
use ApiSutra\Config\ResultExceptionConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Continuation\ContinuationAwaitOptions;
use ApiSutra\Enums\Execution\ExecutionMode;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Enums\Pipeline\PipelineStage;
use ApiSutra\Execution\BatchExecutor;
use ApiSutra\Execution\PoolExecutor;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Continuation\DelegatingClient;
use ApiSutra\Tests\Stubs\Continuation\WithoutCriterionRequest;
use ApiSutra\Tests\Stubs\Execution\CompositeRequest;
use ApiSutra\Tests\Stubs\Execution\ExecutorClient;
use ApiSutra\Tests\Stubs\Execution\PollResolver;
use ApiSutra\Tests\Stubs\Execution\TokenExtractor;
use ApiSutra\Tests\Stubs\Requests\ContinuationPollRequest;
use ApiSutra\Tests\Stubs\Requests\WrappedPaginatedRequest;
use ApiSutra\Tests\Stubs\ResultContract\DefaultRequest;
use ApiSutra\Tests\Stubs\ResultContract\RecordingFactory;
use ApiSutra\Tests\Stubs\ResultContract\ValueExtension;
use ApiSutra\Tests\Support\ExecutionDelivery;
use ApiSutra\Transport\MockTransport;

/** @return array{ExecutorClient, MockTransport, RecordingFactory} */
function canonicalClient(bool $throw, bool $mismatch = true, array $options = []): array
{
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake(['*' => MockResponse::success(['id' => 1])]);
    $factory = new RecordingFactory(fallback: true);
    $config = new ClientConfig(...array_replace([
        'baseUrl' => 'https://canonical.test',
        'throwOnErrors' => $throw,
        'resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory),
        'extensions' => $mismatch ? [new ValueExtension('invalid')] : [],
    ], $options));
    return [new ExecutorClient($config, $transport), $transport, $factory];
}

it('канонический sync async возвращает FAILED до публичной выдачи', function (bool $throw, bool $async): void {
    [$client, $transport, $factory] = canonicalClient($throw);
    $result = $async ? $client->execution()->executeAsync(new DefaultRequest())->wait() : $client->execution()->execute(new DefaultRequest());
    expect($result->isFailed())->toBeTrue()
        ->and($result->errors->first()->context['reason'])->toBe('response_type_mismatch')
        ->and($result->response->status)->toBe(200)
        ->and(array_column($result->audit, 'stage'))->toBe([PipelineStage::Started, PipelineStage::HttpRequest, PipelineStage::HttpResponse, PipelineStage::Failed])
        ->and($factory->results)->toBeEmpty()->and($transport->getRecorded())->toHaveCount(1);
})->with([false, true])->with([false, true]);

it('декоратор ClientInterface сохраняет каноническую ошибку batch pool', function (bool $throw, bool $wrapped, string $kind): void {
    [$client, $transport, $factory] = canonicalClient($throw);
    $client = $wrapped ? new DelegatingClient($client, Hydrator::default()) : $client;
    $executor = $kind === 'batch'
        ? new BatchExecutor($client, [new DefaultRequest()], ExecutionMode::Parallel)
        : new PoolExecutor($client, [new DefaultRequest()]);
    $aggregate = ExecutionDelivery::result($executor->send(...), $throw, $factory);
    $child = $aggregate->nested[0];
    expect($child->errors->first()->code->value)->toBe('hydration_error')
        ->and($child->errors->first()->context['reason'])->toBe('response_type_mismatch')
        ->and($child->response->status)->toBe(200)->and($child->trace)->not->toBeNull()
        ->and($aggregate->exception)->toBe($child->exception)->and($transport->getRecorded())->toHaveCount(1);
})->with([false, true])->with([false, true])->with(['batch', 'pool']);

it('продолжение batch зависит от стратегии и не зависит от throwOnErrors', function (bool $throw, ExecutionMode $mode, FailStrategy $strategy): void {
    [$client, $transport, $factory] = canonicalClient($throw);
    $batch = new BatchExecutor($client, [new DefaultRequest(), new DefaultRequest()], $mode, $strategy, 1);
    $result = ExecutionDelivery::result($batch->send(...), $throw, $factory);
    $count = $strategy === FailStrategy::FailAll ? 1 : 2;
    expect($result->nested)->toHaveCount($count)->and($transport->getRecorded())->toHaveCount($count);
    foreach ($result->nested as $child) {
        expect($child->response->status)->toBe(200)->and($child->errors->first()->context['reason'])->toBe('response_type_mismatch');
    }
})->with([false, true])->with(ExecutionMode::cases())->with(FailStrategy::cases());

it('composite сохраняет собственный исход и завершённых детей до выдачи', function (bool $throw): void {
    [$client, $transport, $factory] = canonicalClient($throw);
    $request = new CompositeRequest()->setClient($client);
    $result = ExecutionDelivery::result(fn () => $client->send($request)->raw(), $throw, $factory);
    expect($result->requestClass)->toBe(CompositeRequest::class)->and($result->nested)->toHaveCount(1)
        ->and($result->nested[0]->requestClass)->toBe(DefaultRequest::class)
        ->and($result->nested[0]->trace->parentExecutionId)->toBe($result->trace->executionId)
        ->and($result->nested[0]->trace->executionId)->not->toBe($result->trace->executionId)
        ->and($transport->getRecorded())->toHaveCount(1);
})->with([false, true]);

it('пагинация отдаёт полный агрегат одной factory без повторной классификации', function (bool $throw, bool $throughClient): void {
    [$client, $transport, $factory] = canonicalClient($throw, options: ['paginationRule' => $throughClient ? PaginationRule::pages(1) : null]);
    $request = new WrappedPaginatedRequest()->setClient($client);
    $result = ExecutionDelivery::result(fn () => $throughClient ? $client->send($request)->raw() : $request->paginate()->pages(1), $throw, $factory);
    expect($result->requestClass)->toBe(WrappedPaginatedRequest::class)->and($result->nested)->toHaveCount(1)
        ->and($result->errors->first()->context['reason'])->toBe('response_type_mismatch')
        ->and($result->nested[0]->response->status)->toBe(200)
        ->and($result->nested[0]->trace->parentExecutionId)->toBe($result->trace->executionId)
        ->and($transport->getRecorded())->toHaveCount(1);
})->with([false, true])->with([false, true]);

it('failed poll передаётся resolver и может быть Pending при token', function (bool $throw, bool $wrapped): void {
    $resolver = new PollResolver();
    $extractor = new CountingMetaExtractor();
    [$client, $transport, $factory] = canonicalClient($throw, false, [
        'retry' => new RetryConfig(attempts: 1),
        'continuationStateResolver' => $resolver,
        'resultMetaExtractor' => $extractor,
        'continuationTokenExtractor' => new TokenExtractor(),
        'defaultPollRequest' => ContinuationPollRequest::class,
        'paginationRule' => PaginationRule::pages(2),
    ]);
    $transport->fake([ContinuationPollRequest::class => MockResponse::sequence([
        MockResponse::make(['phase' => 'pending', 'operationToken' => 'test-token'], 503),
        MockResponse::success(['phase' => 'ready', 'data' => ['value' => 'done']]),
    ])]);
    $client = $wrapped ? new DelegatingClient($client, Hydrator::default()) : $client;
    $result = $client->continuation()->awaitByToken('test-token', WithoutCriterionRequest::class, new ContinuationAwaitOptions(2, 0));
    expect($result->value)->toBe('done')->and($resolver->statuses)->toBe([503, 200])->and($extractor->results)->toHaveCount(2)
        ->and($factory->results)->toBeEmpty()->and($transport->getRecorded())->toHaveCount(2);
})->with([false, true])->with([false, true]);

it('pool выбирает callback по результату независимо от throwOnErrors', function (bool $throw): void {
    [$client, $transport, $factory] = canonicalClient($throw);
    $seen = [];
    $pool = $client->pool([new DefaultRequest()])
        ->withResponseHandler(function () use (&$seen): void { $seen[] = 'response'; })
        ->withExceptionHandler(function (Throwable $exception) use (&$seen): void { $seen[] = $exception; });
    try {
        $result = $pool->send();
        expect($throw)->toBeFalse();
    } catch (Throwable $exception) {
        expect($throw)->toBeTrue();
        $result = $factory->results[1];
        expect($exception)->toBe($result->exception);
    }
    expect($seen)->toHaveCount(1)->and($seen[0])->toBe($result->nested[0]->exception)
        ->and($factory->results)->toHaveCount($throw ? 2 : 1)->and($transport->getRecorded())->toHaveCount(1);
})->with([false, true]);

it('extractor обслуживает failed страницу и агрегат без meta на обоих входах', function (bool $throughClient): void {
    $extractor = new CountingMetaExtractor();
    [$client, $transport] = canonicalClient(false, options: [
        'resultMetaExtractor' => $extractor,
        'paginationRule' => $throughClient ? PaginationRule::pages(1) : null,
    ]);
    $request = new WrappedPaginatedRequest()->setClient($client);
    $result = $throughClient ? $client->send($request)->raw() : $request->paginate()->pages(1);
    expect($result->isFailed())->toBeTrue()->and($extractor->results)->toHaveCount(2)
        ->and($extractor->results[0]->response->status)->toBe(200)->and($transport->getRecorded())->toHaveCount(1);
    expect($extractor->results[1]->nested[0])->toBe($result->nested[0]);
})->with([false, true]);

it('порт отклоняет противоречивый parent trace до HTTP при обеих формах вызова', function (bool $named): void {
    [$client, $transport] = canonicalClient(false, false);
    $request = new DefaultRequest();
    $root = $client->execution()->execute($request);
    $parent = $request->getContext();
    $other = ExecutionTrace::create();
    expect(fn () => $named
        ? $client->execution()->execute(request: new DefaultRequest(), parent: $parent, parentTrace: $other)
        : $client->execution()->execute(new DefaultRequest(), RequestRole::Root, $parent, $other))
        ->toThrow(ConfigurationException::class);
    expect($transport->getRecorded())->toHaveCount(1)->and($root->isSuccess())->toBeTrue();
})->with([false, true]);

it('await не локализует и не заменяет сбой контракта стороннего исполнителя', function (): void {
    [$client, $transport, $factory] = canonicalClient(false, false, [
        'localization' => 'ru',
        'continuationStateResolver' => new PollResolver(),
        'continuationTokenExtractor' => new TokenExtractor(),
        'defaultPollRequest' => ContinuationPollRequest::class,
    ]);
    $executor = new RecordingExecutor($client->execution());
    $executor->failure = $cause = new ConfigurationException(new Message('diagnostics.conflicting_trace'));
    $client->executorOverride = $executor;
    try {
        $client->continuation()->awaitByToken('token', WithoutCriterionRequest::class, new ContinuationAwaitOptions(2, 0));
        test()->fail('Ожидался исходный сбой executor');
    } catch (Throwable $exception) {
        expect($exception)->toBe($cause);
    }
    expect($factory->results)->toBeEmpty()->and($transport->getRecorded())->toBeEmpty();
});
