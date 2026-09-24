<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Support\CooldownScenario;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Timing\ExecutionDeadline;

it('pool и consume ждут при budget и возвращают локальные отказы без него', function (bool $budget, int $concurrency, bool $consume): void {
    $s = new CooldownScenario(new ClientConfig(
        baseUrl: 'https://fixture.test',
        retry: new RetryConfig(attempts: 1, totalTimeoutMs: $budget ? 60_000 : null)
    ));
    $s->client->send(new RetryPolicyRequest());
    $s->transport->fake(['*' => MockResponse::success()]);
    $requests = (static function (): Generator {
        for ($i = 0; $i < 5; $i++) {
            yield new RetryPolicyRequest();
        }
    })();
    $pool = $s->client->pool($requests, $concurrency);
    $summary = $consume ? $pool->consume() : $pool->send()->results()->summarize();
    expect($summary->total)->toBe(5)->and($summary->successful)->toBe($budget ? 5 : 0)
        ->and($s->clock->waits)->toBe($budget ? [30_000] : [])
        ->and($s->transport->getRecorded())->toHaveCount($budget ? 6 : 1);
})->with([[false, 1, true], [true, 1, true], [false, 3, true], [true, 3, true], [false, 1, false], [true, 3, false]]);

it('stopOnFailure останавливает новые старты, а не планирует повтор', function (): void {
    $s = new CooldownScenario();
    $s->client->send(new RetryPolicyRequest());
    $summary = $s->client->pool([new RetryPolicyRequest(), new RetryPolicyRequest()], 1)->withStopOnFailure()->consume();
    expect($summary->total)->toBe(1)->and($summary->failed)->toBe(1)->and($s->transport->getRecorded())->toHaveCount(1);
});

it('общий внешний deadline уменьшает остаток для следующих элементов вместо нового totalTimeoutMs', function (): void {
    $s = new CooldownScenario(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(attempts: 1, totalTimeoutMs: 2000)));
    $deadline = ExecutionDeadline::afterMs(1000, $s->clock);
    $budgets = [];
    $s->transport->fake(['*' => static function (RetryPolicyRequest $request) use ($s, &$budgets): MockResponse {
        $budgets[] = $request->getContext()->budget->remainingMs();
        $s->clock->advance(600);
        return MockResponse::success();
    }]);
    $requests = [(new RetryPolicyRequest())->withDeadline($deadline), (new RetryPolicyRequest())->withDeadline($deadline), (new RetryPolicyRequest())->withDeadline($deadline)];
    $summary = $s->client->pool($requests, 1)->consume();
    expect($budgets)->toBe([1000, 400])->and($summary->successful)->toBe(1)->and($summary->failed)->toBe(2);
});
