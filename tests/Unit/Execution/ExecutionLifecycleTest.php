<?php

declare(strict_types=1);

use ApiSutra\Tests\Stubs\Execution\CountingMetaExtractor;
use GuzzleHttp\Promise\RejectionException;
use ApiSutra\VO\Errors\RequestError;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\Pagination\Paginator;
use ApiSutra\Tests\Stubs\Requests\OffsetPaginatedRequest;
use ApiSutra\Tests\Stubs\ResultContract\ProviderFailure;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\ResultExceptionConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResultMeta;
use ApiSutra\Enums\Execution\ExecutionMode;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Enums\Pipeline\PipelineStage;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Exceptions\Configuration\ExceptionFactoryException;
use ApiSutra\Exceptions\Serialization\ResponseTypeMismatchException;
use ApiSutra\Execution\BatchExecutor;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResultMetaExtractorInterface;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Auth\RefreshingAuthenticator;
use ApiSutra\Tests\Stubs\Continuation\DelegatingClient;
use ApiSutra\Tests\Stubs\Execution\CompositeRequest;
use ApiSutra\Tests\Stubs\Execution\DeferredExecutor;
use ApiSutra\Tests\Stubs\Execution\ExecutorClient;
use ApiSutra\Tests\Stubs\Execution\RecordingExecutor;
use ApiSutra\Tests\Stubs\Requests\RefreshTokenRequest;
use ApiSutra\Tests\Stubs\Requests\ProtectedRequest;
use ApiSutra\Tests\Stubs\ResultContract\DefaultRequest;
use ApiSutra\Tests\Stubs\ResultContract\LocalMessageRequest;
use ApiSutra\Tests\Stubs\ResultContract\RecordingFactory;
use ApiSutra\Tests\Stubs\ResultContract\ValueExtension;
use ApiSutra\Tests\Stubs\Tracing\MemoryLogger;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Transport\MockTransport;

it('две обёртки executor видят root composite child и auth ровно один раз', function (bool $async): void {
    RefreshingAuthenticator::reset();
    RefreshingAuthenticator::$shouldRefresh = true;
    $transport = new MockTransport();
    $transport->fake([
        DefaultRequest::class => MockResponse::success(['id' => 1]),
        RefreshTokenRequest::class => MockResponse::success(['token' => 'fresh']),
    ]);
    $extractor = new CountingMetaExtractor();
    $client = new ExecutorClient(new ClientConfig(baseUrl: 'https://executor.test', auth: new RefreshingAuthenticator(), resultMetaExtractor: $extractor), $transport);
    $inner = new RecordingExecutor($client->execution());
    $outer = new RecordingExecutor($inner);
    $client->executorOverride = $outer;
    $request = new CompositeRequest()->setClient($client);
    $result = $async ? $client->sendAsync($request)->wait()->raw() : $client->send($request)->raw();
    expect($result->isSuccess())->toBeTrue()
        ->and(array_column($extractor->results, 'requestClass'))->toBe([RefreshTokenRequest::class, DefaultRequest::class])
        ->and($inner->requests)->toBe([CompositeRequest::class, DefaultRequest::class, RefreshTokenRequest::class])
        ->and($outer->requests)->toBe($inner->requests)->and($transport->getRecorded())->toHaveCount(2)
        ->and($result->nested[0]->nested[0]->trace->parentExecutionId)->toBe($result->nested[0]->trace->executionId);
    RefreshingAuthenticator::reset();
})->with([false, true]);

it('refresh остаётся single при глобальном правиле пагинации', function (): void {
    RefreshingAuthenticator::reset();
    RefreshingAuthenticator::$shouldRefresh = true;
    $transport = new MockTransport();
    $transport->fake([
        RefreshTokenRequest::class => MockResponse::success(['token' => 'fresh']),
        ProtectedRequest::class => MockResponse::success(['id' => 1, 'name' => 'ready']),
    ]);
    $client = new ExecutorClient(new ClientConfig(baseUrl: 'https://executor.test', auth: new RefreshingAuthenticator(), paginationRule: PaginationRule::pages(2)), $transport);
    $request = new ProtectedRequest()->setClient($client)->rules(PaginationRule::single());
    $result = $client->send($request)->raw();
    expect($result->isSuccess())->toBeTrue()->and($result->nested)->toHaveCount(1)->and($transport->getRecorded())->toHaveCount(2);
    RefreshingAuthenticator::reset();
});

