<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MetadataIsolation;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Get('/defaults')]
#[Returns(DefaultsDto::class)]
final class DefaultsRequest extends AbstractRequest
{
}
