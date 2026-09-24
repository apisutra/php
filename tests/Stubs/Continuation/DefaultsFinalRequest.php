<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Continuation;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\ContinuationResult;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\MetadataIsolation\DefaultsDto;

#[Get('/start')]
#[ContinuationResult(DefaultsDto::class, unwrap: 'data')]
final class DefaultsFinalRequest extends AbstractRequest
{
}
