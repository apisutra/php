<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Dto\EmptyStringNullableDto;

#[Get('/optional')]
#[Returns(EmptyStringNullableDto::class)]
final class OptionalResponseRequest extends AbstractRequest
{
}
