<?php

declare(strict_types=1);

namespace ApiSutra\Execution;

use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Execution\Async\AsyncRuntime;
use ApiSutra\Execution\Async\AsyncTask;
use ApiSutra\Execution\Async\GuzzlePromiseBridge;
use ApiSutra\Result\ExecutionResult;
use Closure;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Generator;
use SplQueue;
use Throwable;
use ApiSutra\Exceptions\ControlFlow\AdmissionRefused;
use ApiSutra\Execution\Admission\AdmissionScope;
use WeakReference;

/** @internal Один экземпляр на запуск; при сбое завершает выданное, callbacks вне очереди Promise. */
final class ConcurrentExecution
{
    /** @var array<int, array{request: RequestInterface, promise: PromiseInterface}> */
    private array $active = [];
    /** @var SplQueue<array{int, mixed, bool}> */
    private SplQueue $ready;
    private readonly GuzzlePromiseBridge $bridge;
    private bool $advance = false;
    private bool $exhausted = false;
    private bool $stoppedOnFailure = false;
    private int $next = 0;
    private ?Throwable $failure = null;
    /** @var 'source'|'executor'|'handler'|null */
    private ?string $failureStage = null;

    private function __construct(private readonly AsyncTask $task)
    {
        $this->bridge = new GuzzlePromiseBridge();
        $this->ready = new SplQueue();
    }

    /**
     * @param list<RequestInterface> $requests
     * @param callable(RequestInterface): PromiseInterface $dispatch
     * @param null|callable(ExecutionResult, RequestInterface): void $onResult
     * @return list<ExecutionResult>
     */
    public static function collect(
        array $requests,
        int $concurrency,
        callable $dispatch,
        bool $stopOnFailure,
        ?callable $onResult = null,
    ): array {
        $results = [];
        $outcome = self::run(
            $requests,
            $concurrency,
            $dispatch,
            $stopOnFailure,
            static function (ExecutionResult $result, RequestInterface $request, int $index) use (&$results): void {
                $results[$index] = $result;
            },
            $onResult,
        );
        if ($outcome->failure !== null) {
            throw $outcome->failure;
        }
        ksort($results);
        return array_values($results);
    }

    /**
     * @param iterable<RequestInterface> $requests
     * @param callable(RequestInterface): PromiseInterface $dispatch
     * @param callable(ExecutionResult, RequestInterface, int): void $record
     * @param null|callable(ExecutionResult, RequestInterface): void $onResult
     */
    public static function run(
        iterable $requests,
        int $concurrency,
        callable $dispatch,
        bool $stopOnFailure,
        callable $record,
        ?callable $onResult = null,
    ): ExecutionOutcome {
        $task = AsyncTask::current();
        if ($task === null) {
            $runtime = new AsyncRuntime();
            return $runtime->promises->await($runtime->start(static fn (): ExecutionOutcome => self::run(
                $requests,
                $concurrency,
                $dispatch,
                $stopOnFailure,
                $record,
                $onResult,
            )));
        }
        return (new self($task))->execute(
            $requests,
            max(1, $concurrency),
            $dispatch,
            $stopOnFailure,
            $record,
            $onResult,
        );
    }

    /**
     * @param iterable<RequestInterface> $requests
     * @param callable(RequestInterface): PromiseInterface $dispatch
     * @param callable(ExecutionResult, RequestInterface, int): void $record
     * @param null|callable(ExecutionResult, RequestInterface): void $onResult
     */
    private function execute(
        iterable $requests,
        int $concurrency,
        callable $dispatch,
        bool $stopOnFailure,
        callable $record,
        ?callable $onResult,
    ): ExecutionOutcome {
        $iterator = self::iterate($requests);
        $signal = new Promise();
        $enqueue = self::createEnqueue($this->ready, $signal, $this->bridge);
        try {
            while (true) {
                $this->task->check();
                $this->fillWindow($iterator, $concurrency, $dispatch, $enqueue);
                if ($this->active === []) {
                    break;
                }
                if ($this->ready->isEmpty()) {
                    $this->bridge->await($signal);
                }
                $signal = new Promise();
                $this->drainReady($stopOnFailure, $record, $onResult);
            }
            return new ExecutionOutcome($this->exhausted, $this->stoppedOnFailure, $this->failure, $this->failureStage);
        } finally {
            $this->release();
        }
    }

