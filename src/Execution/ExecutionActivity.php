<?php

declare(strict_types=1);

namespace ApiSutra\Execution;

use Closure;

/** @internal Владение транспортом до фактического cleanup, включая ленивый обход. */
final class ExecutionActivity
{
    private int $active = 0;

    /** @return Closure(): void */
    public function acquire(): Closure
    {
        $this->active++;
        $released = false;
        return function () use (&$released): void {
            if (!$released) {
                $released = true;
                $this->active--;
            }
        };
    }

    public function isActive(): bool
    {
        return $this->active > 0;
    }
}
