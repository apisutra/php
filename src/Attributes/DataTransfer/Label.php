<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\DataTransfer;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Label
{
    public function __construct(
        public string $name,
    ) {
    }
}
