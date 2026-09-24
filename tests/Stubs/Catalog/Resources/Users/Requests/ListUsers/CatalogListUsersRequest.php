<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Catalog\Resources\Users\Requests\ListUsers;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Catalog\Resources\Users\Requests\GetUser\Dto\CatalogUserDto;

#[Get('/catalog/users/list')]
#[Returns(CatalogUserDto::class)]
final class CatalogListUsersRequest extends AbstractRequest
{
}
