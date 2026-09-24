<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Continuation;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\ContinuationResult;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Dto\ContinuationFinalDto;

#[Get('/start')]
#[ContinuationResult(ContinuationFinalDto::class)]
final class WithoutCriterionRequest extends AbstractRequest
{
}
