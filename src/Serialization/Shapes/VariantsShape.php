<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Shapes;

use ApiSutra\Enums\DataTransfer\NestedDiscriminatorMode;
use ApiSutra\Enums\DataTransfer\NestedUnknownVariant;

final readonly class VariantsShape implements ShapeSpec
{
    /** @param array<int|string, class-string> $map */
    public function __construct(
        public string $discriminator,
        public array $map,
        public NestedDiscriminatorMode $mode = NestedDiscriminatorMode::Value,
        public NestedUnknownVariant $unknown = NestedUnknownVariant::KeepRaw,
    ) {
    }
}
