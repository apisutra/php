<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Exceptions\Request\CooldownException;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Stubs\Retry\ProbeTransportDecorator;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Transport\MockTransport;
use Revolt\EventLoop;

it('сохраняет локальный подтип через RetrySender и локализацию даже при Throwable retry', function (bool $previous): void {
    $clock = new VirtualClock();
    $inner = new MockTransport();
    $inner->fake(['*' => MockResponse::serverError()]);
    $transport = new ProbeTransportDecorator($inner);
    $sleeper = new class implements SleeperInterface {
        public function sleepMs(int $milliseconds): void
        {
            throw new CooldownException(1501);
        }
    };
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test',
        delay: $previous ? 0 : 1,
        retry: new RetryConfig(attempts: 3, baseDelay: 1, maxDelay: 1, jitter: false, retryExceptions: [Throwable::class]),
        localization: 'ru',
    ), $transport, sleeper: $sleeper, clock: $clock);
    $handle = $client->send(new RetryPolicyRequest());
    $result = $handle->raw();
    expect($result->exception)->toBeInstanceOf(CooldownException::class)
        ->and($result->exception->retryAfterMs)->toBe(1501)
        ->and($result->exception->retryAfter)->toBe(2)
        ->and($result->exception->response)->toBeNull()
        ->and($result->exception->lastResponse?->status)->toBe($previous ? 500 : null)
        ->and($result->errors->first()->code)->toBe(ErrorCode::RateLimited)
        ->and($result->errors->first()->context['reason'])->toBe('server_cooldown_active')
        ->and($transport->entries)->toBe($previous ? ['sync'] : []);
    expect(fn () => $handle->dataOrFail())->toThrow(CooldownException::class)
        ->and($result->exception->getMessage())->toContain('Действует запрет');
})->with([false, true]);

it('входит в sync и async декоратор без уступки циклу после последней проверки', function (bool $async): void {
    $inner = new MockTransport();
    $inner->fake(['*' => MockResponse::success()]);
    $interleaved = false;
    $transport = new ProbeTransportDecorator(
        $inner,
        onSend: static function () use (&$interleaved): void {
            expect($interleaved)->toBeFalse();
        },
        onCheck: static function () use (&$interleaved): void {
            $interleaved = false;
            EventLoop::queue(static function () use (&$interleaved): void {
                $interleaved = true;
            });
        },
    );
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport);
    $result = $async ? $client->sendAsync(new RetryPolicyRequest())->wait() : $client->send(new RetryPolicyRequest());
    expect($result->raw()->isSuccess())->toBeTrue()->and($transport->entries)->toBe([$async ? 'async' : 'sync']);
    $suspension = EventLoop::getSuspension();
    EventLoop::queue($suspension->resume(...));
    $suspension->suspend();
})->with([false, true]);

it('не вызывает транспортный декоратор при активном запрете в обоих путях', function (bool $async): void {
    $clock = new VirtualClock();
    $inner = new MockTransport();
    $inner->fake(['*' => MockResponse::rateLimited(30)]);
    $transport = new ProbeTransportDecorator($inner);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport, $clock, $clock);
    $first = $async ? $client->sendAsync(new RetryPolicyRequest())->wait() : $client->send(new RetryPolicyRequest());
    $next = (new RetryPolicyRequest())->withoutRetry()->withoutRateLimit();
    $second = $async ? $client->sendAsync($next)->wait() : $client->send($next);
    expect($first->raw()->response?->status)->toBe(429)
        ->and($second->raw()->exception)->toBeInstanceOf(CooldownException::class)
        ->and($second->raw()->response)->toBeNull()
        ->and($transport->entries)->toBe([$async ? 'async' : 'sync'])
        ->and($clock->waits)->toBe([]);
})->with([false, true]);
