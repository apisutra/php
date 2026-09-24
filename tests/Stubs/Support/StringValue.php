<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Support;

final readonly class StringValue implements \Stringable
{
    public function __construct(
        private string $value,
    ) {}

    public function __toString(): string
    {
        return $this->value;
    }
}
