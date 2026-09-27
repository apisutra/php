<?php

declare(strict_types=1);

namespace ApiSutra\Collections;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

/**
 * @template T of object
 * @extends AbstractCollection<T>
 */
abstract readonly class AbstractTypedCollection extends AbstractCollection
{
    /**
     * @return class-string<T>
     */
    abstract protected static function itemClass(): string;

    /**
     * @internal Статическая декларация для проверки схемы без создания коллекции.
     * @return class-string<T>
     */
    final public static function declaredItemClass(): string
    {
        $class = static::itemClass();
        if ($class === '') {
            throw new ConfigurationException(new Message('collections.collection_item_type_is_not_specified'));
        }

        if (!class_exists($class) && !interface_exists($class) && !enum_exists($class)) {
            throw new ConfigurationException(new Message('collections.collection_item_class_not_found', ['class' => $class]));
        }

        return $class;
    }

    /** @param array<int, mixed> $items */
    protected function validateItems(array $items): void
    {
        $class = static::declaredItemClass();
        foreach ($items as $item) {
            if (!$item instanceof $class) {
                throw new ConfigurationException(new Message('collections.invalid_collection_item_type', ['class' => $class]));
            }
        }
    }
}
