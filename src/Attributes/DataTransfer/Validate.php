<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\DataTransfer;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Validate
{
    public function __construct(
        public string $rules,
        public ?string $message = null,
    ) {
    }
}
