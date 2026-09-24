<?php

declare(strict_types=1);

namespace Example\Continuation;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Response\ContinuationResult;
use ApiSutra\Core\AbstractRequest;

#[Post('/operations')]
#[ContinuationResult(
    finalType: FinalDto::class,
    pollRequest: PollRequest::class,
    stateResolver: OperationStateResolver::class,
)]
final class StartRequest extends AbstractRequest
{
}
