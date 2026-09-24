<?php

declare(strict_types=1);

namespace ApiSutra\Execution\Async;

use ApiSutra\Exceptions\ControlFlow\ExecutionCancelledException;
use Closure;
use Fiber;
use WeakMap;
use WeakReference;
use Throwable;

/** @internal Отмена и lifetime одной задачи; реестр не удерживает Fiber. */
final class AsyncTask
{
    /** @var WeakMap<Fiber, WeakReference<self>>|null */
    private static ?WeakMap $current = null;
    /** @var array<int, Closure(bool): void> */
    private array $cancellations = [];
    /** @var list<Closure(): void> */
    private array $finalizers = [];
    private int $nextId = 0;
    private int $consumers = 0;
    public private(set) bool $cancelled = false;
    public private(set) bool $finished = false;
    public private(set) bool $abandoned = false;

    public function __construct(public readonly AsyncRuntime $runtime)
    {
    }

    public static function current(): ?self
    {
        $fiber = Fiber::getCurrent();
        return $fiber === null ? null : (self::$current[$fiber] ?? null)?->get();
    }

    public function enter(Fiber $fiber): void
    {
        self::$current ??= new WeakMap();
        self::$current[$fiber] = WeakReference::create($this);
    }

    public function finish(): void
    {
        $this->finished = true;
        $this->cancellations = [];
        $fiber = Fiber::getCurrent();
        if ($fiber !== null && self::$current !== null) {
            unset(self::$current[$fiber]);
        }
        $finalizers = $this->finalizers;
        $this->finalizers = [];
        foreach ($finalizers as $finalizer) {
            $finalizer();
        }
    }

    /** @internal Callback освобождения не выполняет I/O и не уступает управление.
     * @param Closure(): void $callback
     */
    public function onFinish(Closure $callback): void
    {
        if ($this->finished) {
            $callback();
        } else {
            $this->finalizers[] = $callback;
        }
    }

    public function check(): void
    {
        if ($this->cancelled) {
            throw new ExecutionCancelledException();
        }
    }

    /** @param Closure(bool): void $callback
     * @return Closure(): void
     */
    public function onCancel(Closure $callback): Closure
    {
        $this->check();
        $id = $this->nextId++;
        $this->cancellations[$id] = $callback;
        return function () use ($id): void {
            unset($this->cancellations[$id]);
        };
    }

    public function cancel(bool $resume = true): void
    {
        if ($this->cancelled || $this->finished) {
            return;
        }
        $this->cancelled = true;
        $this->abandoned = !$resume;
        $callbacks = $this->cancellations;
        $this->cancellations = [];
        $failure = null;
        foreach ($callbacks as $callback) {
            try {
                $callback($resume);
            } catch (Throwable $error) {
                $failure ??= $error;
            }
        }
        if ($resume && $failure !== null) {
            throw $failure;
        }
    }

    public function retain(): void
    {
        $this->consumers++;
    }

    public function release(): void
    {
        if (--$this->consumers === 0 && !$this->finished) {
            // Удаление handle снимает ожидания, но не запускает loop или новый HTTP.
            $this->cancel(false);
        }
    }
}
