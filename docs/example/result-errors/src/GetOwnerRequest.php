<?php

declare(strict_types=1);

namespace Example\ResultErrors;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Get('/owner')]
#[Returns(AccountInfo::class, mismatchMessage: 'Получен неожиданный результат чтения владельца.')]
final class GetOwnerRequest extends AbstractRequest
{
}
