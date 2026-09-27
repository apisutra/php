<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

use ApiSutra\Serialization\Variants\VariantDefinition;
use ApiSutra\Localization\Message;
use ApiSutra\Enums\DataTransfer\DiscriminatorMode;
use ApiSutra\Enums\DataTransfer\UnknownVariant;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

final readonly class ValueShape
{
    /**
     * @param list<string> $types
     */
    private function __construct(
        public string $kind,
        public array $types = [],
        public ?self $item = null,
        public ?string $class = null,
        public ?string $each = null,
        public ?HandlerSpec $itemCast = null,
        public bool $normalizeKeys = false,
        public ?VariantDefinition $variants = null,
        public bool $emptyListAsObject = false,
    ) {
    }

    public static function int(): self
    {
        return new self('scalar', ['int']);
    }

    public static function float(): self
    {
        return new self('scalar', ['float']);
    }

    public static function bool(): self
    {
        return new self('scalar', ['bool']);
    }

    public static function true(): self
    {
        return new self('scalar', ['true']);
    }

    public static function false(): self
    {
        return new self('scalar', ['false']);
    }

    public static function string(): self
    {
        return new self('scalar', ['string']);
    }

    public static function mixed(): self
    {
        return new self('mixed');
    }

    public static function scalars(ScalarType ...$types): self
    {
        if ($types === []) {
            throw new ConfigurationException(new Message('serialization.valueshape_scalars_requires_at_least_one_type'));
        }
        return new self('scalar', array_values(array_unique(array_map(
            static fn (ScalarType $type): string => $type->value,
            $types,
        ))));
    }

    public static function nullable(self $shape): self
    {
        return new self('nullable', item: $shape);
    }

    public static function dto(string $class, bool $emptyListAsObject = false): self
    {
        return new self('dto', class: $class, emptyListAsObject: $emptyListAsObject);
    }

    public static function list(
        self $item,
        ?string $each = null,
        ?HandlerSpec $itemCast = null,
        bool $normalizeKeys = false,
    ): self {
        return new self('list', item: $item, each: $each, itemCast: $itemCast, normalizeKeys: $normalizeKeys);
    }

    /** @param array<int|string, class-string> $map */
    public static function variants(
        string $discriminator,
        array $map,
        DiscriminatorMode $mode = DiscriminatorMode::Value,
        UnknownVariant|string $unknown = UnknownVariant::KeepRaw,
    ): self {
        return new self('variants', variants: new VariantDefinition($discriminator, $map, $mode, $unknown));
    }
}
