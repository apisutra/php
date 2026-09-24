<!-- languages --> <a href="hydration.md">English</a> · <a href="../../../ru/reference/attributes/hydration.md">Русский</a> <!-- /languages -->
# DTO attributes <a id="section-1"></a>

## Signatures and targets <a id="section-2"></a>

Class names belong to `ApiSutra\Attributes\DataTransfer`.
Signatures show constructor parameters and defaults; target identifies where the
attribute is allowed. Behavior and priorities are covered in the topic links below.

| Attribute | Target | Constructor |
| --- | --- | --- |
| About | PROPERTY | `About(string $title, ?string $description = null, string\|int\|float\|bool\|array\|null $example = null, ?array $examples = null, ?string $format = null, ?string $nullableReason = null, ?string $note = null)` |
| Cast | PROPERTY | `Cast(string $class, mixed ...$args)` |
| <a id="datetimefrom"></a> `DateTimeFrom` | PROPERTY | `DateTimeFrom(?string $format = null, ?string $defaultTimezone = null, ?bool $preserveOffset = null, ?bool $strictMissingTimezone = null, ?bool $strictFormat = null, ?DateTimeInvalidBehavior $invalidBehavior = null)` |
| <a id="datetimeto"></a> `DateTimeTo` | PROPERTY | `DateTimeTo(?string $format = null, ?string $timezone = null)` |
| DefaultValue | PROPERTY | `DefaultValue(int\|float\|string\|bool\|array\|null $value = null, ?string $provider = null, array $when = [ValueState::Missing])` |
| <a id="dtohydrate"></a> DtoHydrate | CLASS | `DtoHydrate(?NamingStrategy $namingStrategy = null, ?string $dateTimeFormat = null, ?string $dateTimeDefaultTimezone = null, ?bool $dateTimePreserveOffset = null, ?bool $dateTimeStrictMissingTimezone = null, ?bool $dateTimeStrictFormat = null, ?DateTimeInvalidBehavior $dateTimeInvalidBehavior = null, ?EmptyStringBehavior $emptyStringBehavior = null, ?ScalarPolicy $scalars = null)` |
| <a id="dtohydrationprofile"></a> DtoHydrationProfile | CLASS | `DtoHydrationProfile(string $class)` |
| <a id="dtoserializationprofile"></a> DtoSerializationProfile | CLASS | `DtoSerializationProfile(string $class)` |
| <a id="dtoserialize"></a> DtoSerialize | CLASS | `DtoSerialize(?EnumOutput $enumOutput = null, ?bool $strictEnums = null, ?NamingStrategy $namingStrategy = null, ?bool $serializeNulls = null, ?string $dateTimeFormat = null, ?string $dateTimeTimezone = null)` |
| EmptyStringAsNull | PROPERTY | `EmptyStringAsNull(bool $blank = false)` |
| From | PROPERTY | `From(string $name, array $fallback = [])` |
| Label | PROPERTY | `Label(string $name)` |
| Map | PROPERTY | `Map(string $name)` |
| Nested | PROPERTY | `Nested(?string $type = null, ?string $itemCast = null, ?string $from = null, array $fallback = [], ?string $each = null, ?string $discriminator = null, ?array $map = null, NestedDiscriminatorMode $discriminatorMode = NestedDiscriminatorMode::Value, NestedUnknownVariant $unknownVariant = NestedUnknownVariant::KeepRaw)` |
| To | PROPERTY | `To(string $name)` |
| Validate | PROPERTY | `Validate(string $rules, ?string $message = null)` |
| Extras | PROPERTY | `Extras()` |
| RequiredInput | PROPERTY | `RequiredInput()` |
| ForbidExplicitNull | PROPERTY | `ForbidExplicitNull()` |
| ConstructorValue | PROPERTY | `ConstructorValue(bool $allowMissing = false)` |
| Shape | PROPERTY | `Shape(ScalarType\|ShapeSpec $value)` |

[New declarations](../dto/declarations.md) cover input remainder, presence/null,
constructor comparison, and recursive shapes. Shared policy:
[HydrationConfig](../dto/configuration.md).

These attributes define DTO mapping, profiles, and validation. The table gives each target.

## When to use them <a id="section-3"></a>
- **Map** — the same external key for hydration and serialization.
- **From** — an API input field has another name or is nested deeper.
- **To** — the outgoing key differs from the DTO property name.
- **Cast** — type conversion (dates, enums, numbers).
- **About** — business meaning of a response DTO field for documentation and tooling.
- **`DateTimeFrom` / `DateTimeTo`** — ordinary property-level date overrides without a low-level Cast.
- **EmptyStringAsNull** — treat a provider's empty string as null.
- **Nested** — a field contains a nested object or list of objects.
- **DefaultValue** — a default or provider validation of a present value.
- **Validate/Label** — local DTO validation with readable errors.

## Cast <a id="section-4"></a>
**Parameters:**
- `class: class-string<HydrationCastInterface|SerializationCastInterface>`
- `...args` — constructor arguments.

Example:
```php
#[Cast(DateTimeCast::class)]
public DateTimeImmutable $createdAt;
```

Cast takes priority over built-in automatic casting. Ordinary int/float/bool/string
cases often do not need an explicit Cast if the provider sends a safely convertible value.

## About <a id="section-5"></a>
About describes a DTO field's business meaning for documentation, analysis, and export tooling.

Minimal form:
```php
#[About(title: 'ИНН физического лица')]
public ?string $inn = null;
```

