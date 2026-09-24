<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ConstructorOwned;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\ContinuationResult;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Requests\ContinuationPollRequest;

#[Get('/await')]
#[ContinuationResult(NodeDto::class, pollRequest: ContinuationPollRequest::class, unwrap: 'data')]
final class AwaitRequest extends AbstractRequest
{
}
