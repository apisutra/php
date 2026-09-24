<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Http;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Put
{
    public function __construct(
        public string $path,
    ) {
    }
}
