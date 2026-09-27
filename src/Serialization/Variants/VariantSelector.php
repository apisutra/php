<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Variants;

use ApiSutra\Enums\DataTransfer\DiscriminatorMode;
use ApiSutra\Enums\DataTransfer\UnknownVariant;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Support\ArrayPath;

/** @internal Общий выбор Value/Key, без создания DTO и изменения scope. */
final readonly class VariantSelector
{
    public static function select(mixed $value, VariantDefinition $definition): VariantSelection
    {
        $source = is_object($value) ? get_object_vars($value) : $value;
        $payload = $value;
        $segments = [];
        if ($definition->mode === DiscriminatorMode::Key) {
            if ($definition->discriminator !== '') {
                $segments = explode('.', $definition->discriminator);
                $source = ArrayPath::getByPath($source, $definition->discriminator);
            }
            $tag = is_array($source) && $source !== [] ? array_key_first($source) : null;
            if ($tag !== null) {
                $payload = $source[$tag];
                $segments[] = $tag;
            }
        } else {
            $tag = ArrayPath::getByPath($source, $definition->discriminator);
            if ($tag !== null && !is_string($tag) && !is_int($tag)) {
                throw HydrationException::invalidValue('invalid_discriminator_type', 'string|int', get_debug_type($tag));
            }
        }
        $class = $tag === null ? null : ($definition->map[$tag] ?? null);
        if ($class !== null) {
            return new VariantSelection($class, $payload, $segments);
        }
        if (is_string($definition->unknown)) {
            return new VariantSelection($definition->unknown, $value);
        }
        return match ($definition->unknown) {
            UnknownVariant::Skip => new VariantSelection(null, null, skip: true),
            UnknownVariant::KeepRaw => new VariantSelection(null, $value),
            UnknownVariant::Error => throw HydrationException::invalidValue(
                'unknown_nested_variant',
                'variant: ' . implode('|', array_keys($definition->map)),
                $tag === null ? 'missing' : get_debug_type($tag),
            ),
        };
    }
}