    /**
     * @param Generator<int, RequestInterface> $iterator
     * @param callable(RequestInterface): PromiseInterface $dispatch
     * @param Closure(int, mixed, bool): void $enqueue
     */
    private function fillWindow(Generator $iterator, int $concurrency, callable $dispatch, Closure $enqueue): void
    {
        while (!$this->hasStopped() && !$this->exhausted && count($this->active) < $concurrency) {
            $stage = 'source';
            try {
                // next вызывается только после освобождения места, не сразу после dispatch.
                $this->advance ? $iterator->next() : $iterator->rewind();
                $this->advance = true;
                if (!$iterator->valid()) {
                    $this->exhausted = true;
                    break;
                }
                $request = $iterator->current();
                $this->task->check();
                $stage = 'executor';
                $index = $this->next++;
                $promise = $dispatch($request);
                $this->active[$index] = ['request' => $request, 'promise' => $promise];
                $promise->then(
                    static fn (mixed $value) => $enqueue($index, $value, true),
                    static fn (mixed $reason) => $enqueue($index, $reason, false),
                );
                $this->bridge->pump();
            } catch (Throwable $exception) {
                $this->fail($exception, $stage);
            } finally {
                unset($request, $promise);
            }
        }
    }

    /**
     * @param callable(ExecutionResult, RequestInterface, int): void $record
     * @param null|callable(ExecutionResult, RequestInterface): void $onResult
     */
    private function drainReady(bool $stopOnFailure, callable $record, ?callable $onResult): void
    {
        while (!$this->ready->isEmpty()) {
            $this->task->check();
            [$index, $value, $success] = $this->ready->dequeue();
            $request = $this->active[$index]['request'];
            unset($this->active[$index]);
            $stage = 'executor';
            try {
                if (!$success) {
                    throw Create::exceptionFor($value);
                }
                $result = self::result($value);
                $record($result, $request, $index);
                $stage = 'handler';
                if (($this->failure === null || $this->failure instanceof AdmissionRefused) && $onResult !== null) {
                    $onResult($result, $request);
                }
                $this->stoppedOnFailure = $this->stoppedOnFailure || ($stopOnFailure && $result->isFailed());
            } catch (Throwable $exception) {
                $this->fail($exception, $stage);
            } finally {
                unset($request, $result, $value);
            }
        }
    }

    /**
     * @param SplQueue<array{int, mixed, bool}> $ready
     * @return Closure(int, mixed, bool): void
     */
    private static function createEnqueue(SplQueue $ready, Promise &$signal, GuzzlePromiseBridge $bridge): Closure
    {
        $reference = WeakReference::create($ready);
        return static function (int $index, mixed $value, bool $success) use ($reference, &$signal, $bridge): void {
            $queue = $reference->get();
            if ($queue === null) {
                return;
            }
            $queue->enqueue([$index, $value, $success]);
            if ($signal->getState() === PromiseInterface::PENDING) {
                $signal->resolve(null);
            }
            $bridge->pump();
        };
    }

    /** @param 'source'|'executor'|'handler' $stage */
    private function fail(Throwable $exception, string $stage): void
    {
        $this->task->check();
        if ($this->failure === null || ($this->failure instanceof AdmissionRefused && !$exception instanceof AdmissionRefused)) {
            $this->failure = $exception;
            $this->failureStage = $stage;
        }
    }

    private function hasStopped(): bool
    {
        return $this->failure !== null || $this->stoppedOnFailure || AdmissionScope::hasRefusal();
    }

    private function release(): void
    {
        // Позднее завершение чужого Promise не удерживает отброшенное окно.
        unset($this->ready);
        try {
            foreach ($this->active as $entry) {
                $entry['promise']->cancel();
            }
        } finally {
            // Исключение может сохранить этот экземпляр в trace; тяжёлое состояние ему не оставляем.
            $this->active = [];
            $this->failure = null;
        }
    }

    /** @param iterable<RequestInterface> $requests
     * @return Generator<int, RequestInterface>
     */
    private static function iterate(iterable $requests): Generator
    {
        foreach ($requests as $request) {
            yield $request;
        }
    }

    private static function result(mixed $value): ExecutionResult
    {
        return $value;
    }
}
