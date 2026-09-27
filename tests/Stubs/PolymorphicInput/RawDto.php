<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\PolymorphicInput;

use ApiSutra\Attributes\DataTransfer\Extras;

final readonly class RawDto
{
    /** @param array<array-key, mixed> $raw */
    public function __construct(#[Extras] public array $raw = [])
    {
    }
}
