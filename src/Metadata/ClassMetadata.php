<?php

declare(strict_types=1);

namespace ApiSutra\Metadata;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;

/** @internal Нейтральное описание без policy, материализованных args/default и данных вызова. */
final readonly class ClassMetadata
{
    /** @var list<ReflectionProperty> Порядок свойств соответствует Reflection. */
    public array $properties;

    /** @var list<ReflectionAttribute<object>> */
    public array $attributes;

    public function __construct(public ReflectionClass $reflection)
    {
        $this->properties = $reflection->getProperties();
        $this->attributes = $reflection->getAttributes();
    }

    /** @return ?list<array{name: string, reflection: ReflectionParameter, hasDefault: bool}> */
    public function constructorParameters(): ?array
    {
        $constructor = $this->reflection->getConstructor();
        return $constructor === null ? null : array_map(
            static fn (ReflectionParameter $parameter): array => [
                'name' => $parameter->getName(),
                'reflection' => $parameter,
                'hasDefault' => $parameter->isDefaultValueAvailable(),
            ],
            $constructor->getParameters(),
        );
    }

    /** @return list<ReflectionAttribute<object>> */
    public function attributes(string $class, bool $inherited = false): array
    {
        $matches = $this->attributes === [] ? [] : array_values(array_filter(
            $this->attributes,
            static fn (ReflectionAttribute $attribute): bool => strcasecmp($attribute->getName(), $class) === 0,
        ));
        if ($matches !== [] || !$inherited) {
            return $matches;
        }
        // Поиск классовой декларации не требует описаний всех свойств каждого предка.
        for ($parent = $this->reflection->getParentClass(); $parent !== false; $parent = $parent->getParentClass()) {
            $matches = $parent->getAttributes($class);
            if ($matches !== []) {
                return $matches;
            }
        }
        return [];
    }

    /**
     * Адаптер общего реестра атрибутов; это не план гидрации или сериализации.
     * @return array{
     *   class: list<array{attribute: ReflectionAttribute}>,
     *   properties: list<array{attribute: ReflectionAttribute, property: ReflectionProperty}>
     * }
     */
    public function registryMetadata(): array
    {
        $properties = [];
        foreach ($this->properties as $property) {
            foreach ($property->getAttributes() as $attribute) {
                $properties[] = ['attribute' => $attribute, 'property' => $property];
            }
        }
        return [
            'class' => array_map(
                static fn (ReflectionAttribute $attribute): array => ['attribute' => $attribute],
                $this->attributes,
            ),
            'properties' => $properties,
        ];
    }
}
