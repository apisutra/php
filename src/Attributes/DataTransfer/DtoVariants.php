<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\DataTransfer;

use ApiSutra\Enums\DataTransfer\DiscriminatorMode;
use ApiSutra\Enums\DataTransfer\UnknownVariant;
use ApiSutra\Serialization\Variants\VariantDefinition;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class DtoVariants
{
    /** @internal */
    public VariantDefinition $definition;

    /** @param array<array-key, class-string> $map */
    public function __construct(
        string $discriminator,
        array $map,
        DiscriminatorMode $mode = DiscriminatorMode::Value,
        UnknownVariant|string $unknown = UnknownVariant::Error,
    ) {
        $this->definition = new VariantDefinition($discriminator, $map, $mode, $unknown);
    }
}
