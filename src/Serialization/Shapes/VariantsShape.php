<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Shapes;

use ApiSutra\Enums\DataTransfer\DiscriminatorMode;
use ApiSutra\Enums\DataTransfer\UnknownVariant;

final readonly class VariantsShape implements ShapeSpec
{
    /** @param array<int|string, class-string> $map */
    public function __construct(
        public string $discriminator,
        public array $map,
        public DiscriminatorMode $mode = DiscriminatorMode::Value,
        public UnknownVariant|string $unknown = UnknownVariant::KeepRaw,
    ) {
    }
}
