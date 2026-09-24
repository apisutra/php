<!-- languages --> <a href="defaults.md">English</a> · <a href="../../../ru/reference/dto/defaults.md">Русский</a> <!-- /languages -->
# Presence, null, and defaults <a id="section-1"></a>

`#[RequiredInput]` checks original presence before defaults/providers;
`#[ForbidExplicitNull]` forbids a found null while allowing a missing field.
Both work with From/Map and `DefaultValue`; a null primary does not trigger fallback.

## Required fields and hydration errors <a id="section-2"></a>

Without external rules, requiredness follows PHP types and DTO declarations.
The checks below run after `From`/fallback, empty-string normalization,
`DefaultValue`, and automatic typed collection defaults.
External `FieldRule::required()` additionally requires key presence **before** defaults;
`forbidExplicitNull()` forbids original null even for nullable types.
See [shapes and presence](shapes.md#section-3).

| Declaration and input | Result |
| --- | --- |
| Constructor parameter with a default, field missing | Constructor default value. |
| Constructor parameter without a default, field missing, including `?T` | `required_field_missing`. Nullable allows null but does not make the argument optional. |
| Nullable public property outside the constructor chain, no default, field missing | Null. |
| Non-nullable public property outside the constructor chain, no default, field missing | `required_field_missing`. |
| Explicit null found | Accepted by nullable/mixed; constructor defaults and fallback do not replace it. An applicable DefaultValue for Null may replace it. |
| Null after transformation, type does not allow null | `null_not_allowed`. |
| Value does not fit the type after allowed casts | `invalid_field_type`; a scalar instead of a nested DTO gives `unexpected_response_shape`. |

The constructor runs once; its parameter type is validated even if it transforms
the value for a property of another type. Legacy retains scalar conversions and
union branch selection. External rules may enable [Strict](scalars.md#section-3),
including validation of cast results.

Direct `DTO::from()` throws `HydrationException` with `reason`, `path`, `expected`,
and `actual`. In a request, this becomes `hydration_error` with the HTTP response
preserved. The path contains DTO property names and sequential indices (`items[1].id`),
with an unwrap prefix when present. It is not necessarily the literal `From` path in the response.

Invalid class declarations, casts, `Nested` maps, time zones, and readonly initialization
conflicts remain configuration errors. Arbitrary failures in custom constructors or
computed are not classified as a specific field's error.

See [JsonCast](../serialization/casts.md#section-6) for strict nested JSON and
[diagnostics](diagnostics.md#section-4) for access to the original response.

## Cast, DateTimeFrom/To, and DefaultValue <a id="section-3"></a>
Use these to transform a type or supply a default for missing/NULL input.
```php
use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use ApiSutra\Attributes\DataTransfer\DateTimeTo;
use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Casts\DateTimeCast;

#[DateTimeFrom(format: DATE_ATOM, defaultTimezone: 'UTC')]
#[DateTimeTo(format: DATE_ATOM, timezone: 'UTC')]
public DateTimeImmutable $createdAt;

#[Cast(DateTimeCast::class, format: 'Y-m-d')]
public DateTimeImmutable $legacyCreatedAt;

#[DefaultValue('unknown')]
public string $status;
```

## Empty-string normalization <a id="section-4"></a>
By default, `apisutra` does not treat `''` as equivalent to `null`:

- `Missing` — key absent
- `Null` — key present with value `null`
- `Present` — value found, including `''`

If a provider uses an empty string for “no value”, explicitly enable that behavior.

### On an individual property <a id="section-5"></a>
```php
use ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;

#[EmptyStringAsNull]
public ?string $middleName = null;
```

To include blank strings as well:

```php
#[EmptyStringAsNull(blank: true)]
public ?string $comment = null;
```

### Centrally through a hydration profile <a id="section-6"></a>
```php
use ApiSutra\Config\DtoHydrationPolicy;
use ApiSutra\Contracts\Interfaces\Serialization\DtoHydrationProfileInterface;
use ApiSutra\Enums\DataTransfer\EmptyStringBehavior;

final readonly class ProviderDtoHydrationProfile implements DtoHydrationProfileInterface
{
    public function policy(): DtoHydrationPolicy
    {
        return new DtoHydrationPolicy(
            emptyStringBehavior: EmptyStringBehavior::NullIfEmpty,
        );
    }

    public function casts(): array
    {
        return [];
    }
}
```

### Priorities <a id="section-7"></a>

For an attribute-based model without external rules:

1. `#[Cast(...)]`
2. `#[EmptyStringAsNull(...)]`
3. Hydration profile-level `emptyStringBehavior`
4. Default `Keep` behavior

External rules configure normalization through `RulePolicy::emptyString`;
see [priorities and processing order](shapes.md#section-3).

### Practical rules <a id="section-8"></a>
- Core default behavior is unchanged: `''` remains `''`.
- `EmptyStringAsNull` is mainly useful for `?string`.
- If `'' -> null` is enabled on a non-nullable field and its value remains `null`
  after `DefaultValue`, the hydrator throws `HydrationException` with `reason` `null_not_allowed`.
- `DefaultValue(... when: [Null])` works with this normalization: after `'' -> null`,
  it behaves as it would for ordinary `null`.

`DefaultValue` can supply a value based on `ValueState`:
- `Missing` — key absent
- `Null` — key present with null
- `Present` — value found

Use a provider when you need context (such as request/meta/traceId) or more complex logic:
```php
use ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Serialization\Context\HydrationContext;

final class StatusDefault implements DefaultValueProviderInterface
{
    public function resolve(mixed $value, ValueState $state, array $source, HydrationContext $context): mixed
    {
        return 'unknown';
    }
}
```

For a simple value, use `#[DefaultValue]`.

A provider with `when: [ValueState::Present]` can validate a found value and return
it before Nested. To forbid null and validate list shape together, use one provider
with `when: [ValueState::Null, ValueState::Present]`. See the
[DefaultValue reference](../attributes/hydration.md#section-13) for a working example and normalization order.

For typed collections, `#[DefaultValue(value: [], when: [ValueState::Missing])]`
is usually unnecessary: the core handles `missing -> empty collection`.
Keep an explicit `DefaultValue` to:
- Handle `null`.
- Override the fallback.
- Make the DTO contract explicit.

## Providers for present values <a id="section-9"></a>

A provider can compute a replacement, return a validated value, or reject it.
`when: [ValueState::Present]` invokes it for a found non-null value before Nested and
casts. `DefaultValue` is non-repeatable: use a single provider with
`when: [ValueState::Null, ValueState::Present]` to forbid null and check shape.

```php
<?php

declare(strict_types=1);

use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Context\HydrationContext;

final readonly class ListShapeProvider implements DefaultValueProviderInterface
{
    /** @param array<string, mixed> $source */
    public function resolve(mixed $value, ValueState $state, array $source, HydrationContext $context): mixed
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw HydrationException::invalidValue('invalid_list_shape', 'list', get_debug_type($value));
        }

        return $value;
    }
}

final readonly class KnownRecordDto
{
    public function __construct(public string $city)
    {
    }
}

final readonly class RecordsDto
{
    /** @param list<mixed>|null $items */
    public function __construct(
        #[DefaultValue(provider: ListShapeProvider::class, when: [ValueState::Null, ValueState::Present])]
        #[Nested(discriminator: 'kind', map: ['known' => KnownRecordDto::class])]
        public ?array $items = null,
    ) {
    }
}

$dto = Hydrator::default()->hydrate([
    'items' => [['kind' => 'known', 'city' => 'Sample'], ['kind' => 'future', 'enabled' => false]],
], RecordsDto::class);
```

Missing retains the constructor's null default; an empty list is accepted.
Explicit null, associative arrays, and sparse indices are rejected at path `items`.
After shape validation, Nested constructs a known DTO and preserves unknown variants
with default `KeepRaw`. An invalid `city` type in a known item produces `items[0].city`.

State processing order:

1. Value lookup and fallback.
2. Empty-string normalization, if enabled by policy or `EmptyStringAsNull`.
3. Applicable `DefaultValue` with current `$value` and `$state`; `$source` contains current DTO `data`.
4. Built-in missing typed collection fallback, then Nested or casts and type validation.

`Keep` is the default: the provider sees the original empty string and Present.
When an empty string is converted to null, it sees Null. A property with `#[Cast]`
skips empty-string normalization even with `EmptyStringAsNull`; the provider still runs before the cast.

To reject input, use structured `HydrationException::invalidValue()`: the hydrator
adds the current field name, parents, indices, and `Returns.unwrap`. The provider
supplies only a local path suffix if needed; `reason`, `expected`, `actual`, and the
`previous` chain are retained. Do not include response values in error text.
`ConfigurationException` remains a configuration error. Exceptions without `reason`
do not receive automatic path completion.

A provider error on field `count` inside `child` has path `child.count`,
or `data.child.count` after unwrap `data`.

## External field rules <a id="section-10"></a>

Field order: lookup → required/null/shape → empty-string normalization → default →
Missing handling → transformation → native type validation → one constructor call.
Missing/null are not checked as containers. `required()` runs before any default,
including the automatic empty typed collection. `forbidExplicitNull()` runs before
normalization and does not forbid null produced from an empty string by NullIfEmpty.
`Keep` is the default; a whole-field cast skips empty-string normalization.

`DefaultSpec::value($value, ValueState ...$when)` and
`DefaultSpec::provider(HandlerSpec $provider, ValueState ...$when)` apply to Missing
when no when is supplied. Specify Present to validate a found value, or Null and
Present for both states. The provider runs on every application and can create an
independent object. Literal values and `HandlerSpec::args` accept scalar, null,
unit/backed enum cases, and arrays of them at any depth; other objects and closures
inside arrays are forbidden. Enum cases retain identity.
[Constructor defaults](lifecycle.md#section-3) are still evaluated by PHP only when the argument is omitted.

For [constructorValue](constructor-values.md), defaults/providers run before comparison
with the constructor. `allowMissing` permits absence but does not cancel required.
