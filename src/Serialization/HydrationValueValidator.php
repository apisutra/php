<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

use ApiSutra\Exceptions\Serialization\HydrationException;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Stringable;

/** Проверяет невозможные присваивания, сохраняя разрешённые reflection scalar conversions. */
final readonly class HydrationValueValidator
{
    public function __construct(private PropertyTypeInspector $types = new PropertyTypeInspector())
    {
    }

    public function assertValue(mixed $value, ?ReflectionType $type, ReflectionClass $owner, string $path): void
    {
        if ($type === null || $this->accepts($value, $type, $owner)) {
            return;
        }

        throw HydrationException::invalidValue(
            $value === null ? 'null_not_allowed' : 'invalid_field_type',
            (string) $type,
            get_debug_type($value),
            $path,
        );
    }

    private function accepts(mixed $value, ReflectionType $type, ReflectionClass $owner): bool
    {
        if ($value === null) {
            return $type->allowsNull();
        }
        if ($type instanceof ReflectionUnionType) {
            return array_any($type->getTypes(), fn (ReflectionType $part): bool => $this->accepts($value, $part, $owner));
        }
        if ($type instanceof ReflectionIntersectionType) {
            return array_all($type->getTypes(), fn (ReflectionType $part): bool => $this->accepts($value, $part, $owner));
        }
        if (!$type instanceof ReflectionNamedType) {
            return false;
        }

        $name = PropertyTypeInspector::resolveName($type->getName(), $owner);
        if ($this->types->matchesRuntimeType($value, $name)) {
            return true;
        }

        return match ($name) {
            'int' => (is_scalar($value) && !is_string($value) || is_string($value) && is_numeric($value))
                && !IntegerRange::overflows($value),
            'float' => is_bool($value) || is_int($value) || is_string($value) && is_numeric($value),
            'string' => is_scalar($value) || $value instanceof Stringable,
            'bool' => is_scalar($value),
            default => false,
        };
    }
}
