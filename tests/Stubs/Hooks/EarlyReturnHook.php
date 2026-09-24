<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hooks;

use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\Exceptions\ControlFlow\EarlyReturnException;
use ApiSutra\VO\Pipeline\PipelineContext;

final class EarlyReturnHook implements HookInterface
{
    public function handle(PipelineContext $context): ?array
    {
        throw new EarlyReturnException(['ok' => true]);
    }
}
