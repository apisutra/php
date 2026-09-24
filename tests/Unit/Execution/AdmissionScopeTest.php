<?php

declare(strict_types=1);

use ApiSutra\Exceptions\ControlFlow\AdmissionRefused;
use ApiSutra\Exceptions\Request\RateLimitException;
use ApiSutra\Execution\Admission\AdmissionScope;
use ApiSutra\Execution\Async\AsyncRuntime;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Timing\CooperativeSleeper;

it('объединяет сроки и вычитает дренирование, восстанавливает вложенную область', function (): void {
    $clock = new VirtualClock();
    $outer = new AdmissionScope($clock);
    $inner = new AdmissionScope($clock);
    $outer->run(static function () use ($clock, $inner): void {
        try { AdmissionScope::refuse('local_rate_limit_exceeded', 5000, new RateLimitException('test', null)); } catch (AdmissionRefused) { }
        $clock->advance(1000);
        try { $inner->run(static fn () => AdmissionScope::refuse('local_rate_limit_exceeded', 10000, new RateLimitException('test', null))); } catch (AdmissionRefused $signal) {
            expect($signal->scope)->toBe($inner);
        }
        try { AdmissionScope::refuse('local_rate_limit_exceeded', 1000, new RateLimitException('test', null)); } catch (AdmissionRefused) { }
    });
    expect($outer->remainingMs())->toBe(4000)->and($inner->remainingMs())->toBe(10000)->and(AdmissionScope::hasRefusal())->toBeFalse();
});

it('передаёт область дочерним SDK-задачам, изолируя соседние Fiber', function (): void {
    $runtime = new AsyncRuntime();
    $area = new AdmissionScope();
    $first = $runtime->start(static fn () => $area->run(static function (): void {
        (new CooperativeSleeper())->sleepMs(2);
        (new AsyncRuntime())->start(static fn () => AdmissionScope::refuse('local_rate_limit_exceeded', 10, new RateLimitException('test', null)))->wait();
    }));
    $second = $runtime->start(static function (): bool {
        (new CooperativeSleeper())->sleepMs(1);
        AdmissionScope::refuse('local_rate_limit_exceeded', 10, new RateLimitException('test', null));
        return true;
    });
    expect(fn () => $first->wait())->toThrow(AdmissionRefused::class)->and($second->wait())->toBeTrue();
});
