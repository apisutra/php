<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\ResultExceptionConfig;
use ApiSutra\Diagnostics\RedactionPolicy;
use ApiSutra\Enums\Pipeline\PipelineStage;
use ApiSutra\Execution\Async\AsyncTask;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Pagination\PageRequest;
use ApiSutra\Tests\Stubs\ResultContract\ProviderFailure;
use ApiSutra\Tests\Stubs\ResultContract\RecordingFactory;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Tracing\MemoryLogger;
use ApiSutra\Transport\MockTransport;
use Revolt\EventLoop;

it('сохраняет scope в ошибке самого обхода и раннем отказе iterator', function (bool $iterator, bool $explicit): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['data' => [1], 'meta' => ['page' => 1, 'per_page' => 1, 'has_more' => true]])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pagination.test'), $transport);
    $request = new PageRequest()->setClient($client);
    $paginator = ($explicit ? $request->withTraceId('traversal') : $request)->paginate()->withConcurrency(2);
    $result = $iterator ? iterator_to_array($paginator)[0] : $paginator->all();
    expect($result->isFailed())->toBeTrue()
        ->and($result->errors->first()->context['traceId'] ?? null)->toBe($result->traceId)
        ->and($result->traceId)->not->toBeNull()
        ->and(array_column($result->audit, 'stage'))->toBe([PipelineStage::Started, PipelineStage::Failed])
        ->and($transport->getRecorded())->toHaveCount($iterator ? 0 : 1);
    if ($explicit) {
        expect($result->traceId)->toBe('traversal');
    }
})->with([false, true])->with([false, true]);

it('доставляет ранний отказ iterator через настроенную фабрику ровно один раз', function (): void {
    $factory = new RecordingFactory();
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://pagination.test',
        localization: 'ru',
        throwOnErrors: true,
        resultExceptions: new ResultExceptionConfig(exceptionFactory: $factory),
    ), $transport);
    $paginator = new PageRequest()->setClient($client)->withTraceId('iterator')->paginate()->withConcurrency(2);
    expect(fn () => iterator_to_array($paginator))->toThrow(ProviderFailure::class);
    expect($factory->results)->toHaveCount(1)
        ->and($factory->results[0]->traceId)->toBe('iterator')
        ->and($factory->results[0]->errors->first()->context['traceId'])->toBe('iterator')
        ->and($factory->messages[0])->toContain('пагинац')
        ->and($transport->getRecorded())->toBe([]);
});

it('разделяет trace, audit и безопасный debug конкурентных страниц', function (bool $async, bool $debug): void {
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $transport->fake(['*' => function (PageRequest $request): MockResponse {
        $page = $request->getContext()->paginationOptions->getPage();
        AsyncTask::current()->runtime->sleep($page === 2 ? 20 : 1);
        return MockResponse::success(['data' => [$page], 'meta' => ['page' => $page, 'per_page' => 1, 'total' => 3]]);
    }]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://pagination.test', logger: $logger, debug: $debug, logLevel: 'debug',
        redaction: new RedactionPolicy(headers: ['X-Private']),
    ), $transport);
    $request = new PageRequest()->setClient($client)->withTraceId('pages')
        ->withHeader('Authorization', 'Bearer synthetic-secret')->withHeader('X-Private', 'custom-secret')
        ->rules(PaginationRule::all(concurrency: 2));
    $result = ($async ? $client->sendAsync($request)->wait() : $client->send($request))->raw();
    expect($result->data)->toBe([1, 2, 3])->and($result->debug)->toBeNull()->and($result->requestDebug())->toBeNull();
    foreach ([$result, ...$result->nested] as $execution) {
        expect($execution->traceId)->toBe('pages');
        $stages = array_column($execution->audit, 'stage');
        expect(array_filter($stages, fn ($stage) => $stage === PipelineStage::Started))->toHaveCount(1)
            ->and(array_filter($stages, fn ($stage) => $stage === PipelineStage::Completed))->toHaveCount(1);
        foreach ($execution->audit as $event) {
            expect($event->trace)->toBe($execution->trace);
        }
    }
    foreach ($result->nested as $index => $page) {
        expect($page->trace->parentExecutionId)->toBe($result->trace->executionId);
        if ($debug) {
            expect($page->debug->response)->toBe($page->response)->and($page->debug->duration)->toBeGreaterThan(0)
                ->and($page->requestDebug()['url'])->toContain('page=' . ($index + 1))
                ->and($page->requestDebug()['headers'])->toMatchArray(['Authorization' => '***', 'X-Private' => '***']);
        } else {
            expect($page->debug)->toBeNull()->and($page->requestDebug())->toBeNull()->and($page->response)->not->toBeNull();
        }
    }
    $completions = array_values(array_filter(array_column($logger->records, 'context'), fn (array $c): bool => $c['event'] === 'completed'));
    expect(array_column($completions, 'executionId'))->toBe([
        $result->nested[0]->trace->executionId, $result->nested[2]->trace->executionId,
        $result->nested[1]->trace->executionId, $result->trace->executionId,
    ])->and(json_encode($logger->records))->not->toContain('synthetic-secret')->not->toContain('custom-secret');
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe([]);
})->with([false, true])->with([false, true]);
