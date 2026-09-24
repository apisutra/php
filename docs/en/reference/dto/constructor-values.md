<!-- languages --> <a href="constructor-values.md">English</a> · <a href="../../../ru/reference/dto/constructor-values.md">Русский</a> <!-- /languages -->
# Values set by the constructor <a id="section-1"></a>

For your own model, put `#[ConstructorValue(allowMissing: false)]` on a separate
property. It is compatible with From/Map and defaults; both declaration forms use
the comparison rules and restrictions below.

`FieldRule::constructorValue(bool $allowMissing = false)` compares the input value
with the value already set by the constructor. This can validate a fixed `type`,
permissions list, or settings dictionary in plain DTOs and `AbstractDto` subclasses.
A readonly property is not written twice. The constructor is called once.

## Setup <a id="section-2"></a>

```php
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use Example\ConstructorValues\RecordDto;

$rules = HydrationRules::create()->withDto(RecordDto::class, DtoRules::create()
    ->field('type', FieldRule::create()->constructorValue())
    ->field('permissions', FieldRule::create()->constructorValue(allowMissing: true))
    ->field('flags', FieldRule::create()->constructorValue(allowMissing: true)));
```

The [RecordDto model](../../../example/constructor-values/src/RecordDto.php) sets
`type = 'record'`, `permissions = ['read', 'write']`, and the `flags` dictionary in its
constructor. The [complete example](../../../example/constructor-values/run.php)
shows success, a conflict, Missing, and a dictionary with reordered keys:

```bash
php docs/example/constructor-values/run.php
```

Connect `$rules` through `ClientConfig(hydration: new HydrationConfig(rules: $rules))`
or `Hydrator::forRules($rules)`. All [hydration entry points](field-rules.md#section-5)
use the same contract, including cache hits and Ready await.

## Lifecycle and states <a id="section-3"></a>

Normal input field processing runs first: from/fallback, required and
forbidExplicitNull, defaults, shape, cast, and native type validation. The constructor
then creates the marked properties' values, which are checked before other fields
are populated. `constructorValue()` is compatible with FieldRule groups but cannot be specified twice.

| State after normal processing | Result |
| --- | --- |
| Field present | Compare with the constructor's result |
| Missing, `allowMissing: false` | `required_field_missing` before the constructor |
| Missing, `allowMissing: true` | Skip comparison; the constructor must set a valid value |
| Missing with a default/provider | Compare the default/provider result |
| Null | Normal nullable check, then comparison with null |

`required()` checks original presence and cannot be bypassed by allowMissing/default.
`forbidExplicitNull()` runs before the Null default. Normal empty-string normalization
still applies; an explicit cast receives the original value in the existing order.

Comparison uses **the same Hydrator's result for an ordinary writable field**.
In Legacy this includes package conversions and final PHP typing. For example,
`float|string ← 5` normally produces string `"5"`, but with `noTransform()` produces
float `5.0`. Strict permits only the [documented conversions](scalars.md).
The check performs no extra coercion to make values match.

## Equality <a id="section-4"></a>

- Scalars and null are compared strictly; `1`, `"1"`, and `1.0` differ after processing.
  `NAN` is not equal even to itself.
- An enum matches only the same case of the same class. Backed enums are converted
  normally; use `noTransform()` or an explicit cast for an existing unit enum.
- List length, indices, value order, and value types matter.
- Dictionary keys and values matter; key insertion order does not.
- Arrays are compared recursively without sorting, serialization, or leaf conversion.
  To transform items, declare a [shape or itemCast](shapes.md) first.

The comparison uses existing **PHP keys**. `[1 => 'b', 0 => 'a']` equals `['a', 'b']`:
index-to-value associations match. `array_is_list()` does not introduce a separate
failure. After JSON decoding, key `"1"` is already an int, while `"01"` remains a string;
empty `{}` and `[]` are indistinguishable after associative decoding. The check does
not reconstruct lost JSON information.

## Boundaries and errors <a id="section-5"></a>

A separate public stored property without hooks or a declaration default is required,
along with an accessible public constructor (which may be inherited). Its name must
not match any constructor parameter, including promoted parameters. Supported types
are scalar, null, concrete enums, array, and nullable/unions of these types. Mixed,
untyped fields, arbitrary objects, interfaces, intersections, receivers, and DTO/variants
shapes at any depth are rejected when compiling the rules.
[Attribute and profile conflicts](field-rules.md) still apply.

Both sides may contain only scalar/null/enum values and recursive arrays of these.
All content is checked before comparison, even beyond the first mismatch. Input
containing an object or resource after transformation produces `invalid_field_type`
before the constructor. Up to 512 nested array containers are allowed, including a
final empty one; deeper or cyclic input produces `hydration_depth_exceeded`.
Reusing a finite branch is allowed. An unsupported/overly deep constructor result
or an uninitialized property produces `ConfigurationException` after one call.

Valid but different values produce `constructor_value_mismatch`. The new checks
point to **the whole property**, without internal array keys or values. SourcePath
preserves the selected fallback; after a cast/provider it reports Boundary. Errors
from earlier transformations retain their detailed paths. See [diagnostics](diagnostics.md).

Ordinary fields are not checked again. Without opt-in, the existing prohibition on
fallback writes to initialized properties remains. The serializer neither runs this
comparison nor excludes the field from requests: To and other outgoing rules still apply.