it('callback failure завершает уже выданные Promise и не выдаёт следующие', function (string $where): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 1])]);
    $cause = new LogicException('callback failure');
    $factory = new RecordingFactory(fallback: true, failure: $where === 'factory' ? $cause : null);
    $logger = new MemoryLogger();
    $client = new ExecutorClient(new ClientConfig(
        baseUrl: 'https://executor.test', throwOnErrors: true, logger: $logger,
        extensions: $where === 'response' ? [] : [new ValueExtension('wrong')],
        resultExceptions: new ResultExceptionConfig(exceptionFactory: $factory),
    ), $transport);
    $deferred = new DeferredExecutor($client->execution());
    $client->executorOverride = $deferred;
    $callbacks = 0;
    $callback = function () use (&$callbacks, $cause): void { $callbacks++; throw $cause; };
    $pool = $client->pool([new DefaultRequest(), new DefaultRequest(), new DefaultRequest()], 2);
    $pool = $where === 'response' ? $pool->withResponseHandler($callback) : $pool->withExceptionHandler($callback);
    try {
        $pool->send();
        test()->fail('Ожидался сбой доставки');
    } catch (Throwable $exception) {
        if ($where === 'factory') {
            expect($exception)->toBeInstanceOf(ExceptionFactoryException::class)->and($exception->getPrevious())->toBe($cause);
        } else {
            expect($exception)->toBe($cause);
        }
    }
    expect($deferred->issued)->toBe(2)->and($deferred->completed)->toBe(2)
        ->and($callbacks)->toBe($where === 'factory' ? 0 : 1)
        ->and($factory->results)->toHaveCount($where === 'response' ? 0 : 1)
        ->and($transport->getRecorded())->toHaveCount(2);
})->with(['response', 'exception', 'factory']);

it('нарушение чужого async executor не превращается в HTTP ошибку и не оставляет начатых Promise', function (): void {
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $factory = new RecordingFactory();
    $client = new ExecutorClient(new ClientConfig(baseUrl: 'https://executor.test', resultExceptions: new ResultExceptionConfig(exceptionFactory: $factory), retry: new RetryConfig(attempts: 3, retryExceptions: [Throwable::class])), $transport);
    $deferred = new DeferredExecutor($client->execution());
    $cause = new LogicException('broken executor');
    $deferred->failure = $cause;
    $client->executorOverride = $deferred;
    expect(fn () => $client->pool([new DefaultRequest(), new DefaultRequest(), new DefaultRequest()], 2)->send())->toThrow($cause);
    expect($deferred->issued)->toBe(2)->and($deferred->completed)->toBe(2)
        ->and($factory->results)->toBeEmpty()->and($transport->getRecorded())->toBeEmpty();
});

it('причина агрегата определяется порядком входа а не завершения Promise', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 1])]);
    $client = new ExecutorClient(new ClientConfig(baseUrl: 'https://executor.test', extensions: [new ValueExtension('wrong')]), $transport);
    $deferred = new DeferredExecutor($client->execution(), reverse: true);
    $client->executorOverride = $deferred;
    $batch = new BatchExecutor($client, [new DefaultRequest(), new LocalMessageRequest()], ExecutionMode::Parallel, FailStrategy::Partial, 2);
    $result = $batch->send();
    expect($result->nested[0]->requestClass)->toBe(DefaultRequest::class)
        ->and($result->nested[1]->requestClass)->toBe(LocalMessageRequest::class)
        ->and($result->exception)->toBe($result->nested[0]->exception)
        ->and($deferred->completed)->toBe(2);
});

it('meta failure не переписывает готовый FAILED и не запускает extractor повторно', function (bool $mismatch): void {
    $extractor = new class implements ResultMetaExtractorInterface {
        public int $calls = 0;
        public function extract(ExecutionResult $result): ?ResultMeta
        {
            $this->calls++;
            throw new LogicException('meta failure');
        }
    };
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 1])]);
    $client = new ExecutorClient(new ClientConfig(baseUrl: 'https://executor.test', resultMetaExtractor: $extractor, extensions: $mismatch ? [new ValueExtension('wrong')] : []), $transport);
    $result = $client->execution()->execute(new DefaultRequest());
    expect($result->isFailed())->toBeTrue()->and($extractor->calls)->toBe(1)
        ->and($result->exception)->toBeInstanceOf($mismatch ? ResponseTypeMismatchException::class : LogicException::class)
        ->and($result->errors->all())->toHaveCount($mismatch ? 2 : 1)
        ->and($result->response->status)->toBe(200)
        ->and(array_column($result->audit, 'stage'))->toBe([PipelineStage::Started, PipelineStage::HttpRequest, PipelineStage::HttpResponse, PipelineStage::Failed]);
})->with([false, true]);

it('logical scope через декоратор сохраняет часы и logger клиента', function (): void {
    $clock = new VirtualClock();
    $logger = new MemoryLogger();
    $client = new ExecutorClient(new ClientConfig(baseUrl: 'https://executor.test', logger: $logger), new MockTransport(), clock: $clock);
    $wrapped = new DelegatingClient($client, Hydrator::default());
    $scope = $wrapped->execution()->createScope(DefaultRequest::class, traceId: 'logical');
    $scope->start();
    $clock->advance(7);
    $result = $scope->finish(new ExecutionResult(null, ResultStatus::SUCCESS, new ErrorCollection([])));
    expect($result->traceId)->toBe('logical')->and($result->audit[1]->duration)->toBe(7.0)
        ->and($logger->records)->toHaveCount(2);
});

