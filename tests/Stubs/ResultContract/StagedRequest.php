<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ResultContract;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Get('/staged')]
#[Returns(StagedDto::class)]
final class StagedRequest extends AbstractRequest
{
}
