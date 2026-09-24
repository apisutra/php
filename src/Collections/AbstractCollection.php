<?php

declare(strict_types=1);

namespace ApiSutra\Collections;

use ApiSutra\Localization\Message;
use ArrayIterator;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Support\ArrayPath;
use BackedEnum;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Override;
use Stringable;
use Traversable;
use UnitEnum;

/**
 * Базовая коллекция с общими методами.
 *
 * @template T
 * @implements IteratorAggregate<int|string, T>
 */
abstract readonly class AbstractCollection implements IteratorAggregate, Countable
{
    /**
     * @param array<int|string, T> $items
     */
    public function __construct(
        protected array $items,
    ) {
        $this->validateItems($items);
    }

    /**
     * @param array<int|string, T> $items
     */
    public static function fromArray(array $items): static
    {
        return new static($items);
    }

    public function all(): array
    {
        return $this->items;
    }

    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            foreach ($this->items as $item) {
                return $item;
            }

            return $this->resolveDefault($default);
        }

        foreach ($this->items as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return $this->resolveDefault($default);
    }

    public function get(int|string|null $key, mixed $default = null): mixed
    {
        $key ??= '';

        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        return $this->resolveDefault($default);
    }

    public function has(int|string|array ...$keys): bool
    {
        $keys = $this->normalizeKeys($keys);

        return array_all($keys, fn (int|string $key): bool => array_key_exists($key, $this->items));
    }

    public function hasAny(int|string|array ...$keys): bool
    {
        $keys = $this->normalizeKeys($keys);

        return array_any($keys, fn (int|string $key): bool => array_key_exists($key, $this->items));
    }

    public function only(int|string|array ...$keys): static
    {
        $keys = $this->normalizeKeys($keys);

        if ($keys === []) {
            return new static([]);
        }

        return new static(array_intersect_key($this->items, array_flip($keys)));
    }

    public function except(int|string|array ...$keys): static
    {
        $keys = $this->normalizeKeys($keys);

        if ($keys === []) {
            return new static($this->items);
        }

        return new static(array_diff_key($this->items, array_flip($keys)));
    }

    #[Override]
    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function map(callable $mapper): static
    {
        $items = [];
        foreach ($this->items as $key => $item) {
            $items[$key] = $mapper($item, $key);
        }

        return new static($items);
    }

    public function filter(?callable $filter = null): static
    {
        if ($filter === null) {
            return new static(array_filter($this->items));
        }

        $items = [];
        foreach ($this->items as $key => $item) {
            if ($filter($item, $key)) {
                $items[$key] = $item;
            }
        }

        return new static($items);
    }

    public function mapToArray(callable $mapper): array
    {
        $result = [];
        foreach ($this->items as $key => $item) {
            $result[$key] = $mapper($item, $key);
        }

        return $result;
    }

    /**
     * Сбросить ключи и вернуть коллекцию с числовой индексацией.
     */
    public function values(): static
    {
        return new static(array_values($this->items));
    }

    public function contains(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        if (func_num_args() === 1) {
            if (is_callable($key)) {
                foreach ($this->items as $itemKey => $item) {
                    if ($key($item, $itemKey)) {
                        return true;
                    }
                }

                return false;
            }

            return in_array($key, $this->items);
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->contains(function (mixed $item) use ($key, $operator, $value): bool {
            return $this->compareValue($this->resolveItemValue($item, (string) $key), (string) $operator, $value);
        });
    }

    public function firstWhere(string $key, mixed $operator = null, mixed $value = null): mixed
    {
        if (func_num_args() === 1) {
            $value = true;
            $operator = '=';
        } elseif (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        foreach ($this->items as $item) {
            if ($this->compareValue($this->resolveItemValue($item, $key), (string) $operator, $value)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Возвращает RawCollection, так как значения могут быть любого типа.
     */
    public function pluck(string $value, ?string $key = null): RawCollection
    {
        $result = [];
        foreach ($this->items as $itemKey => $item) {
            $valueItem = $this->resolveItemValue($item, $value);
            if ($key === null) {
                $result[$itemKey] = $valueItem;
                continue;
            }

            $resolvedKey = $this->resolveItemValue($item, $key);
            $result[$this->resolveKey($resolvedKey)] = $valueItem;
        }

        return new RawCollection($result);
    }

    public function keyBy(string|callable $keyOrCallback): static
    {
        $items = [];
        foreach ($this->items as $item) {
            $key = is_callable($keyOrCallback)
                ? $keyOrCallback($item)
                : $this->resolveItemValue($item, $keyOrCallback);
            $items[$this->resolveKey($key)] = $item;
        }

        return new static($items);
    }

    public function sortBy(string|callable $keyOrCallback, int $options = SORT_REGULAR, bool $descending = false): static
    {
        $results = [];
        foreach ($this->items as $key => $item) {
            $results[$key] = is_callable($keyOrCallback)
                ? $keyOrCallback($item)
                : $this->resolveItemValue($item, $keyOrCallback);
        }

        $descending ? arsort($results, $options) : asort($results, $options);

        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        return new static($results);
    }

    public function sortByDesc(string|callable $keyOrCallback, int $options = SORT_REGULAR): static
    {
        return $this->sortBy($keyOrCallback, $options, true);
    }

    public function unique(string|callable|null $key = null, bool $strict = false): static
    {
        if ($key === null && $strict === false) {
            return new static(array_unique($this->items, SORT_REGULAR));
        }

        $items = [];
        $seen = [];

        foreach ($this->items as $itemKey => $item) {
            $value = match (true) {
                $key === null => $item,
                is_callable($key) => $key($item, $itemKey),
                default => $this->resolveItemValue($item, $key),
            };
            if (in_array($value, $seen, $strict)) {
                continue;
            }
            $seen[] = $value;
            $items[$itemKey] = $item;
        }

        return new static($items);
    }

    /**
     * Глубокая сериализация элементов коллекции.
     *
     * @return array<int, mixed>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->items as $key => $item) {
            if (is_object($item) && method_exists($item, 'toArray')) {
                $result[$key] = $item->toArray();
                continue;
            }

            if ($item instanceof JsonSerializable) {
                $result[$key] = $item->jsonSerialize();
                continue;
            }

            $result[$key] = $item;
        }

        return $result;
    }

    /**
     * @param array<int|string, T> $items
     */
    protected function validateItems(array $items): void
    {
        // По умолчанию без валидации.
    }

    private function resolveDefault(mixed $default): mixed
    {
        return is_callable($default) ? $default() : $default;
    }

    private function resolveItemValue(mixed $item, string $key): mixed
    {
        if (str_contains($key, '.')) {
            if (is_array($item)) {
                return ArrayPath::getByPath($item, $key);
            }
            if (is_object($item) && method_exists($item, 'toArray')) {
                return ArrayPath::getByPath($item->toArray(), $key);
            }
        }

        if (is_array($item) && array_key_exists($key, $item)) {
            return $item[$key];
        }

        if (is_object($item) && property_exists($item, $key)) {
            return $item->{$key};
        }

        return null;
    }

    private function resolveKey(mixed $key): int|string
    {
        if ($key instanceof UnitEnum) {
            return $key instanceof BackedEnum ? $key->value : $key->name;
        }

        if (is_int($key) || is_string($key)) {
            return $key;
        }

        if (is_bool($key)) {
            return $key ? 1 : 0;
        }

        if (is_float($key)) {
            return (int) $key;
        }

        if ($key === null) {
            return '';
        }

        if ($key instanceof Stringable) {
            return (string) $key;
        }

        throw new ConfigurationException(new Message('collections.collection_key_must_be_scalar'));
    }

    /**
     * @param array<int, int|string|array<int|string>> $keys
     * @return array<int, int|string>
     */
    private function normalizeKeys(array $keys): array
    {
        if (count($keys) === 1 && is_array($keys[0])) {
            $keys = $keys[0];
        }

        $normalized = [];
        foreach ($keys as $key) {
            $normalized[] = $this->resolveKey($key);
        }

        return $normalized;
    }

    private function compareValue(mixed $left, string $operator, mixed $right): bool
    {
        $operator = strtolower($operator);

        return match ($operator) {
            '=', '==' => $left == $right,
            '===', 'eq' => $left === $right,
            '!=', '<>' => $left != $right,
            '!==', 'ne' => $left !== $right,
            '>', 'gt' => $left > $right,
            '>=', 'gte' => $left >= $right,
            '<', 'lt' => $left < $right,
            '<=', 'lte' => $left <= $right,
            default => $left == $right,
        };
    }
}
