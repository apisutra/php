<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Hooks\AfterHydrate;
use ApiSutra\Attributes\Hooks\AfterResponse;
use ApiSutra\Attributes\Hooks\BeforeHydrate;
use ApiSutra\Attributes\Hooks\BeforeSend;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use ApiSutra\Tests\Stubs\Hooks\HookRecorder;
use ApiSutra\Tests\Stubs\Hooks\RecordAfterHydrateHook;
use ApiSutra\Tests\Stubs\Hooks\RecordAfterResponseHook;
use ApiSutra\Tests\Stubs\Hooks\RecordBeforeHydrateHook;
use ApiSutra\Tests\Stubs\Hooks\RecordBeforeSendHook;
use ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/pipeline')]
#[Returns(SimpleResponseDto::class)]
#[BeforeSend(handler: RecordBeforeSendHook::class)]
#[AfterResponse(handler: RecordAfterResponseHook::class)]
#[BeforeHydrate(handler: RecordBeforeHydrateHook::class)]
#[AfterHydrate(handler: RecordAfterHydrateHook::class)]
final class PipelineHookedRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $query,
    ) {}

    protected function beforeSend(PipelineContext $context): void
    {
        HookRecorder::add('beforeSend:req');
    }

    protected function afterResponse(PipelineContext $context): void
    {
        HookRecorder::add('afterResponse:req');
    }

    protected function beforeHydrate(PipelineContext $context, array $data): array
    {
        HookRecorder::add('beforeHydrate:req');
        return $data;
    }

    protected function afterHydrate(PipelineContext $context): void
    {
        HookRecorder::add('afterHydrate:req');
    }
}