Extended form:
```php
#[About(
    title: 'Данные паспорта',
    description: 'Структурированные паспортные данные, если провайдер вернул их отдельным объектом.',
    example: [
        'series' => '1234',
        'number' => '567890',
        'issued_at' => '2020-01-15',
    ],
    format: 'object',
)]
public ?array $passport = null;
```

Fields:
- title — required short human-readable field name.
- description — detailed business description.
- `example` — one typical value; may be a scalar, JSON string, or array.
- `examples` — several `examples`, each following the same rules as `example`.
- format — human-readable format hint when the PHP type is insufficient.
- `nullableReason` — why the field may be null.
- note — an additional qualification or important detail.

About does not duplicate the technical field schema. Type, nullability, enum,
nested DTO, collection shape, external name, and casts must be derived from PHP
types and attributes such as From, Map, Nested, and Cast.

Do not guess optional fields. Values must be supported by an explicit provider
contract, documentation, user description, or verified analysis. If information is
insufficient, leave the field null. This is especially important for AI-assisted DTO annotation.

For `nullableReason`, the rule is strict: fill it only when the reason is explicitly
known. If unknown or merely assumed, leave null.

Both forms of JSON `examples` are valid:
- array — structured `example`; an exporter may render it as JSON.
- string — a literal `example` value; it must not be parsed automatically as JSON.

## DateTimeFrom / DateTimeTo <a id="section-6"></a>
Use these for standard date-time DX:

```php
#[DateTimeFrom(format: DATE_ATOM, defaultTimezone: 'UTC')]
#[DateTimeTo(format: DATE_ATOM, timezone: 'UTC')]
public DateTimeImmutable $createdAt;
```

- `DateTimeFrom` affects only hydration.
- `DateTimeTo` affects DTO property serialization, including DX and wire; requestDateTime
  defines the format of a field on the Request itself.
- Cast still takes precedence and remains an escape hatch.

## EmptyStringAsNull <a id="section-7"></a>
Property-level hydration normalization:

```php
#[EmptyStringAsNull]
public ?string $middleName = null;
```

Option:
- `blank: true` — treat whitespace-only strings as null in addition to ''.

See the [DTO guide](../../guides/dto/attribute-models.md) for behavior and recommendations.

## Map <a id="section-8"></a>
**Parameters:**
- `name: string` — external key name.

Example:
```php
#[Map('user_id')]
public int $userId;
```

Map works in both directions: hydration from `user_id` and serialization to `user_id`.

Priorities:
- Hydration: From -> Map -> `NamingStrategy`.
- Serialization: To -> Map -> `NamingStrategy`.

## From <a id="section-9"></a>
**Parameters:**
- `name: string` — response path/key.
- `fallback: array = []` — alternative keys.

Example:
```php
#[From('data.id', fallback: ['id'])]
public int $id;
```

## To <a id="section-10"></a>
**Parameters:**
- `name: string` — serialized key name.

## Choosing an attribute <a id="section-11"></a>
| Scenario | Recommendation |
|---|---|
| Same key both ways | Map |
| Hydration only, requiring dot paths/fallback | From |
| Serialization only | To |
| Different input and output keys | From + To |
| Base rule for an entire DTO | NamingStrategy |

## Nested <a id="section-12"></a>
**Parameters:**
- `type?: string` — concrete class for a single DTO or collection item.
- `itemCast?: string` — cast for each array item before further hydration/type processing.
- `from?: string` — response path.
- `fallback: array = []`
- `each?: string` — for collections.
- `discriminator?: string` — discriminator path for `Value`, or wrapper object path for `Key`.
- `map?: ?array` — discriminator -> DTO class-string mapping.
- `discriminatorMode: NestedDiscriminatorMode = Value` — discriminator source (`Value` or `Key`).
- `unknownVariant: NestedUnknownVariant = KeepRaw` — unknown variant policy (`KeepRaw`, `Skip`, or `Error`).

`itemCast` is useful when a nested array is structurally correct but every item needs
additional transformation, such as `list<data-uri-string> -> list<Base64File>`.
The `itemCast` class must implement `HydrationCastInterface` and be constructible without arguments.

See [DTO shapes](../dto/shapes.md#section-4) for single objects, lists, typed collections,
and Cast/Nested ordering.

## DefaultValue <a id="section-13"></a>
**Parameters:**
- `value?: int|float|string|bool|array|null`
- `provider?: string` — `DefaultValueProviderInterface` class constructed without arguments.
- `when: array = [ValueState::Missing]`

Without provider, value is used. Use a provider when the value depends on context or
requires logic. A non-null value together with provider is a configuration error.

Providers apply to Missing/Null/Present. The defaults contract contains
[ordering and a present-value validation example](../dto/defaults.md#section-9).
See [collections](../dto/collections.md) for the automatic missing typed collection
value and its priority relative to DefaultValue.

## Label <a id="section-14"></a>
**Parameters:**
- `name: string` — human-readable field name.

## Validate <a id="section-15"></a>
**Parameters:**
- `rules: string` — validation rules.
- `message?: string` — custom `message`.

Without `message`, the validator's default `message` is used.

Example:
```php
#[Label('Email')]
#[Validate('required|email', message: 'Некорректный email')]
public string $email;
```

## Combining with external rules <a id="section-16"></a>

`FieldRule` conflicts with From, Map, Nested, Cast, `DateTimeFrom`, EmptyStringAsNull,
and DefaultValue on the same property. Other fields' attributes continue to work.
Casts/providers receive the current rule set through HydrationContext even with
attribute registration, including `Nested(itemCast:)`. See the
[external rules reference](../dto/field-rules.md#section-3) for API and priorities.
