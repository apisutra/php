<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hooks;

use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\VO\Pipeline\PipelineContext;

final class RecordBeforeSendFirstHook implements HookInterface
{
    public function handle(PipelineContext $context): ?array
    {
        HookRecorder::add('before-send-first');
        return null;
    }
}
