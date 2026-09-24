<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\DataTransfer;

use Attribute;
use ApiSutra\Enums\DataTransfer\NestedDiscriminatorMode;
use ApiSutra\Enums\DataTransfer\NestedUnknownVariant;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Nested
{
    public function __construct(
        public ?string $type = null,
        public ?string $itemCast = null,
        public ?string $from = null,
        public array $fallback = [],
        public ?string $each = null,
        public ?string $discriminator = null,
        public ?array $map = null,
        public NestedDiscriminatorMode $discriminatorMode = NestedDiscriminatorMode::Value,
        public NestedUnknownVariant $unknownVariant = NestedUnknownVariant::KeepRaw,
    ) {
    }
}
