<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\Extras;

final readonly class ArrayReceiverDto
{
    public function __construct(#[Extras] public array $extra = [])
    {
    }
    public function toArray(): array
    {
        return ['custom' => $this->extra];
    }
}
