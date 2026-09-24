<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Catalog\Resources\Users\Requests\GetUser;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\OperationDescriptor;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Catalog\Resources\Users\Requests\GetUser\Dto\CatalogUserDto;

#[Get('/catalog/users')]
#[Returns(CatalogUserDto::class)]
#[OperationDescriptor(title: 'Получить пользователя')]
final class CatalogGetUserRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $id,
    ) {}
}
