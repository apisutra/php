<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\DataTransfer;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class EmptyStringAsNull
{
    public function __construct(
        public bool $blank = false,
    ) {
    }

    public function matches(string $value): bool
    {
        if ($this->blank) {
            return trim($value) === '';
        }

        return $value === '';
    }
}
