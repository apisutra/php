<?php

declare(strict_types=1);

namespace Example\DtoHydrator;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Get('/user')]
#[Returns(User::class)]
final class GetUser extends AbstractRequest
{
}
