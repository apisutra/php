<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ResultContract;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\AttributeHydration\Record;

#[Get('/attributed-record')]
#[Returns(Record::class, unwrap: 'data', mismatchMessage: 'Операция вернула неожиданный тип записи.')]
final class AttributedRequest extends AbstractRequest
{
}
