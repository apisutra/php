<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Hooks;

use ApiSutra\VO\Pipeline\PipelineContext;

interface HookInterface
{
    /**
     * Выполнить хук
     */
    public function handle(PipelineContext $context): ?array;
}
