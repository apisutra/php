<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Request;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class OperationDescriptor
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $note = null,
    ) {
    }
}
