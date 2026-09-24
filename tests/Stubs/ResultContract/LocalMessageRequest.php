<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ResultContract;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Get('/contract')]
#[Returns(ContractDto::class, mismatchMessage: 'Локальное сообщение операции.')]
final class LocalMessageRequest extends AbstractRequest
{
}
