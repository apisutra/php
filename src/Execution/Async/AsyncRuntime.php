<?php

declare(strict_types=1);

namespace ApiSutra\Execution\Async;

use ApiSutra\Contracts\Interfaces\Execution\ResultPromiseInterface;
use Closure;
use ApiSutra\Execution\ExecutionLocal;
use Fiber;
use GuzzleHttp\Promise\CancellationException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Throwable;
use ApiSutra\Support\ContainerProviderRegistry;

/** @internal Запускает обычный стек исполнения; не содержит правил пайплайна. */
final readonly class AsyncRuntime
{
    public GuzzlePromiseBridge $promises;

    public function __construct(public SchedulerInterface $scheduler = new RevoltScheduler())
    {
        $this->promises = new GuzzlePromiseBridge($scheduler);
    }

    /**
     * @template T
     * @param Closure(): T $operation
     * @return ResultPromiseInterface<T>
     */
    public function start(Closure $operation): ResultPromiseInterface
    {
        $provider = ContainerProviderRegistry::resolve();
        $operation = ExecutionLocal::inherit($operation);
        $task = new AsyncTask($this);
        $promise = new Promise(null, $task->cancel(...));
        $result = $this->promises->wrap($promise, $task);
        $unsubscribe = AsyncTask::current()?->onCancel($task->cancel(...));
        $fiber = new Fiber(function () use ($operation, $task, $promise, $unsubscribe, $provider): void {
            try {
                $task->check();
                $value = ContainerProviderRegistry::withProvider($provider, $operation);
                if ($promise->getState() === PromiseInterface::PENDING) {
                    $task->cancelled ? $promise->reject(new CancellationException('SDK execution cancelled')) : $promise->resolve($value);
                }
            } catch (Throwable $error) {
                if ($promise->getState() === PromiseInterface::PENDING) {
                    $promise->reject($error);
                }
            } finally {
                $task->finish();
                $unsubscribe?->__invoke();
                if (!$task->abandoned) {
                    $this->promises->pump();
                }
            }
        });
        $task->enter($fiber);
        $fiber->start();
        return $result;
    }

    public function sleep(int $milliseconds): void
    {
        $task = AsyncTask::current();
        $task?->check();
        $suspension = $this->scheduler->suspension();
        $pending = new PendingAwait($suspension);
        $cancelTimer = $this->scheduler->delay($milliseconds, static fn () => $pending->resolve(null));
        $unsubscribe = $task?->onCancel(static function (bool $resume) use ($pending, $cancelTimer): void {
            $cancelTimer();
            $pending->cancel($resume);
        });
        try {
            $suspension->suspend();
        } finally {
            $pending->detach();
            $cancelTimer();
            $unsubscribe?->__invoke();
        }
    }
}
