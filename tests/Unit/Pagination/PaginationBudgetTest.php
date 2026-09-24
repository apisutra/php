<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Result\PaginatedResult;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Pagination\PageRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Timing\ExecutionDeadline;
use ApiSutra\Transport\MockTransport;

it('делит клиентский бюджет между страницами и сохраняет частичные данные на обоих входах', function (string $route, bool $partial, int $concurrency): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $timeouts = [];
    $parents = [];
    $transport->fake(['*' => function (PageRequest $request) use ($clock, $transport, &$timeouts, &$parents): MockResponse {
        $context = $request->getContext();
        $page = $context->paginationOptions->getPage();
        $parents[] = $context->parent;
        $recorded = $transport->getRecorded();
        $timeout = $recorded[array_key_last($recorded)]->transportOptions->effective()->timeoutMs;
        $timeouts[] = $timeout;
        $clock->advance(min(400, $timeout));
        return MockResponse::success(['data' => [$page], 'meta' => ['page' => $page, 'per_page' => 1, 'total' => 3]]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(totalTimeoutMs: 1000)), $transport, $clock, $clock);
    $request = new PageRequest()->setClient($client);
    $strategy = $partial ? FailStrategy::Partial : FailStrategy::FailAll;
    $execution = $request->rules(PaginationRule::all($strategy, $concurrency));
    $result = match ($route) {
        'builder' => $request->paginate()->withFailStrategy($strategy)->withConcurrency($concurrency)->all(),
        'sync' => $execution->send()->raw(),
        'async' => $execution->sendAsync()->wait()->raw(),
    };
    // В конкурентном mock общий clock может истечь до гидратации уже полученного второго ответа.
    expect($result)->toBeInstanceOf(PaginatedResult::class)
        ->and($result->isPartial())->toBeTrue()->and($result->items())->toBe($concurrency === 1 ? [1, 2] : [1])
        ->and($timeouts)->toBe([1000, 600, 200])->and($clock->milliseconds)->toBe(2000)
        ->and($result->errors)->toHaveCount($concurrency === 1 ? 1 : 2)
        ->and($result->errors->first()->context)->toMatchArray(['reason' => 'execution_deadline_exceeded', 'page' => $concurrency === 1 ? 3 : 2]);
    expect($parents[0])->not->toBeNull()->and($parents[1])->toBe($parents[0])->and($parents[2])->toBe($parents[0]);
    expect($result->nested)->toHaveCount(3)->and($parents[0]->nested)->toHaveCount(3);
    foreach ($result->nested as $page) {
        expect($page->trace->parentExecutionId)->toBe($result->trace->executionId);
    }
})->with(['builder', 'sync', 'async'])->with([false, true])->with([1, 3]);

it('сохраняет свежий бюджет iterator и ограничивает его только общим внешним сроком', function (bool $external): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $transport->fake(['*' => function (PageRequest $request) use ($clock): MockResponse {
        $context = $request->getContext();
        $clock->advance(min(400, $context->budget->remainingMs()));
        $page = $context->paginationOptions->getPage();
        return MockResponse::success(['data' => [$page], 'meta' => ['page' => $page, 'per_page' => 1, 'total' => 3]]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(totalTimeoutMs: 1000)), $transport, $clock, $clock);
    $request = new PageRequest()->setClient($client);
    $execution = $external ? $request->withDeadline(ExecutionDeadline::afterMs(1000, $clock)) : $request;
    $pages = iterator_to_array($execution->paginate());
    expect($pages)->toHaveCount(3)->and($pages[2]->isFailed())->toBe($external)
        ->and($clock->milliseconds)->toBe($external ? 2000 : 2200);
})->with([false, true]);
