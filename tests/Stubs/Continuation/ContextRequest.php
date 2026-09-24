<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Continuation;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\ContinuationResult;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Continuation\ContinuationMode;
use ApiSutra\Tests\Stubs\Requests\ContinuationPollRequest;

#[Get('/continuation/context')]
#[ContinuationResult(
    ContextFinalDto::class,
    unwrap: 'data',
    pollRequest: ContinuationPollRequest::class,
    defaultMode: ContinuationMode::Sync,
    stateResolver: ContextStateResolver::class,
)]
final class ContextRequest extends AbstractRequest
{
}
