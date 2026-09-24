<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hooks;

use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\VO\Pipeline\PipelineContext;

final class RecordBeforeSendHook implements HookInterface
{
    public function handle(PipelineContext $context): ?array
    {
        HookRecorder::add('beforeSend:attr');
        return null;
    }
}
