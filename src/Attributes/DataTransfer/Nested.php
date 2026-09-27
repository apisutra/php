<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\DataTransfer;

use Attribute;
use ApiSutra\Enums\DataTransfer\DiscriminatorMode;
use ApiSutra\Enums\DataTransfer\UnknownVariant;

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
        public DiscriminatorMode $discriminatorMode = DiscriminatorMode::Value,
        public UnknownVariant|string $unknownVariant = UnknownVariant::KeepRaw,
    ) {
    }
}
