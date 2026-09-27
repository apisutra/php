<?php

declare(strict_types=1);

namespace Example\PolymorphicJson;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Get('/update')]
#[Returns(Update::class)]
final class GetUpdate extends AbstractRequest
{
}
