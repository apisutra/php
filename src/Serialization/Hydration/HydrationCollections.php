<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Hydration;

use ApiSutra\Collections\AbstractTypedCollection;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Rules\HydrationScope;

/** @internal Общие операции элемента и коллекции без изменения границ ошибок и фабрик. */
final readonly class HydrationCollections
{
    /** @return class-string|null */
    public static function itemClass(?string $collection): ?string
    {
        return $collection !== null && is_subclass_of($collection, AbstractTypedCollection::class)
            ? $collection::declaredItemClass() : null;
    }

    public static function item(
        mixed $item,
        string $dtoClass,
        int $index,
        HydrationScope $scope,
        bool $selectVariants = true,
    ): object {
        try {
            if (!is_array($item) && !is_object($item)) {
                throw HydrationException::invalidValue('unexpected_response_shape', $dtoClass, get_debug_type($item));
            }
            return $scope->hydrateDto($item, $dtoClass, selectVariants: $selectVariants);
        } catch (HydrationException $exception) {
            throw $exception->prependPath('[' . $index . ']');
        }
    }

    /** @param array<int|string, mixed> $items */
    public static function wrap(array $items, ?string $propertyType): mixed
    {
        if ($propertyType === null || $propertyType === 'array') {
            return $items;
        }

        if (class_exists($propertyType)) {
            if (is_callable([$propertyType, 'fromArray'])) {
                return $propertyType::fromArray($items);
            }

            return new $propertyType($items);
        }

        return $items;
    }
}
