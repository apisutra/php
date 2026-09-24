<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Hooks;

use ApiSutra\VO\Pipeline\PipelineContext;

interface BeforeSendHookInterface extends HookInterface
{
    public function handle(PipelineContext $context): ?array;
}
