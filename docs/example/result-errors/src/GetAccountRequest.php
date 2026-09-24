<?php

declare(strict_types=1);

namespace Example\ResultErrors;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Get('/account')]
#[Returns(AccountInfo::class, mismatchMessage: 'Получен неожиданный результат чтения аккаунта.')]
final class GetAccountRequest extends AbstractRequest
{
}
