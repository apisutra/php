<?php

declare(strict_types=1);

use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Execution\Async\AsyncTask;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Auth\LockAwareAuthenticator;
use ApiSutra\Tests\Stubs\Pagination\PageRequest;
use ApiSutra\Tests\Stubs\Requests\RefreshTokenRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Tests\Support\ArrayCache;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use ApiSutra\Transport\MockTransport;
use Revolt\EventLoop;

it('страницы делят cooldown и квоту, retry не перезапускает обход', function (): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $sent = [];
    $transport->fake(['*' => function (PageRequest $request) use (&$sent, $clock): MockResponse {
        $page = $request->getContext()->paginationOptions->getPage();
        $sent[] = [$page, $clock->monotonicMs()];
        return count($sent) === 1 ? MockResponse::rateLimited(1)
            : MockResponse::success(['data' => [$page], 'meta' => ['page' => $page, 'per_page' => 1, 'total' => 3]]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pagination.test', rateLimit: new RateLimitConfig(1, 1), retry: new RetryConfig(attempts: 2, totalTimeoutMs: 5000, jitter: false)), $transport, $clock, $clock);
    $result = new PageRequest()->setClient($client)->paginate()->withConcurrency(3)->all();
    expect($result->items())->toBe([1, 2, 3])->and(array_column($sent, 0))->toBe([1, 1, 2, 3]);
    foreach (array_slice($sent, 1) as $index => $entry) {
        expect($entry[1] - $sent[$index][1])->toBeGreaterThanOrEqual(1000);
    }
});

it('cache hit страниц не расходует новую HTTP-квоту', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => function (PageRequest $request): MockResponse {
        $page = $request->getContext()->paginationOptions->getPage();
        return MockResponse::success(['data' => [$page], 'meta' => ['page' => $page, 'per_page' => 1, 'total' => 3]]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pagination.test', cacheConfig: new CacheConfig(store: new ArrayCache()), rateLimit: new RateLimitConfig(3, 60, behavior: RateLimitBehavior::Throw)), $transport);
    $paginator = new PageRequest()->setClient($client)->withLimit(1)->paginate()->withConcurrency(2);
    expect($paginator->all()->items())->toBe([1, 2, 3])->and($paginator->all()->items())->toBe([1, 2, 3]);
    expect($transport->getRecorded())->toHaveCount(3);
});

it('pool обходов координирует один auth refresh и сохраняет trace детей страниц', function (): void {
    $auth = new LockAwareAuthenticator();
    $transport = new MockTransport();
    $refreshes = 0;
    $active = $peak = 0;
    $transport->fake([
        RefreshTokenRequest::class => function () use (&$refreshes): MockResponse {
            $refreshes++;
            AsyncTask::current()->runtime->sleep(5);
            return MockResponse::success(['token' => 'fresh']);
        },
        '*' => function (PageRequest $request) use (&$active, &$peak): MockResponse {
            $context = $request->getContext();
            $peak = max($peak, ++$active);
            $page = $context->paginationOptions->getPage();
            AsyncTask::current()->runtime->sleep($page === 1 ? 60 : 120);
            --$active;
            expect($request->getContext())->toBe($context);
            $page = $context->paginationOptions->getPage();
            return MockResponse::success(['data' => [$page], 'meta' => ['page' => $page, 'per_page' => 1, 'total' => 3]]);
        },
    ]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pagination.test', auth: $auth), $transport);
    $request = new PageRequest()->rules(PaginationRule::all(concurrency: 2));
    $pool = $client->pool([$request, $request], 2)->send();
    expect($refreshes)->toBe(1)->and($peak)->toBe(4);
    foreach ($pool->nested as $result) {
        expect($result->data)->toBe([1, 2, 3]);
        foreach ($result->nested as $page) {
            expect($page->trace->parentExecutionId)->toBe($result->trace->executionId);
            foreach ($page->nested as $authResult) {
                expect($authResult->trace->parentExecutionId)->toBe($page->trace->executionId);
            }
        }
    }
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe([]);
});
