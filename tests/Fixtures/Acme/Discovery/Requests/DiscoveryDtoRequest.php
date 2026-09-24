<?php

declare(strict_types=1);

namespace Acme\Discovery\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;

#[Get('/discovery-dto')]
#[Returns(SimpleResponseDto::class)]
final class DiscoveryDtoRequest extends AbstractRequest
{
}