it('ошибка порта при refresh сохраняется без HTTP retry и factory', function (): void {
    RefreshingAuthenticator::reset();
    RefreshingAuthenticator::$shouldRefresh = true;
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $factory = new RecordingFactory();
    $client = new ExecutorClient(new ClientConfig(
        baseUrl: 'https://executor.test', auth: new RefreshingAuthenticator(),
        retry: new RetryConfig(attempts: 3, retryExceptions: [Throwable::class]),
        resultExceptions: new ResultExceptionConfig(exceptionFactory: $factory),
    ), $transport);
    $executor = new RecordingExecutor($client->execution());
    $executor->failure = $cause = new LogicException('broken refresh executor');
    $executor->failureRequest = RefreshTokenRequest::class;
    $client->executorOverride = $executor;
    expect(fn () => $client->send(new ProtectedRequest()))->toThrow($cause);
    expect($executor->requests)->toBe([ProtectedRequest::class, RefreshTokenRequest::class])
        ->and($factory->results)->toBeEmpty()->and($transport->getRecorded())->toBeEmpty();
    RefreshingAuthenticator::reset();
});

it('чужой Promise с неверным значением прекращает выдачу после завершения начатых', function (bool $reject): void {
    $transport = new MockTransport();
    $client = new ExecutorClient(new ClientConfig(baseUrl: 'https://executor.test'), $transport);
    $executor = new DeferredExecutor($client->execution());
    $executor->invalidRejection = $reject;
    $executor->invalidResult = !$reject;
    $client->executorOverride = $executor;
    expect(fn () => $client->pool([new DefaultRequest(), new DefaultRequest(), new DefaultRequest()], 2)->send())
        ->toThrow($reject ? RejectionException::class : TypeError::class);
    expect($executor->issued)->toBe(2)->and($executor->completed)->toBe(2)->and($transport->getRecorded())->toBeEmpty();
})->with([false, true]);

it('первый FAILED без exception не заимствует exception следующего ребёнка', function (): void {
    $client = new ExecutorClient(new ClientConfig(baseUrl: 'https://executor.test'), new MockTransport());
    $executor = new DeferredExecutor($client->execution(), reverse: true);
    $executor->overrides = [
        new ExecutionResult(null, ResultStatus::FAILED, new ErrorCollection([
            new RequestError(ErrorCode::ExecutionError, 'first child'),
        ])),
        new ExecutionResult(null, ResultStatus::FAILED, new ErrorCollection([]), exception: new LogicException('second child')),
    ];
    $client->executorOverride = $executor;
    $result = new BatchExecutor($client, [new DefaultRequest(), new LocalMessageRequest()], ExecutionMode::Parallel, FailStrategy::Partial, 2)->send();
    expect($result->exception)->toBeNull()->and($result->nested)->toBe($executor->overrides);
    expect(fn () => $result->throw())->toThrow(SdkException::class, 'first child');
});

it('явный клиент paginator задаёт factory и локаль ошибки до первой страницы', function (bool $iterator): void {
    $factory = new RecordingFactory();
    $client = new ExecutorClient(new ClientConfig(
        baseUrl: 'https://executor.test', localization: 'ru', throwOnErrors: true,
        resultExceptions: new ResultExceptionConfig(exceptionFactory: $factory),
    ), new MockTransport());
    $paginator = new Paginator(new OffsetPaginatedRequest(), client: $client);
    expect(fn () => $iterator ? iterator_to_array($paginator) : $paginator->pages(1))
        ->toThrow(ProviderFailure::class);
    expect($factory->results)->toHaveCount(1)->and($factory->results[0]->exception)
        ->toBeInstanceOf(ConfigurationException::class)
        ->and($factory->messages[0])->not->toBe('Offset-based pagination requires limit')
        ->and(array_column($factory->results[0]->audit, 'stage'))->toBe([PipelineStage::Started, PipelineStage::Failed]);
})->with([false, true]);

it('FailAll завершает две уже выданные работы и не начинает третью', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 1])]);
    $client = new ExecutorClient(new ClientConfig(baseUrl: 'https://executor.test', extensions: [new ValueExtension('wrong')]), $transport);
    $executor = new DeferredExecutor($client->execution());
    $client->executorOverride = $executor;
    $result = new BatchExecutor($client, [new DefaultRequest(), new DefaultRequest(), new DefaultRequest()], ExecutionMode::Parallel, FailStrategy::FailAll, 2)->send();
    expect($result->isFailed())->toBeTrue()->and($result->nested)->toHaveCount(2)
        ->and($executor->issued)->toBe(2)->and($executor->completed)->toBe(2)
        ->and($transport->getRecorded())->toHaveCount(2);
});
