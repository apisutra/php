<!-- languages --> <a href="collections.md">English</a> · <a href="../../../ru/reference/dto/collections.md">Русский</a> <!-- /languages -->
# Typed collections <a id="section-1"></a>

This guide describes ApiSutra typed collections and their DTO integration.

## Purpose <a id="section-2"></a>
Typed collections provide a predictable contract:
- IDEs and static analysis know the exact item type.
- Fewer errors when working with responses.
- Convenient `first()/count()/isEmpty()` methods.

## AbstractTypedCollection <a id="section-3"></a>
The base typed container requires an item class and performs a runtime guard.

```php
use ApiSutra\Collections\AbstractTypedCollection;

final readonly class OrderItemCollection extends AbstractTypedCollection
{
    protected static function itemClass(): string
    {
        return OrderItemDto::class;
    }
}
```

### Available methods <a id="section-4"></a>
- `all(): array<int|string, T>`
- `first(callback|null, default|null): ?T|mixed`
- `get(key, default?): T|mixed`
- `has(keys): bool`, `hasAny(keys): bool`
- `only(keys): static`, `except(keys): static`
- `count(): int`, `isEmpty(): bool`
- `map(callable(T): T): static`
- `filter(callback|null): static` (removes falsy values without a callback)
- `contains(value|callback|key, operator, value): bool`
- `firstWhere(key, operator?, value?): ?T`
- `pluck(value, key?): RawCollection`
- `keyBy(key|callable): static`
- `sortBy(key|callable, options?, desc=false): static`
- `sortByDesc(key|callable, options?): static`
- `unique(key|callable|null, strict=false): static`
- `values(): static`
- `mapToArray(callable(T): mixed): array`
- `fromArray(array $items): static`
- `toArray(): array`

### Guard <a id="section-5"></a>
The constructor checks that every item is `instanceof itemClass()`.
A guard violation throws `ConfigurationException`.

### One collection for similar DTOs <a id="section-6"></a>
If several DTOs are very similar (for example, rights and encumbrance records) and
you want one collection class, use a **shared abstraction**: an interface or base DTO.

```php
interface RightRecordInterface {}

final readonly class RegistrationRecordDto implements RightRecordInterface {}
final readonly class EncumbranceRecordDto implements RightRecordInterface {}

final readonly class RightRecordCollection extends AbstractTypedCollection
{
    protected static function itemClass(): string
    {
        return RightRecordInterface::class;
    }
}
```

Use this when:
- Items share a contract and behavior.
- You need one collection type in different places.
- Type safety and a guard matter.

Do **not** use this when:
- Items have no shared contract.
- Types depend on context (prefer `RawCollection`).

## DTO integration <a id="section-7"></a>
Use `#[Nested]` for a DTO to receive a collection instead of an array:

```php
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\DataTransfer\AbstractResponseDto;

final readonly class OrderDto extends AbstractResponseDto
{
    public function __construct(
        #[Nested(type: OrderItemDto::class)]
        public OrderItemCollection $items,
    ) {}
}
```

## RawCollection (escape hatch) <a id="section-8"></a>
Use `RawCollection` for a container without type validation.
It supports the same basic methods but does not run a guard.

Polymorphic arrays (`#[Nested(discriminator: ..., map: ...)]`) follow the same rules
as ordinary `array`: `discriminatorMode` (`Value`/`Key`) and
`unknownVariant` (`KeepRaw`/`Skip`/`Error`) are supported.

## Serialization <a id="section-9"></a>
`toArray()` serializes deeply:
- DTO → `toArray()`
- `JsonSerializable` → `jsonSerialize()`
- Everything else → unchanged

## Priorities <a id="section-10"></a>
Typed collections do not affect `#[Cast]` or registry casts.
They only define the list's container type.

## Keys and indexing <a id="section-11"></a>
`map()` and `filter()` **preserve keys**, as in Laravel.
Use `values()` to reindex.

## Automatic empty collection <a id="section-12"></a>

For a **non-nullable typed collection**, the core automatically supplies an empty
collection when the field is **missing** (`ValueState::Missing`).

This rule:
- Applies only to `AbstractTypedCollection`.
- Applies only to **missing**.
- Does not apply to `null`.
- Does not apply to a nullable property (`?OrderItemCollection`).
- Does not override `#[DefaultValue]` when that `DefaultValue` covers `Missing`.

For `null -> []`, keep explicit
`#[DefaultValue(value: [], when: [ValueState::Null])]`
or a combined `Missing + Null` declaration.

If `DefaultValue` covers only `Null`, the built-in `Missing` fallback still works.
