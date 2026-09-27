<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Hydration;

use ApiSutra\Attributes\DataTransfer\DtoVariants;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;
use ApiSutra\Metadata\MetadataCatalog;
use ApiSutra\Serialization\Variants\VariantDefinition;
use Throwable;

/** @internal Назначение гидратации без создания объектов или компиляции их полей. */
final class DtoTargetCompiler
{
    /** @var array<string, VariantDefinition|null> */
    private array $variants = [];
    /** @var array<string, true> */
    private array $validated = [];

    public function __construct(
        private readonly ?HydrationConfig $config = null,
        private readonly MetadataCatalog $metadata = new MetadataCatalog(),
    ) {
    }

    public function validate(string $type): void
    {
        if (isset($this->validated[$type])) {
            return;
        }
        $this->validateDeclaration($type);
        if ($this->variantsFor($type) === null) {
            if (!class_exists($type) || $this->metadata->forClass($type)->reflection->isAbstract() || enum_exists($type)) {
                throw new ConfigurationException(new Message('serialization.dto_class_is_unavailable_for_hydration', ['dtoClass' => $type]));
            }
        }
        $this->validated[$type] = true;
    }

    /** Готовый результат может соответствовать abstract/interface без рецепта создания. */
    public function validateDeclaration(string $type): void
    {
        if (!class_exists($type) && !interface_exists($type)) {
            throw new ConfigurationException(new Message('serialization.dto_class_is_unavailable_for_hydration', ['dtoClass' => $type]));
        }
        $this->variantsFor($type);
    }

    public function variantsFor(string $type): ?VariantDefinition
    {
        if (array_key_exists($type, $this->variants)) {
            return $this->variants[$type];
        }
        if (!class_exists($type) && !interface_exists($type)) {
            return null;
        }
        $declarations = $this->metadata->forClass($type)->attributes(DtoVariants::class);
        $definition = $this->config?->rules?->variantsFor($type);
        if (count($declarations) > 1 || $declarations !== [] && $definition !== null) {
            throw new ConfigurationException(new Message('serialization.duplicate_dto_variants', ['type' => $type]));
        }
        if ($declarations !== []) {
            try {
                $definition = $declarations[0]->newInstance()->definition;
            } catch (Throwable $exception) {
                throw new ConfigurationException(new Message('serialization.invalid_declaration_of', ['attribute' => DtoVariants::class]), previous: $exception);
            }
        }
        $definition?->validate('DTO type ' . $type, allowSkip: false, allowRaw: false, target: $type);
        return $this->variants[$type] = $definition;
    }
}
