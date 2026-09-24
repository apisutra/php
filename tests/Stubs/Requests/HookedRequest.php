<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Hooks\BeforeSend;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Hooks\HookPriority;
use ApiSutra\Tests\Stubs\Hooks\HookRecorder;
use ApiSutra\Tests\Stubs\Hooks\RecordBeforeSendFirstHook;
use ApiSutra\Tests\Stubs\Hooks\RecordBeforeSendLastHook;
use ApiSutra\Tests\Stubs\Hooks\RecordBeforeSendNormalHook;
use ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/hooked')]
#[BeforeSend(RecordBeforeSendFirstHook::class, priority: HookPriority::First)]
#[BeforeSend(RecordBeforeSendNormalHook::class, priority: HookPriority::Normal)]
#[BeforeSend(RecordBeforeSendLastHook::class, priority: HookPriority::Last)]
final class HookedRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $payload,
    ) {}

    protected function beforeSend(PipelineContext $context): void
    {
        HookRecorder::add('request-before-send');
    }
}
