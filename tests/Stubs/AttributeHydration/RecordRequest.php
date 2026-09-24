<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Get('/record')]
#[Returns(Record::class, unwrap: 'data')]
final class RecordRequest extends AbstractRequest
{
}
