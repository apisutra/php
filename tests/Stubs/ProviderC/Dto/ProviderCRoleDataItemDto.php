<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderC\Dto;

use ApiSutra\DataTransfer\AbstractDto;

final readonly class ProviderCRoleDataItemDto extends AbstractDto
{
    public function __construct(
        public string $id,
    ) {}
}
