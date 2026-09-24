<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ResultContract;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;

#[Get('/contract')]
#[Returns(SimpleResponseDto::class, unwrap: 'data', type: ContractDto::class)]
final class UnwrappedRequest extends AbstractRequest
{
}
