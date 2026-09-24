<!-- languages --> <a href="shapes.md">English</a> · <a href="../../../ru/reference/dto/shapes.md">Русский</a> <!-- /languages -->
# Nested objects and lists <a id="section-1"></a>

`#[Shape(new ListShape(ScalarType::Int))]` declares a strict list; NullableShape,
DtoShape, and VariantsShape compose recursive shapes. This is attribute syntax for
the same `ValueShape`, without a separate execution mechanism. Shape together with
Nested or Cast on the same field is rejected.

## Nested DTOs <a id="section-2"></a>
For responses containing nested objects or lists of objects, such as `user.address`
or `order.items[]`.

A single object may be a plain DTO without an ApiSutra base class:

```php
<?php

declare(strict_types=1);

use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Serialization\Hydrator;

final readonly class AddressDto
{
    public function __construct(public string $city)
    {
    }
}

final readonly class UserDto
{
    #[Nested(type: AddressDto::class)]
    public AddressDto $address;
}

$payload = json_decode('{"address":{"city":"Sample"}}', true, flags: JSON_THROW_ON_ERROR);
$user = Hydrator::default()->hydrate($payload, UserDto::class);
echo $user->address->city; // Sample
```

The same declaration works with constructor promotion and through Returns.
For a single property, the concrete class can be inferred from its native type:
`#[Nested] public AddressDto $address`. For an array, type defines the item class.
See the [Nested reference](../attributes/hydration.md#section-12) for object/list
selection, nullable/union rules, and all parameters.

Key-mode example (a case such as `{"person": {...}}`):
```php
use ApiSutra\Enums\DataTransfer\NestedDiscriminatorMode;
use ApiSutra\Enums\DataTransfer\NestedUnknownVariant;

#[Nested(
    discriminatorMode: NestedDiscriminatorMode::Key,
    map: [
        'person' => PersonOwnerDto::class,
        'organization' => OrganizationOwnerDto::class,
    ],
    unknownVariant: NestedUnknownVariant::KeepRaw,
)]
public array $owners = [];
```

If the nested array is already located correctly but each item needs its own
transformation, use `itemCast`:

```php
use ApiSutra\Casts\DataUriBase64FileCast;
use ApiSutra\VO\Files\Base64File;

#[Nested(
    type: Base64File::class,
    itemCast: DataUriBase64FileCast::class,
)]
public array $faces = [];
```

Rule:
- Cast on a property transforms the whole property value.
- `Nested(itemCast: ...)` transforms each array item.

## Shapes, presence, and defaults <a id="section-3"></a>

`ValueShape` provides `int()`, `float()`, `bool()`, `true()`, `false()`, `string()`,
`mixed()`, `scalars(ScalarType ...$types)`, `nullable(ValueShape $shape)`, `dto(string $class)`,
`list(ValueShape $item, ?string $each = null, ?HandlerSpec $itemCast = null, bool $normalizeKeys = false)`.
Lists may be nested. PHPDoc `list<int>` and bare array do not themselves validate items.
A plain native class without `dto()`, Nested, or DtoInterface is not hydrated automatically.

`list()` requires dense keys 0..n−1; a dictionary or sparse array produces `invalid_list_shape`.
`normalizeKeys: true` allows a dictionary and reindexes the result while preserving
original keys in diagnostics and extras. `Traversable` is not a list.
`dto()` accepts an object or an object-shaped array; a nonempty list produces
`invalid_object_shape`. An empty PHP array is allowed in both shapes: after associative
decoding, the differences between `{}`/`[]` and `{"0":...}`/`[...]` cannot be recovered.

Presence, original null, normalization, and defaults follow
[field processing order](defaults.md#section-10).

Within a list, the order is `each` → `itemCast` → item shape. A completed DTO from
`itemCast` or a provider is not hydrated again; `cast(..., result: ...)` validates the
completed value. Attribute Nested still ignores a whole-field cast.

## Nested: single object and collection <a id="section-4"></a>

The hydrator selects the target from the property declaration before parsing data:

| Declaration | Processing |
| --- | --- |
| `#[Nested] AddressDto` or `?AddressDto` | Single DTO of the specified class |
| `#[Nested(type: AddressDto::class)] AddressDto` | Single DTO; a concrete subtype is also allowed |
| Interface or abstract property class | A single object requires a compatible concrete Nested.type |
| `object` | A single object requires a concrete `Nested.type` |
| `array`, `iterable`, `mixed`, no native type | Retains array-of-items processing; type defines the item class |
| Traversable class, including AbstractCollection / AbstractTypedCollection | Collection; type defines the item class, map the variant classes |
| Custom wrapper and a separate incompatible item type | Collection if callable fromArray() exists, or a public constructor takes an array as its first argument with no other required arguments |
| Factory wrapper with map but no type | Collection through fromArray() |
| Union of only classes/interfaces | A single object requires type compatible with at least one branch |

null does not select a union branch. A union with mixed cardinality, such as
`AddressDto|array`, a union without required type, an incompatible class, inaccessible
constructor, or intersection produces `ConfigurationException`. List parameters `each`,
`itemCast`, `discriminator`, and `map` cannot be used with a single object. This validation
runs when processing a found non-null value.

A single DTO accepts an associative array or PHP object. Hydration uses ordinary
field checks and one constructor call, even for an existing PHP DTO. A scalar or
nonempty PHP list produces `unexpected_response_shape` at the property path. An empty
array proceeds to the child DTO's field checks: for example, missing `city` produces
`required_field_missing` at `address.city`. After JSON decode with `assoc=true`, empty
{} and [] are indistinguishable; Nested does not recover that difference.
See the [executable example](shapes.md#section-2).

`Nested.from` overrides the From / Map / property-name path. Nonempty `Nested.fallback`
has priority over `From.fallback`; fallback applies only when a key is absent, while
a found null is retained. `DefaultValue`, constructor defaults, nullability, and empty
typed collections retain the [shared missing/null rules](defaults.md#section-2).

For lists, the order remains `each` → `itemCast` → item hydration or `discriminator` →
collection wrapper. Nested does not enable PHPDoc `list<T>` validation; list shape
and scalar items require explicit checks. An item error contains its sequential
index. A whole-property `#[Cast]` does not run when Nested is present.

A single-object error contains the child field path without a list index.
Use a concrete DTO class and a native type that distinguishes an object from a collection.
