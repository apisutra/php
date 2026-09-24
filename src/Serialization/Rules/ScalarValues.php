<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\IntegerRange;
use ApiSutra\Serialization\PropertyTypeInspector;
use ApiSutra\Serialization\SafeScalarHydrationCaster;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Stringable;

/** @internal Проверка скаляров до reflection с единственным точным расширением int → float. */
final readonly class ScalarValues
{
    private const int EXACT_FLOAT_INTEGER = 9007199254740992;

    public function __construct(private PropertyTypeInspector $types = new PropertyTypeInspector())
    {
    }

    /** @param list<string> $types */
    public function coerce(mixed $value, array $types, ScalarPolicy $policy): mixed
    {
        foreach ($types as $type) {
            if ($type === 'null' && $value === null || $this->types->matchesRuntimeType($value, $type)) {
                return $value;
            }
        }
        if ($policy === ScalarPolicy::Strict && $this->canWiden($value, $types)) {
            return (float) $value;
        }
        if (in_array('int', $types, true) && IntegerRange::overflows($value)) {
            throw HydrationException::invalidValue('integer_out_of_range', implode('|', $types), get_debug_type($value));
        }
        if ($policy === ScalarPolicy::Legacy) {
            $caster = new SafeScalarHydrationCaster();
            foreach ($types as $type) {
                if ($caster->canHydrate($type, $value)) {
                    return $caster->hydrate($type, $value);
                }
            }
            foreach (['int', 'float', 'string', 'bool'] as $type) {
                if (!in_array($type, $types, true)) {
                    continue;
                }
                $numeric = is_scalar($value) && (!is_string($value) || is_numeric($value));
                if ($type === 'int' && $numeric) {
                    return (int) $value;
                }
                if ($type === 'float' && $numeric) {
                    return (float) $value;
                }
                if ($type === 'string' && (is_scalar($value) || $value instanceof Stringable)) {
                    return (string) $value;
                }
                if ($type === 'bool' && is_scalar($value)) {
                    return (bool) $value;
                }
            }
        }
        throw HydrationException::invalidValue(
            $value === null ? 'null_not_allowed' : 'invalid_field_type',
            implode('|', $types),
            get_debug_type($value),
        );
    }

    public function strictReflection(mixed $value, ?ReflectionType $type, ReflectionClass $owner): mixed
    {
        if ($type === null || $this->accepts($value, $type, $owner)) {
            return $value;
        }
        $names = $this->names($type, $owner);
        if ($this->canWiden($value, $names)) {
            return (float) $value;
        }
        if (in_array('int', $names, true) && IntegerRange::overflows($value)) {
            throw HydrationException::invalidValue('integer_out_of_range', (string) $type, get_debug_type($value));
        }
        throw HydrationException::invalidValue(
            $value === null ? 'null_not_allowed' : 'invalid_field_type',
            (string) $type,
            get_debug_type($value),
        );
    }

    /** @param list<string> $types */
    private function canWiden(mixed $value, array $types): bool
    {
        return is_int($value) && in_array('float', $types, true)
            && $value >= -self::EXACT_FLOAT_INTEGER && $value <= self::EXACT_FLOAT_INTEGER;
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
        return $type instanceof ReflectionNamedType
            && $this->types->matchesRuntimeType($value, PropertyTypeInspector::resolveName($type->getName(), $owner));
    }

    /** @return list<string> */
    private function names(ReflectionType $type, ReflectionClass $owner): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [PropertyTypeInspector::resolveName($type->getName(), $owner)];
        }
        if ($type instanceof ReflectionUnionType) {
            $names = [];
            foreach ($type->getTypes() as $part) {
                $names = [...$names, ...$this->names($part, $owner)];
            }
            return $names;
        }
        return [];
    }
}
