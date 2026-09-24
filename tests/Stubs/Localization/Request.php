<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Localization;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Get('/localized')]
#[Returns(ParentDto::class)]
final class Request extends AbstractRequest
{
}
