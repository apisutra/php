<!-- languages --> <a href="declarations.md">English</a> · <a href="../../../ru/reference/dto/declarations.md">Русский</a> <!-- /languages -->
# DTO attribute declarations <a id="section-1"></a>

When the SDK owns its models, declare field processing on the model itself.
Plain classes, readonly classes, and ApiSutra DTOs are supported; no withDto()
registry is needed. [Shared HydrationConfig](configuration.md) sets policy for the entire traversal.

```php
use ApiSutra\Attributes\DataTransfer\ConstructorValue;
use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\Attributes\DataTransfer\ForbidExplicitNull;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\RequiredInput;
use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Rules\ScalarType;
use ApiSutra\Serialization\Shapes\ListShape;

final readonly class Record
{
    #[ConstructorValue]
    public string $kind;

    public function __construct(
        #[From('record_id')] #[RequiredInput] public int $id,
        #[Shape(new ListShape(new ListShape(ScalarType::Int)))] public array $rows = [],
        #[ForbidExplicitNull] public ?int $stock = null,
        #[Extras] public array $_extra = [],
    ) {
        $this->kind = 'record';
    }
}
```

With shared Strict policy, `{kind:"record",record_id:7,rows:[[1,2],[]],future:false}`
produces id=7, the original rows, stock=null, and `_extra=['future'=>false]`.
The constructor runs once. `record_id:"7"`, an incorrect kind, a present stock:null,
or a string item in rows produces an error with a DTO path and original JSON Pointer.
The complete executable [showcase](../../guides/dto/showcase.md) demonstrates input, result, and request.

| Attribute | External rule equivalent | Full contract |
| --- | --- | --- |
| Extras | DtoRules::extras with the property name | [Input remainder and collisions](extras.md) |
| ConstructorValue(allowMissing: false) | FieldRule::constructorValue | [Constructor value comparison](constructor-values.md) |
| RequiredInput | FieldRule::required | [Presence before defaults](defaults.md) |
| ForbidExplicitNull | FieldRule::forbidExplicitNull | [Missing and null](defaults.md) |
| Shape | FieldRule::shape | [Value shapes](shapes.md) |

All five attributes are non-repeatable property attributes. Promotion is read as a
property declaration, without executing it again on the parameter. A receiver may
have any name; it must be declared explicitly, not added dynamically. An input key
`_extra` stays inside the remainder and does not replace the receiver. Client requests
exclude the receiver from manually created and nested models too. A toArray() dump retains it.

## Constructor-based shapes <a id="section-2"></a>

Shape accepts `ScalarType|ShapeSpec`; nodes live in `Serialization\Shapes`.
They translate to the existing ValueShape, with the same processing and errors.

| Node | Constructor |
| --- | --- |
| ScalarType | Int, Float, Bool, String — one scalar type |
| ListShape | `ListShape(ScalarType\|ShapeSpec $item, ?string $each = null, ?HandlerSpec $itemCast = null, bool $normalizeKeys = false)` |
| NullableShape | `NullableShape(ScalarType\|ShapeSpec $value)` |
| DtoShape | `DtoShape(string $class, bool $emptyListAsObject = false)` — including a plain class |
| VariantsShape | `VariantsShape(string $discriminator, array $map, NestedDiscriminatorMode $mode = NestedDiscriminatorMode::Value, NestedUnknownVariant $unknown = NestedUnknownVariant::KeepRaw)` |

Nullability is declared separately for the list and its items. For example,
`new NullableShape(new ListShape(new NullableShape(ScalarType::Int)))` allows null,
an empty list, and null items. Dictionaries and sparse lists are rejected; only
explicit normalizeKeys allows key normalization. each/itemCast belong to a specific
ListShape and can therefore also be set at an inner list level.

Nested new expressions and enums are allowed in attributes; ValueShape factories
cannot be called there. Third-party ShapeSpec implementations are rejected, and a
custom compiler is not executed. Mixed, scalar literals true/false, and scalar unions
in the Shape language are available only through external ValueShape; native PHP
mixed/unions/literals continue to work as before.

## Combinations <a id="section-3"></a>

From/Map are compatible with all checks. RequiredInput runs before DefaultValue;
ForbidExplicitNull runs before the provider for Null. ConstructorValue(allowMissing: true)
does not bypass RequiredInput either.

Shape + Nested or Cast on the same field produces ConfigurationException. Use itemCast
for item conversion; external FieldRule::cast retains a separate result shape.
Existing Nested does not become a strict list or change old attribute combinations.

An external FieldRule conflicts with an input attribute on the same field even if
they agree. Neighboring fields may use different approaches. Two receivers, external
extras together with Extras, or an input/output attribute on a receiver are also
rejected. See [configuration rules](field-rules.md) for receiver and profile restrictions.

Adding an attribute does not switch raw items-only pagination to DTOs: explicit
HydrationConfig is required. See [why and how to enable typing](../execution/pagination.md).
