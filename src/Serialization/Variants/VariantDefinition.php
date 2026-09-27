<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Variants;

use ApiSutra\Enums\DataTransfer\DiscriminatorMode;
use ApiSutra\Enums\DataTransfer\UnknownVariant;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;
use ReflectionClass;

/** @internal Разрешённая декларация; default выбирает публичный адаптер. */
final readonly class VariantDefinition
{
    /** @param array<array-key, class-string> $map */
    public function __construct(
        public string $discriminator,
        public array $map,
        public DiscriminatorMode $mode,
        public UnknownVariant|string $unknown,
    ) {
    }

    public function validate(string $position, bool $allowSkip, bool $allowRaw, ?string $target = null): void
    {
        if ($this->map === []) {
            throw new ConfigurationException(new Message('serialization.variants_empty_map', ['position' => $position]));
        }
        if ($this->mode === DiscriminatorMode::Value && $this->discriminator === '') {
            throw new ConfigurationException(new Message('serialization.value_discriminator_requires_a_non_empty_path'));
        }
        if ($this->unknown === UnknownVariant::Skip && !$allowSkip || $this->unknown === UnknownVariant::KeepRaw && !$allowRaw) {
            throw new ConfigurationException(new Message('serialization.variant_policy_not_allowed', [
                'position' => $position, 'policy' => $this->unknown->name,
            ]));
        }
        foreach ($this->classes() as $class) {
            $this->validateClass($class, $position);
            if ($target !== null && !is_a($class, $target, true)) {
                throw new ConfigurationException(new Message('serialization.variant_class_incompatible', [
                    'class' => $class, 'target' => $target, 'position' => $position,
                ]));
            }
        }
    }

    private function validateClass(mixed $class, string $position): void
    {
        if (!is_string($class) || !class_exists($class) || (new ReflectionClass($class))->isAbstract() || enum_exists($class)) {
            throw new ConfigurationException(new Message('serialization.variant_class_unavailable', [
                'position' => $position, 'class' => is_string($class) ? $class : get_debug_type($class),
            ]));
        }
    }

    /** @return list<class-string> */
    public function classes(): array
    {
        $classes = array_values($this->map);
        if (is_string($this->unknown)) {
            $classes[] = $this->unknown;
        }
        return $classes;
    }

    public function accepts(object $value): bool
    {
        foreach ($this->map as $class) {
            if ($value instanceof $class) {
                return true;
            }
        }
        return is_string($this->unknown) && $value instanceof $this->unknown;
    }
}
