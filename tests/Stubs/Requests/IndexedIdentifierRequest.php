<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use ApiSutra\Tests\Stubs\Dto\StringIdentifierDto;

#[Get('/identifiers')]
#[Returns(SimpleResponseDto::class, unwrap: 'data.0', type: StringIdentifierDto::class)]
final class IndexedIdentifierRequest extends AbstractRequest
{
}
