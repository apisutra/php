<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Execution\Async\AsyncTask;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Pagination\PageRequest;
use ApiSutra\Tests\Stubs\RateLimit\ScriptedCooldownBackend;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use GuzzleHttp\Promise\CancellationException;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Revolt\EventLoop;

it('последний read не вызывает внешний logger до HTTP даже если logger приостанавливается', function (): void {
    $events = [];
    $backend = new ScriptedCooldownBackend(static function () use (&$events): int { $events[] = 'read'; return 0; });
    $logger = new class ($events) extends AbstractLogger {
        public function __construct(public array &$events) {}
        public function log($level, string|Stringable $message, array $context = []): void
        {
            if (($context['event'] ?? null) !== 'rate_limit.cooldown_storage') { return; }
            $this->events[] = 'logger';
            AsyncTask::current()?->runtime->sleep(1);
        }
    };
    $transport = new MockTransport();
    $transport->fake(['*' => static function () use (&$events): MockResponse { $events[] = 'http'; return MockResponse::success(); }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', cooldownBackend: $backend, logger: $logger, logLevel: LogLevel::DEBUG), $transport);
    expect($client->sendAsync(new RetryPolicyRequest())->wait()->raw()->isSuccess())->toBeTrue()
        ->and($events)->toBe(['read', 'logger', 'read', 'http', 'logger']);
});

it('ограничивает число чтений фактическими попытками pool и страниц, сохраняя trace и очистку', function (string $mode, bool $slow): void {
    $ioMs = 0.0;
    $backend = new ScriptedCooldownBackend(static function () use ($slow, &$ioMs): int {
        $start = hrtime(true);
        if ($slow) { usleep(1000); }
        $ioMs += (hrtime(true) - $start) / 1_000_000;
        return 0;
    });
    $active = $peak = 0;
    $contexts = [];
    $transport = new MockTransport();
    $transport->fake(['*' => static function ($request) use (&$active, &$peak, &$contexts): MockResponse {
        $contexts[] = $request->getContext();
        $peak = max($peak, ++$active);
        try {
            AsyncTask::current()->runtime->sleep(10);
            $page = $request->getContext()->paginationOptions?->getPage() ?? 1;
            return MockResponse::success(['data' => [$page], 'meta' => ['page' => $page, 'per_page' => 1, 'total' => 6]]);
        } finally { --$active; }
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', cooldownBackend: $backend), $transport);
    $started = hrtime(true);
    if ($mode === 'pages') {
        $result = new PageRequest()->setClient($client)->paginate()->withConcurrency(5)->all();
        expect($result->items())->toBe([1, 2, 3, 4, 5, 6]);
        $attempts = 6;
    } else {
        $requests = array_map(static fn () => $mode === 'nested'
            ? (new PageRequest())->rules(PaginationRule::all(concurrency: 5)) : new RetryPolicyRequest(), range(1, 5));
        $pool = $client->pool($requests, 5);
        $summary = $mode === 'consume' ? $pool->consume() : $pool->send()->results()->summarize();
        expect($summary->successful)->toBe(5);
        $attempts = $mode === 'nested' ? 30 : 5;
    }
    $elapsed = (hrtime(true) - $started) / 1_000_000;
    expect($backend->calls)->toHaveCount(2 * $attempts)->and($transport->getRecorded())->toHaveCount($attempts)
        ->and($peak)->toBeGreaterThan(1)->toBeLessThanOrEqual($mode === 'nested' ? 25 : 5)
        ->and($active)->toBe(0)->and($elapsed)->toBeGreaterThanOrEqual($ioMs);
    $ids = array_map(static fn ($c) => $c->trace->executionId, $contexts);
    expect(array_unique($ids))->toHaveCount($attempts);
    foreach ($contexts as $context) {
        expect($context->cooldownDiagnostics->reads)->toBe(2)->and($context->cooldownDiagnostics->publications)->toBe(0);
        if (in_array($mode, ['pages', 'nested'], true)) {
            expect($context->trace->parentExecutionId)->not->toBeNull()->and($context->budget->clock)->toBe($context->parent->budget->clock);
        }
    }
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe([]);
})->with(['pool', 'consume', 'pages', 'nested'])->with([false, true]);

it('отмена ожидания общего backend снимает задачи без публикации или HTTP', function (): void {
    $backend = new ScriptedCooldownBackend(static fn (): int => 500);
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', cooldownBackend: $backend,
        retry: new RetryConfig(attempts: 1, totalTimeoutMs: 1000)), $transport);
    $promise = $client->sendAsync(new RetryPolicyRequest());
    // Дать задаче дойти до кооперативного ожидания, затем отменить его.
    $suspension = EventLoop::getSuspension();
    EventLoop::delay(0.01, $suspension->resume(...));
    $suspension->suspend();
    $promise->cancel();
    expect(fn () => $promise->wait())->toThrow(CancellationException::class)
        ->and($transport->getRecorded())->toBe([])->and($backend->calls)->toHaveCount(1);
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe([]);
});
