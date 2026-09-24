<!-- languages --> <a href="casts.md">English</a> · <a href="../../../ru/reference/serialization/casts.md">Русский</a> <!-- /languages -->
# Casts: hydration and serialization <a id="section-1"></a>

## Casts <a id="section-2"></a>

Object arguments in `#[Cast]` are isolated between operations regardless of the
environment; see [attribute evaluation rules](../dto/lifecycle.md#section-2).
Existing instances explicitly passed through `casts` retain their identity.

`casts` defines PHP-type rules for serializing request properties:

```php
use ApiSutra\Casts\DateTimeCast;
use ApiSutra\Config\ClientConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    casts: [
        DateTimeImmutable::class => DateTimeCast::class,
    ],
);
```

The key is a declared PHP type, such as `DateTimeImmutable::class` or `'string'`.
Relative `self`/`parent` types are first resolved to fully qualified classes
[in the property's declaring scope](../dto/models.md#section-4).
Response hydration through Returns does not use this setting: it needs a
`DtoHydrationProfile`, a property `#[Cast]`, or external rule-set `casts`.
See [cast sources and priorities](casts.md#section-9).

Casts transform values during request serialization and DTO hydration.
These operations use different registration sources.

## Application priority <a id="section-3"></a>

Without external rules, hydration of a non-null property without Nested uses:

1. Property `#[Cast]`.
2. Type cast from `DtoHydrationProfile::casts()`.
3. Safe scalar conversion and built-in DateTime/enum/DTO rules.

Union type selection considers the input value. With Nested, the hydrator uses its
processing instead of a whole-property Cast; set a per-item cast with
[Nested.itemCast](../attributes/hydration.md#section-12). A `DefaultValue` provider runs
before this processing; a remaining null is not passed to the cast and is checked
against the field's nullability.

For request property serialization, `#[Cast]` takes priority over the client registry
and built-in conversions. Nested DTOs follow their own
[DTO and wire body rules](dto-output.md#section-2).

## Built-in casts <a id="section-4"></a>
- `BooleanCast`
- `IntegerCast`
- `FloatCast`
- `DateTimeCast`
- `EnumCast`
- `JsonCast`

### IntegerCast range and nested JSON <a id="section-5"></a>

`IntegerCast` checks overflow before conversion. On hydration, a number outside PHP's
int range causes `HydrationException`; on serialization, `SerializationException`
(`serialization_error` before HTTP in a request). Null stays null and ordinary
conversions are retained. Integer strings, including leading zeros, are compared
without an intermediate float. Floats and fractional/exponential strings are checked
as floats; rounding near range boundaries may cause rejection. For an exact integer,
pass an integer string or int; keep a large identifier as string.

### JsonCast <a id="section-6"></a>

`JsonCast::hydrate()` strictly parses a string as JSON: malformed JSON, an empty string,
whitespace, invalid UTF-8, and depth exceeding 512 produce `HydrationException` with
reason `invalid_json` and the original `JsonException` in the `previous` chain. A DTO adds
the field path; a request produces `hydration_error`. A nullable field does not hide
malformed JSON behind null either. No new client setting is required.

JSON null, false, 0, strings, arrays, and objects retain their previous results;
objects decode to associative arrays. PHP null and non-string values pass through
without JSON decoding. The result is then checked against the field type: JSON false
is valid JSON but gives `invalid_field_type` for an array field. Large integer JSON
literals remain strings. Serialization stays strict.
See the [shared number policy](../dto/scalars.md#section-4).

Handle malformed nested JSON as an error, or declare a custom cast with an explicit
fallback policy.

### Dates and enums <a id="section-7"></a>

A rejected date in `DateTimeInvalidBehavior::Throw` mode produces `HydrationException`
with reason `invalid_datetime`; expected contains the declared format. The original
string is not included in the message. Null mode retains null; if the field forbids
it, subsequent validation gives `null_not_allowed`. An invalid time zone remains a
configuration error. The same distinction applies to explicit `DateTimeCast`.

`EnumCast` returns null for an unknown backed enum value. An unsuitable input type
produces `invalid_field_type`; hydrating a non-backed enum is a configuration error.
See [shared DTO field rules](../dto/defaults.md#section-2).

## Booleans in text fields <a id="section-8"></a>

`BooleanCast` without arguments retains its existing conversion to PHP bool, including
hydration. An optional format changes only outgoing serialization:

```php
use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Casts\BooleanCast;
use ApiSutra\Enums\Serialization\BooleanFormat;

#[Query]
#[Cast(BooleanCast::class, BooleanFormat::Literal)]
public bool $enabled = false;
```

This produces `enabled=false` regardless of the client's `textBooleanFormat`.
Numeric produces strings 1/0. With an explicit format, serialize accepts bool or null;
other values cause `SerializationException`. Hydration is unchanged. An explicit text
cast on a JSON/DTO field also returns a string: use it only when required by the API.
For a shared query/multipart rule, the [client setting](request-parts.md#section-4) is sufficient.

## Registering casts <a id="section-9"></a>

`ClientConfig.casts` configures serialization of **outgoing request** properties:

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Casts\DateTimeCast;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    casts: [
        DateTimeImmutable::class => DateTimeCast::class,
    ],
);
```

This setting does not register a cast for response hydration through Returns.
For incoming DTOs, use a profile, a property Cast, or [external rules](../dto/scalars.md#section-3).

| Source | Participates in DTO hydration | Purpose |
| --- | --- | --- |
| `new Hydrator(casts: $registry)` | No | Argument retained for compatibility; contents are not applied to DTOs |
| ClientConfig.casts | No | Request property serialization casts |
| CastRegistry::global() | No, including Dto::from() / Hydrator::default() | Shared registry for explicit caller use |
| ExtensionContext::registerCast() / ExtensionRegistry::registerCast() | No | Registers in the extension registry; this is the request registry in a standard SDK client |
| DtoHydrationProfile::casts() | Yes | Type rules for DTOs bound to the profile |
| Property Cast | Yes, unless Nested handles the property | Explicit value transformation |
| Class or rule-set RulePolicy::casts | Yes | PHP-type rules in HydrationRules |
| FieldRule::cast() / ValueShape::list(itemCast:) | Yes | Field/item transformation with HandlerSpec |

Client and global registries do not replace a DTO profile. DTO serialization profiles
are also configured separately from hydration profiles. See the
[DTO guide](../../guides/dto/attribute-models.md) and [ClientConfig.casts](casts.md#section-2).

## The #[Cast] attribute <a id="section-10"></a>
```php
use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Casts\DateTimeCast;

#[Cast(DateTimeCast::class, format: DATE_ATOM)]
public DateTimeImmutable $createdAt;
```

## Behavior examples <a id="section-11"></a>
- **Safe scalar auto-cast:** with `ScalarPolicy::Legacy` (default), `"12"` may become 12
  for int, `"12.5"` → `12.5` for float, and `"true"` → true for bool. Strict rejects these
  strings; see the [Strict type table](../dto/scalars.md#section-3).
- **Enum:** `DtoSerializationProfile` defines DX / `toArray()` format;
  `wireBodySerializationPolicy` defines wire body format; request-level client config
  defines query/header/path format.
- **DateTime:** standard DX uses `DtoHydrationProfile` / `DtoHydrate` / `DateTimeFrom` and
  `DtoSerializationProfile` / `DtoSerialize` / `DateTimeTo`. `#[Cast(DateTimeCast::class, ...)]`
  remains a low-level escape hatch. Default `DateTimeCast::serialize()` accepts only `DateTimeInterface`.
- **Json:** `JsonCast` serializes an array/object into a JSON string.

## Where casts apply <a id="section-12"></a>
- **Request serialization** — request properties
- **DTO hydration** — DTO properties

## External rule-set casts and scope <a id="section-13"></a>

`HandlerSpec` in [HydrationRules](../dto/scope.md#section-2) creates a handler for each
application. HydrationCastInterface and DefaultValueProviderInterface receive
HydrationContext for every registration: attributes, Nested.itemCast, profiles, or
external rules. Outgoing SerializationCastInterface receives SerializationContext;
CastInterface combines both directions. Every method requires context, including
standalone calls. Selecting a handler without the required direction gives
ConfigurationException without fallback. See [context contracts](../dto/scope.md)
for signatures and lifetime.

For outgoing values containing a receiver, casting the whole object/container is
forbidden before invocation: see [outgoing representation boundaries](receiver-output.md#section-1).
Global, client, and extension registries do not become sources of input `casts`.
