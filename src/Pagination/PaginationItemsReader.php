<?php

declare(strict_types=1);

namespace ApiSutra\Pagination;

use ApiSutra\Contracts\Interfaces\Pagination\PaginationItemsContainerInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Localization\Message;

/** @internal Читает исходные элементы, не вызывая сериализацию коллекции или DTO. */
final class PaginationItemsReader
{
    /** @return iterable<mixed, mixed> */
    public function read(mixed $data): iterable
    {
        $items = $data instanceof PaginationItemsContainerInterface ? $data->items() : $data;
        if ($items === null) {
            return [];
        }
        if (!is_iterable($items)) {
            throw new ConfigurationException(new Message('pagination.items_must_be_an_array_or_a_collection'));
        }
        return $items;
    }

    /** @return array<array-key, mixed> */
    public function toArray(mixed $data): array
    {
        $items = [];
        $this->append($items, $data);
        return $items;
    }

    /** @param array<array-key, mixed> $target */
    public function append(array &$target, mixed $data): void
    {
        foreach ($this->read($data) as $key => $item) {
            if (!is_int($key) && !is_string($key)) {
                throw new ConfigurationException(new Message('pagination.item_keys_must_be_integers_or_strings'));
            }
            // Числовые строки подчиняются тем же правилам ключей, что и обычный массив PHP.
            $key = is_int($key) ? $key : array_key_first([$key => null]);
            if (is_int($key)) {
                $target[] = $item;
                continue;
            }
            if (array_key_exists($key, $target)) {
                throw HydrationException::invalidValue('pagination_item_key_collision', 'unique string keys', 'duplicate string key');
            }
            $target[$key] = $item;
        }
    }
}
