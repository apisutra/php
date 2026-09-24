<?php

declare(strict_types=1);

namespace ApiSutra\Pagination;

use ApiSutra\Localization\Message;
use ApiSutra\Config\PaginationConfig;
use ApiSutra\Contracts\Interfaces\Pagination\PaginationItemsCollectionFactoryInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use Throwable;

/**
 * Создаёт коллекцию items по конфигу пагинации.
 * Порядок разрешения: factory → collection class → массив.
 */
final readonly class PaginationItemsCollectionBuilder
{
    /**
     * @param array<array-key, mixed> $items
     */
    public function build(array $items, PaginationConfig $config): array|object
    {
        // Сначала отдаём приоритет фабрике, чтобы контейнер создавался контролируемо
        $fromFactory = $this->buildFromFactory($items, $config);
        if ($fromFactory !== null) {
            return $fromFactory;
        }

        // Затем пробуем сам класс коллекции
        $fromClass = $this->buildFromCollectionClass($items, $config);
        if ($fromClass !== null) {
            return $fromClass;
        }

        return $items;
    }

    /**
     * @param array<array-key, mixed> $items
     */
    private function buildFromFactory(array $items, PaginationConfig $config): array|object|null
    {
        if ($config->itemsCollectionFactory === null) {
            return null;
        }

        $factory = $config->itemsCollectionFactory;
        if (is_string($factory)) {
            if (!class_exists($factory)) {
                throw new ConfigurationException(new Message('pagination.factory_class_not_found', ['factory' => $factory]));
            }
            $factory = new $factory();
        }

        if (!$factory instanceof PaginationItemsCollectionFactoryInterface) {
            throw new ConfigurationException(new Message('pagination.collection_factory_must_implement_paginationitemscollectionfactoryinterface'));
        }

        return $factory->make($items);
    }

    /**
     * @param array<array-key, mixed> $items
     */
    private function buildFromCollectionClass(array $items, PaginationConfig $config): array|object|null
    {
        if ($config->itemsCollection === null) {
            return null;
        }

        $class = $config->itemsCollection;
        if (!class_exists($class)) {
            throw new ConfigurationException(new Message('pagination.collection_class_not_found', ['class' => $class]));
        }

        if (method_exists($class, 'fromArray')) {
            return $class::fromArray($items);
        }

        if (method_exists($class, 'make')) {
            return $class::make($items);
        }

        try {
            return new $class($items);
        } catch (Throwable $exception) {
            throw new ConfigurationException(new Message('pagination.cannot_create_the_items_collection'), previous: $exception);
        }
    }
}
