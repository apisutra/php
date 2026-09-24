<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Plan;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Serialization\PropertyTypeInspector;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

/** @internal Структурные кандидаты; конкретная ветвь выбирается по живому значению. */
final readonly class SerializationValuePlan
{
    /** @var list<string>|null null сохраняет прежний отложенный разбор сложного типа. */
    private ?array $types;
    public ?string $castClass;
    /** @var array<int|string, mixed> */
    public array $castArgs;

    public function __construct(public ReflectionProperty $property, ?Cast $cast = null)
    {
        $this->castClass = $cast?->class;
        $this->castArgs = $cast->args ?? [];
        $type = $property->getType();
        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            $this->types = $name === 'null' ? [] : [PropertyTypeInspector::resolveName($name, $property)];
            return;
        }
        $types = [];
        foreach ($type instanceof ReflectionUnionType ? $type->getTypes() : [$type] as $candidate) {
            if (!$candidate instanceof ReflectionNamedType) {
                $this->types = null;
                return;
            }
            if ($candidate->getName() !== 'null') {
                $types[] = PropertyTypeInspector::resolveName($candidate->getName(), $property);
            }
        }
        $this->types = $types;
    }

    public function typeFor(mixed $value, PropertyTypeInspector $inspector): ?string
    {
        if ($this->types === null) {
            return $inspector->resolvePropertyTypeByValue($this->property, $value);
        }
        foreach ($this->types as $type) {
            if ($inspector->matchesRuntimeType($value, $type)) {
                return $type;
            }
        }
        return $this->types[0] ?? null;
    }
}
