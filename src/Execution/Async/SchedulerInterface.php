<?php

declare(strict_types=1);

namespace ApiSutra\Execution\Async;

use Closure;

/** @internal Минимальная граница планирования; не содержит правил HTTP и исполнения. */
interface SchedulerInterface
{
    /** @param Closure(): void $callback */
    public function queue(Closure $callback): void;

    /** @param Closure(): void $callback
     * @return Closure(): void Отмена таймера.
     */
    public function delay(int $milliseconds, Closure $callback): Closure;

    public function suspension(): Suspension;
}
