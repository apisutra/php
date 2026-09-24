<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\ContinuationResult;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Requests\ContinuationPollRequest;

#[Get('/await')]
#[ContinuationResult(Row::class, pollRequest: ContinuationPollRequest::class, unwrap: 'data')]
final class AwaitRequest extends AbstractRequest
{
}
