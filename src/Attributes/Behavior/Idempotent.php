<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Behavior;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Idempotent
{
    public function __construct(
        public ?string $header = null,
    ) {
    }
}
