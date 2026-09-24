<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Concerns;

use ApiSutra\Serialization\PropertyTypeInspector;
use ReflectionAttribute;
use ReflectionProperty;
use UnitEnum;

trait ReflectionHelperTrait
{
    /**
     * @param array<string, class-string> $classes
     * @param array<string, ReflectionAttribute> $factories
     * @return array<string, object|null>
     */
    private function getPropertyAttributes(ReflectionProperty $property, array $classes, array &$factories): array
    {
        // Отсутствие деклараций известно без материализации и отдельных поисков каждого типа.
        if ($property->getAttributes() === []) {
            return array_fill_keys(array_keys($classes), null);
        }
        $attributes = [];
        foreach ($classes as $key => $class) {
            $declaration = $property->getAttributes($class)[0] ?? null;
            $instance = $declaration?->newInstance();
            $attributes[$key] = $instance;
            // Приведение раскрывает также private/protected свойства, но не вызывает пользовательские методы.
            if ($instance !== null && $this->containsAttributeObject((array) $instance)) {
                $factories[$key] = $declaration;
            }
        }

        return $attributes;
    }

    /** @param array<mixed> $values */
    private function containsAttributeObject(array $values): bool
    {
        foreach ($values as $value) {
            if (is_object($value) && !$value instanceof UnitEnum) {
                return true;
            }
            if (is_array($value) && $this->containsAttributeObject($value)) {
                return true;
            }
        }

        return false;
    }

    private function getPrimaryType(ReflectionProperty $property): ?string
    {
        return $this->propertyTypeInspector()->getPrimaryType($property);
    }

    private function propertyTypeInspector(): PropertyTypeInspector
    {
        static $inspector = null;

        return $inspector ??= new PropertyTypeInspector();
    }
}
