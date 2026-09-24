<?php

declare(strict_types=1);

namespace ApiSutra\Execution;

use Closure;
use Fiber;
use WeakMap;

/** @internal Вложенное значение с наследованием только управляемыми SDK-задачами.
 * @template T of object
 */
final class ExecutionLocal
{
    /** @var WeakMap<self<object>, true>|null */
    private static ?WeakMap $instances = null;
    /** @var T|null */
    private ?object $main = null;
    /** @var WeakMap<Fiber, T> */
    private WeakMap $values;

    public function __construct()
    {
        $this->values = new WeakMap();
        self::$instances ??= new WeakMap();
        self::$instances[$this] = true;
    }

    /** @return T|null */
    public function get(): ?object
    {
        $fiber = Fiber::getCurrent();
        return $fiber === null ? $this->main : ($this->values[$fiber] ?? null);
    }

    /** Снимок значений при запуске, а не поиск уже изменившейся области после suspend.
     * @template R
     * @param Closure(): R $operation
     * @return Closure(): R
     */
    public static function inherit(Closure $operation): Closure
    {
        $captured = [];
        foreach (self::$instances ?? [] as $local => $_) {
            $captured[] = [$local, $local->get()];
        }
        return static function () use ($captured, $operation): mixed {
            $leave = [];
            try {
                foreach ($captured as [$local, $value]) {
                    $leave[] = $local->enter($value);
                }
                return $operation();
            } finally {
                foreach (array_reverse($leave) as $restore) {
                    $restore();
                }
            }
        };
    }

    /** @param T|null $value
     * @return Closure(): void
     */
    public function enter(?object $value): Closure
    {
        $fiber = Fiber::getCurrent();
        $previous = $fiber === null ? $this->main : ($this->values[$fiber] ?? null);
        $this->put($fiber, $value);
        return function () use ($fiber, $previous): void {
            $this->put($fiber, $previous);
        };
    }

    /** @param T|null $value */
    private function put(?Fiber $fiber, ?object $value): void
    {
        if ($fiber === null) {
            $this->main = $value;
        } elseif ($value === null) {
            unset($this->values[$fiber]);
        } else {
            $this->values[$fiber] = $value;
        }
    }
}
