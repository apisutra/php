<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Behavior;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Timeout
{
    public function __construct(
        public int $seconds,
        public ?int $connectTimeout = null,
    ) {
    }
}
