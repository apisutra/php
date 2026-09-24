<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ResultContract;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\HydrationRules\OwnerDto;

#[Get('/external-record')]
#[Returns(OwnerDto::class)]
final class ExternalRecordRequest extends AbstractRequest
{
}
