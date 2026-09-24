<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Catalog\Resources\Users\Requests\GetUser\Dto;

final readonly class CatalogUserDto
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
