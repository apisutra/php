<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\CooldownConfig;
use ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use ApiSutra\Exceptions\Request\CooldownException;
use ApiSutra\Execution\Async\AsyncRuntime;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Pipeline\Transport\CooldownCoordinator;
use ApiSutra\Pipeline\Transport\ResolvedCooldown;
use ApiSutra\RateLimiting\Cooldown\Backends\LocalCooldownBackend;
use ApiSutra\Pipeline\Transport\CooldownStore;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Timing\CooperativeSleeper;
use ApiSutra\Timing\ExecutionBudget;
use ApiSutra\Timing\SystemClock;
use ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Promise\CancellationException;
use Revolt\EventLoop;

it('продлевает срок через max, удаляет истёкшие записи и не хранит успешные области', function (): void {
    $clock = new VirtualClock();
    $registry = new LocalCooldownBackend($clock);
    expect($registry->extend('a', 1000)->extended)->toBeTrue()->and($registry->extend('a', 500)->extended)->toBeFalse();
    $clock->advance(100);
    expect($registry->extend('a', 2000)->extended)->toBeTrue()->and($registry->remainingMs('a'))->toBe(2000);
    for ($i = 0; $i < 1000; $i++) {
        $registry->remainingMs('success-' . $i);
    }
    $state = new ReflectionProperty($registry, 'deadlines');
    expect($state->getValue($registry))->toHaveCount(1);
    $clock->advance(2000);
    expect($registry->remainingMs('absent'))->toBe(0)->and($state->getValue($registry))->toBe([]);
});

it('накапливает добавочное ожидание при продлении без обнуления предела', function (): void {
    $clock = new VirtualClock();
    $registry = new LocalCooldownBackend($clock);
    $registry->extend('a', 600);
    $sleeper = new class ($clock, $registry) implements SleeperInterface {
        public function __construct(private VirtualClock $clock, private LocalCooldownBackend $registry)
        {
        }
        public function sleepMs(int $milliseconds): void
        {
            $this->clock->sleepMs($milliseconds);
            $this->registry->extend('a', 500);
        }
    };
    $context = new PipelineContext(new RetryPolicyRequest(), new ClientConfig(baseUrl: 'https://fixture.test'), 'fixture');
    $context->budget = new ExecutionBudget($clock);
    $coordinator = new CooldownCoordinator(new CooldownStore($registry, new AuditLogger()), $sleeper, new AuditLogger());
    $spent = 0;
    try {
        $coordinator->wait(new ResolvedCooldown(new CooldownConfig(), 'a'), $context, 0, $spent);
        test()->fail('Expected local denial');
    } catch (CooldownException $exception) {
        expect($spent)->toBe(600)->and($clock->waits)->toBe([600])->and($exception->retryAfterMs)->toBe(500)
            ->and($registry->remainingMs('a'))->toBe(500);
    }
});

it('async замечает продление и освобождает таймер при отмене, сохраняя общий срок', function (bool $cancel): void {
    $clock = new SystemClock();
    $registry = new LocalCooldownBackend($clock);
    $registry->extend('a', 40);
    $coordinator = new CooldownCoordinator(new CooldownStore($registry, new AuditLogger()), new CooperativeSleeper(), new AuditLogger());
    $context = new PipelineContext(new RetryPolicyRequest(), new ClientConfig(baseUrl: 'https://fixture.test'), 'fixture');
    $context->budget = new ExecutionBudget($clock, 1000);
    $runtime = new AsyncRuntime();
    $before = EventLoop::getIdentifiers();
    $promise = $runtime->start(static function () use ($coordinator, $context): string {
        $spent = 0;
        $coordinator->wait(new ResolvedCooldown(new CooldownConfig(), 'a'), $context, 0, $spent);
        return 'done';
    });
    if ($cancel) {
        $promise->cancel();
        expect(fn () => $promise->wait())->toThrow(CancellationException::class);
        expect($registry->remainingMs('a'))->toBeGreaterThan(0);
    } else {
        $registry->extend('a', 80);
        expect($promise->wait())->toBe('done')->and($registry->remainingMs('a'))->toBe(0);
    }
    $suspension = EventLoop::getSuspension();
    EventLoop::queue($suspension->resume(...));
    $suspension->suspend();
    expect(EventLoop::getIdentifiers())->toBe($before);
})->with([false, true]);


it('auto наследует конечный parent budget вместо запасного предела', function (): void {
    $clock = new VirtualClock();
    $registry = new LocalCooldownBackend($clock);
    $registry->extend('a', 30_000);
    $context = new PipelineContext(new RetryPolicyRequest(), new ClientConfig(baseUrl: 'https://fixture.test'), 'fixture');
    $context->budget = new ExecutionBudget($clock, parent: new ExecutionBudget($clock, 60_000));
    $spent = 0;
    (new CooldownCoordinator(new CooldownStore($registry, new AuditLogger()), $clock, new AuditLogger()))->wait(
        new ResolvedCooldown(new CooldownConfig(), 'a'), $context, 0, $spent
    );
    expect($clock->waits)->toBe([30_000])->and($context->budget->remainingMs())->toBe(30_000);
});

it('ожидание одной async группы не препятствует HTTP другой', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::sequence([MockResponse::rateLimited(1), MockResponse::success(), MockResponse::success()])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport);
    $client->send(new RetryPolicyRequest());
    $waiting = $client->sendAsync(new RetryPolicyRequest());
    $independent = $client->sendAsync((new RetryPolicyRequest())->withCooldown(new CooldownConfig(group: 'other')));
    expect($independent->wait()->raw()->isSuccess())->toBeTrue()->and($waiting->getState())->toBe('pending');
    expect($waiting->wait()->raw()->isSuccess())->toBeTrue()->and($transport->getRecorded())->toHaveCount(3);
});
