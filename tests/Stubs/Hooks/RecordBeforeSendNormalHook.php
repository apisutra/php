<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hooks;

use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\VO\Pipeline\PipelineContext;

final class RecordBeforeSendNormalHook implements HookInterface
{
    public function handle(PipelineContext $context): ?array
    {
        HookRecorder::add('before-send-normal');
        return null;
    }
}
