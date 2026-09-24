<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Continuation;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\ContinuationResult;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Dto\ContinuationFinalDto;
use stdClass;

#[Get('/start')]
#[ContinuationResult(ContinuationFinalDto::class, stateResolver: stdClass::class)]
final class InvalidResolverRequest extends AbstractRequest
{
}
