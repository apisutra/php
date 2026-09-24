<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\DtoMapping;

use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class FactoryDto extends AbstractDto
{
    private function __construct(#[To('record_id')] public int $id) {}

    public static function create(int $id): self
    {
        return new self($id);
    }
}
