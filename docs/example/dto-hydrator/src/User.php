<?php

declare(strict_types=1);

namespace Example\DtoHydrator;

use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class User extends AbstractDto
{
    private function __construct(
        #[To('user_id')] public int $id,
        public string $name,
        public ?Address $address,
    ) {}

    public static function create(int $id, string $name, ?Address $address): self
    {
        return new self($id, $name, $address);
    }
}
