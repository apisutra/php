<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Request;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Path
{
    public function __construct(
        public ?string $name = null,
    ) {
    }
}
