<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Execution\Async\AsyncRuntime;
use ApiSutra\Execution\Async\AsyncTask;
use ApiSutra\Execution\Async\GuzzlePromiseBridge;
use ApiSutra\Execution\Async\SchedulerInterface;
use ApiSutra\Execution\Async\Suspension;
use GuzzleHttp\Promise\Promise;
use ApiSutra\Serialization\Traversal\TraversalFrames;
use ApiSutra\Tests\Stubs\Async\ContextRequest;
use ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Promise\CancellationException;
use GuzzleHttp\Promise\Each;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Revolt\EventLoop;

it('сохраняет общий результат для нескольких ожидающих и цепочек Guzzle', function (): void {
    $runtime = new AsyncRuntime();
    $calls = 0;
    $source = $runtime->start(function () use ($runtime, &$calls): int {
        $calls++;
        $runtime->sleep(10);
        return 42;
    });
    $a = $runtime->start(fn () => $source->wait());
    $b = $runtime->start(fn () => $source->wait());
    expect(Utils::all([$a, $b])->wait())->toBe([42, 42])->and($calls)->toBe(1);
    expect($source->then(fn (int $v): int => $v + 1)->wait())->toBe(43);
    $values = [];
    Each::ofLimit([$source, $a, $b], 2, function (int $v) use (&$values): void {
        $values[] = $v;
    })->wait();
    expect($values)->toBe([42, 42, 42])->and(AsyncTask::current())->toBeNull();
});

it('доставляет один отказ всем ожидающим и поддерживает otherwise', function (): void {
    $runtime = new AsyncRuntime();
    $source = $runtime->start(function () use ($runtime): never {
        $runtime->sleep(5);
        throw new RuntimeException('expected');
    });
    $wait = fn () => $runtime->start(function () use ($source): string {
        try {
            $source->wait();
        } catch (RuntimeException $error) {
            return $error->getMessage();
        }
        return 'missing';
    });
    expect(Utils::all([$wait(), $wait()])->wait())->toBe(['expected', 'expected']);
    expect($source->otherwise(fn (): string => 'recovered')->wait())->toBe('recovered');
});

it('работает внутри уже запущенного Revolt и не заменяет обработчик ошибок приложения', function (): void {
    $runtime = new AsyncRuntime();
    $values = [];
    $uncaught = [];
    $previous = EventLoop::getErrorHandler();
    EventLoop::setErrorHandler(function (Throwable $e) use (&$uncaught): void {
        $uncaught[] = $e;
    });
    try {
        EventLoop::queue(function () use ($runtime, &$values): void {
            $a = $runtime->start(function () use ($runtime): string {
                $runtime->sleep(5);
                return 'a';
            });
            $b = $runtime->start(fn (): string => 'b');
            $values = Utils::all([$a, $b])->wait();
        });
        EventLoop::run();
        expect($values)->toBe(['a', 'b'])->and($uncaught)->toBe([]);
    } finally {
        EventLoop::setErrorHandler($previous);
    }
});

it('отменяет таймер, освобождает задачу и не останавливает соседа', function (): void {
    $runtime = new AsyncRuntime();
    $before = EventLoop::getIdentifiers();
    $cleaned = false;
    $later = false;
    $a = $runtime->start(function () use ($runtime, &$cleaned, &$later): void {
        try {
            $runtime->sleep(60000);
            $later = true;
        } finally {
            $cleaned = true;
        }
    });
    $a->cancel();
    expect(fn () => $a->wait())->toThrow(CancellationException::class);
    expect($runtime->start(fn (): string => 'next')->wait())->toBe('next');
    expect($cleaned)->toBeTrue()->and($later)->toBeFalse();
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe($before);
});

it('сохраняет вложенность и контексты одного request при чередовании Fiber', function (): void {
    $runtime = new AsyncRuntime();
    $request = new ContextRequest();
    $config = new ClientConfig('https://example.test');
    $a = new PipelineContext($request, $config, 'a');
    $b = new PipelineContext($request, $config, 'b', RequestRole::Nested);
    $run = function (PipelineContext $context) use ($runtime, $request, $config): string {
        $leave = $request->enterExecutionContext($context);
        try {
            $helper = $request->helper();
            $runtime->sleep(5);
            expect($request->getContext())->toBe($context)->and($request->protectedContext())->toBe($context);
            expect($request->helper())->toBe($helper)->and($request->isRoot())->toBe($context->role === RequestRole::Root);
            $nested = new PipelineContext($request, $config, 'nested');
            $nestedLeave = $request->enterExecutionContext($nested);
            try {
                $runtime->sleep(1);
                expect($request->protectedContext())->toBe($nested);
            } finally {
                $nestedLeave();
            }
            expect($request->getContext())->toBe($context)->and($request->helper())->toBe($helper);
            return $context->traceId;
        } finally {
            $leave();
        }
    };
    expect(Utils::all([$runtime->start(fn () => $run($a)), $runtime->start(fn () => $run($b))])->wait())->toBe(['a', 'b']);
    expect($request->getContext())->toBeIn([$a, $b]);
    expect($a->requestPaginationHelper)->not->toBe($b->requestPaginationHelper);
});

