<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ConstructorOwned;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Get('/report')]
#[Returns(ArrayDto::class, unwrap: 'data')]
final class ArrayRequest extends AbstractRequest
{
}
