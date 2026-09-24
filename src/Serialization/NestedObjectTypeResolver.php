<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

use ApiSutra\Localization\Message;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use Traversable;

/** @internal Выбирает одиночный DTO; null сохраняет обработку коллекции. */
final readonly class NestedObjectTypeResolver
{
    /** @return class-string|null */
    public function resolve(Nested $nested, ReflectionProperty $property): ?string
    {
        if ($nested->type !== null) {
            $this->assertAvailable($nested->type);
        }

        $type = $property->getType();
        $types = $type instanceof ReflectionUnionType ? $type->getTypes() : ($type === null ? [] : [$type]);
        $names = [];
        foreach ($types as $part) {
            if (!$part instanceof ReflectionNamedType) {
                throw new ConfigurationException(new Message('serialization.nested_does_not_support_intersection_types', ['value0' => $property->getName()]));
            }
            if ($part->getName() !== 'null') {
                if ($part->getName() === 'parent' && $property->getDeclaringClass()->getParentClass() === false) {
                    throw new ConfigurationException(new Message('serialization.parent_not_found_for_nested', ['value0' => $property->getName()]));
                }
                $names[] = PropertyTypeInspector::resolveName($part->getName(), $property);
            }
        }

        if (count($names) > 1) {
            foreach ($names as $name) {
                if (!class_exists($name) && !interface_exists($name)) {
                    throw new ConfigurationException(new Message('serialization.ambiguous_nested_cardinality', ['value0' => $property->getName()]));
                }
            }
            if ($nested->type === null) {
                throw new ConfigurationException(new Message('serialization.union_property_requires_nested_type', ['value0' => $property->getName()]));
            }
        }

        $propertyType = $names[0] ?? null;
        if ($propertyType === null || in_array($propertyType, ['array', 'iterable', 'mixed'], true)) {
            return null;
        }

        $targetType = $nested->type ?? $propertyType;
        $compatible = $propertyType === 'object';
        foreach ($names as $name) {
            $compatible = $compatible || is_a($targetType, $name, true);
        }

        if (count($names) === 1 && class_exists($propertyType)) {
            if (is_a($propertyType, Traversable::class, true)) {
                return null;
            }
            // Сохраняем пользовательские обёртки списка с конструктором от массива.
            $hasFactory = is_callable([$propertyType, 'fromArray']);
            if (
                (!$compatible && ($hasFactory || $this->acceptsItems($propertyType)))
                || ($hasFactory && $nested->type === null && $nested->map !== null)
            ) {
                return null;
            }
        }

        if (!$compatible) {
            throw new ConfigurationException(new Message('serialization.nested_type_is_incompatible_with_the_property_type', ['value0' => $property->getName()]));
        }
        if (
            $nested->each !== null || $nested->itemCast !== null
            || $nested->map !== null || $nested->discriminator !== null
        ) {
            throw new ConfigurationException(
                new Message('serialization.nested_list_parameters_cannot_be_used_for_a_single', ['value0' => $property->getName()]),
            );
        }

        $this->assertAvailable($targetType);

        return $targetType;
    }

    private function assertAvailable(string $type): void
    {
        if (!class_exists($type) || (new ReflectionClass($type))->isAbstract() || enum_exists($type)) {
            throw new ConfigurationException(new Message('serialization.nested_class_is_unavailable_for_hydration', ['type' => $type]));
        }
    }

    /** @param class-string $type */
    private function acceptsItems(string $type): bool
    {
        $constructor = (new ReflectionClass($type))->getConstructor();
        if ($constructor === null || !$constructor->isPublic() || $constructor->getNumberOfRequiredParameters() > 1) {
            return false;
        }
        $parameter = $constructor->getParameters()[0] ?? null;
        if ($parameter === null) {
            return false;
        }
        $parameterType = $parameter->getType();
        if ($parameterType === null) {
            return true;
        }
        $types = $parameterType instanceof ReflectionUnionType ? $parameterType->getTypes() : [$parameterType];
        foreach ($types as $part) {
            if (
                $part instanceof ReflectionNamedType
                && in_array($part->getName(), ['array', 'iterable', 'mixed'], true)
            ) {
                return true;
            }
        }

        return false;
    }
}