it('разделяет ветки сериализации и сохраняет ветку при повторном входе в ту же Fiber', function (): void {
    $runtime = new AsyncRuntime();
    $frames = new TraversalFrames();
    $operation = function () use ($runtime, $frames): void {
        $frame = $frames->current();
        expect($frame->ancestors)->toBe([]);
        $frame->ancestors[123] = true;
        $runtime->sleep(5);
        expect($frames->current())->toBe($frame)->and($frame->ancestors)->toBe([123 => true]);
        unset($frame->ancestors[123]);
    };
    Utils::all([$runtime->start($operation), $runtime->start($operation)])->wait();
    expect($frames->current()->ancestors)->toBe([]);
});

it('освобождает отброшенные ожидания без запуска loop из деструктора', function (): void {
    $runtime = new AsyncRuntime();
    $before = EventLoop::getIdentifiers();
    $owner = new stdClass();
    $weak = WeakReference::create($owner);
    $promise = $runtime->start(function () use ($runtime, $owner): void {
        $runtime->sleep(60000);
        throw new RuntimeException('Must never run');
    });
    unset($promise, $owner);
    gc_collect_cycles();
    expect($weak->get())->toBeNull()->and(EventLoop::getIdentifiers())->toBe($before);
});

it('ожидание источника не обязано завершить продолжения внешних Fiber и соседних SDK задач', function (bool $external): void {
    $runtime = new AsyncRuntime();
    $source = $runtime->start(function () use ($runtime): int {
        $runtime->sleep(2);
        return 42;
    });
    $values = [];
    $wait = function (string $name) use ($source, &$values): void {
        $values[$name] = $source->wait();
    };
    if ($external) {
        $a = new Fiber(fn () => $wait('a'));
        $b = new Fiber(fn () => $wait('b'));
        $a->start();
        $b->start();
    } else {
        $a = $runtime->start(fn () => $wait('a'));
        $b = $runtime->start(fn () => $wait('b'));
    }
    expect($source->wait())->toBe(42);
    // Основное ожидание завершается до запланированных продолжений соседей.
    expect($values)->toBe([]);
    if ($external) {
        EventLoop::run();
        expect($a->isTerminated())->toBeTrue()->and($b->isTerminated())->toBeTrue();
    } else {
        Utils::all([$a, $b])->wait();
    }
    expect($values)->toBe(['a' => 42, 'b' => 42]);
})->with([false, true]);

it('не теряет продвижение Promise queue при другом экземпляре планировщика', function (): void {
    // Только хранилище очереди callbacks: ни таймеров, ни реализации второго loop.
    $first = new class implements SchedulerInterface {
        /** @var list<Closure(): void> */
        public array $callbacks = [];

        public function queue(Closure $callback): void
        {
            $this->callbacks[] = $callback;
        }

        public function delay(int $milliseconds, Closure $callback): Closure
        {
            throw new LogicException('Таймер не используется в этой проверке');
        }

        public function suspension(): Suspension
        {
            throw new LogicException('Ожидание не используется в этой проверке');
        }
    };
    $second = clone $first;
    $a = new GuzzlePromiseBridge($first);
    $b = new GuzzlePromiseBridge($second);
    $delivered = false;
    $promise = new Promise();
    $promise->then(function () use (&$delivered): void {
        $delivered = true;
    });
    $promise->resolve(null);
    $a->pump();
    $a->pump();
    $b->pump();
    expect($first->callbacks)->toHaveCount(1);
    foreach ($second->callbacks as $callback) {
        $callback();
    }
    $deliveredBySecond = $delivered;
    foreach ($first->callbacks as $callback) {
        $callback();
    }
    expect($deliveredBySecond)->toBeTrue();
});
