<!-- languages --> <a href="dto-output.md">English</a> · <a href="../../../ru/reference/serialization/dto-output.md">Русский</a> <!-- /languages -->
# DTO representation: DX and wire <a id="section-1"></a>

## DtoSerializationProfile <a id="section-2"></a>

**Strong recommendation:** centralize SDK DX serialization semantics through
`DtoSerializationProfile` and wire body semantics through a separate transport policy in `ClientConfig`.

Recommended provider SDK defaults:
- `enumOutput: EnumOutput::TitleValueString`
- `strictEnums: false`
- `serializeNulls: false`
- `namingStrategy`: according to the provider's body DTO contract, often `SnakeCase`

This provides:
- Canonical `toArray()`.
- Centralized configuration through `BaseDto` / `BaseResponseDto`.
- A graceful fallback even when an enum has no `title()` yet.

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Contracts\Interfaces\Serialization\DtoSerializationProfileInterface;
use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Enums\Serialization\EnumOutput;

final readonly class ProviderDtoSerializationProfile implements DtoSerializationProfileInterface
{
    public function policy(): DtoSerializationPolicy
    {
        return new DtoSerializationPolicy(
            enumOutput: EnumOutput::TitleValueString,
            strictEnums: false,
            namingStrategy: NamingStrategy::SnakeCase,
            serializeNulls: false,
        );
    }

    public function casts(): array
    {
        return [];
    }
}

$dtoProfile = new ProviderDtoSerializationProfile();

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    dtoSerializationProfile: $dtoProfile,
    wireBodySerializationPolicy: new DtoSerializationPolicy(
        enumOutput: EnumOutput::Value,
        strictEnums: false,
        namingStrategy: NamingStrategy::SnakeCase,
        serializeNulls: false,
    ),
    requestPartsEnumOutput: EnumOutput::Value,
);
```

- `dtoSerializationProfile` sets DX / `toArray()` semantics.
- `wireBodySerializationPolicy` sets outbound body semantics.
- Request-level enum policy is configured separately.
- Bind the same profile to `BaseDto` / `BaseResponseDto` where practical.

## Canonical DTO serialization <a id="section-3"></a>
`toArray()` is canonical DX serialization for DTOs.

This means:
- `toArray()` describes SDK-friendly DTO output, which need not be the actual wire payload.
- `DtoSerializationProfile` centralizes DX DTO semantics.
- Outbound body follows transport-level wire policy by default.
- Request/`query/header/path` semantics live separately in `ClientConfig`.
- `ClientConfig::wireBodySerializationPolicy` defines the safe wire default.

Recommended provider SDK approach:
- Create a `DtoSerializationProfile`.
- Bind it to `BaseDto` / `BaseResponseDto`.
- Explicitly align wire and DX through `ClientConfig::wireBodySerializationPolicy` if needed.

### Clarification <a id="section-4"></a>
Binding on `BaseDto` is the **recommended place to carry the profile**, but it is optional and other approaches are supported.

For attribute-based models, automatic resolution follows the **concrete DTO class hierarchy**:
- `DtoHydrationProfile` / `DtoSerializationProfile`.
- Class-level `DtoHydrate` / `DtoSerialize` overrides.
- Property-level overrides.
- Defaults requiring no configuration when nothing is declared.

This means:
- One SDK may have several DTO branches with different rules, rather than one `BaseDto`.
- Different DTO hierarchies in one client may have different profiles.
- The DTO and its profile are the source of truth for this model.

An input alternative is an [external rule set](../../guides/dto/plain-models.md)
passed to the client or `Hydrator::forRules()`. It leaves classes without attributes
or ApiSutra base classes. Do not combine `DtoRules` with a hydration profile on the
same class; outgoing serialization profiles are configured separately.

Therefore:
- If the SDK has one shared `BaseDto`, binding there is usually most convenient.
- With independent DTO branches, profiles can be attached to their base classes or concrete DTOs.

## DTO serialization for bodies <a id="section-5"></a>
When a property value is a DTO, the SDK serializes it using:
- **Public** properties only.
- To for renaming, including dot paths.
- Cast and registry casts before serialization.
- Runtime values to select union branches (`A|B`), not type-hint order.

## DateTime serialization <a id="section-6"></a>
Unified DateTime semantics are separated by layer:

- **DTO hydration** — `DtoHydrationProfile` / `DtoHydrate` / `DateTimeFrom`.
- **DTO DX serialization** — `DtoSerializationProfile` / `DtoSerialize` / `DateTimeTo`.
- **Request-level serialization** (`query/header/path` and request body fields) — `ClientConfig::requestDateTime`.
- **Wire body serialization** — `ClientConfig::wireBodySerializationPolicy`.

### Parsing (hydration) <a id="section-7"></a>

See the [hydration contract](../dto/profiles.md#section-6) for input format, time zone,
offset, and invalid-date handling.

### Serialization <a id="section-8"></a>
DTO DX serialization uses `DateTimeSerializationPolicy`:
- format sets the output string format.
- `timezone`, if set, converts the date before formatting.
- The default path formats only `DateTimeInterface`.
- A string is not automatically reparsed as a date during DTO serialization.

Request-level serialization uses `ClientConfig::requestDateTime`.
Outbound body uses transport-level wire policy by default.

### Priorities <a id="section-9"></a>

Custom and type casts follow the [shared cast contract](casts.md#section-3).
When built-in date formatting is used, a field's `DateTimeTo` overrides
`DateTimeSerializationPolicy`. The DTO profile provides the base for DX, body policy
for wire. A custom cast may define its own format and need not read this policy.

### Union types and branch selection <a id="section-10"></a>
For union fields, the SDK first tries to select a type matching the runtime value.
This removes dependence on declaration order:

- `DateTimeInterface|string` with a string value is handled as string.
- `string|DateTimeInterface` with a date object is handled as `DateTimeInterface`.

If no branch matches at runtime, the first non-null type is used.
Serialization does not implicitly reparse strings as dates.

## Enum serialization <a id="section-11"></a>

With default DX/wire separation, serialization has distinct layers:
- **DX / `toArray()`** → `DtoSerializationProfile`
- **Wire body** → `ClientConfig::wireBodySerializationPolicy`
- **Query/header/path** → request-level client config

This matters because:
- `toArray()` may be more convenient for SDK developers than the wire payload.
- Default wire mode must preserve the provider contract.

### Recommended DX DTO default <a id="section-12"></a>
For provider SDK DX defaults, use:
- `enumOutput: EnumOutput::TitleValueString`
- `strictEnums: false`

This produces `title|value`, with a safe fallback if `title()` is absent.

### Recommended wire body default <a id="section-13"></a>
For transport wire defaults, use:
- `enumOutput: EnumOutput::Value`
- `strictEnums: false`

### Options and modes <a id="section-14"></a>
Available `EnumOutput` modes:
- `Value` — `BackedEnum` → value; ordinary enum → name.
- `Name` — always name.
- `Object` — `{value, title}` (**body** only).
- `TitleValueString` — `title|value` string (suitable for `query/header/path`).

### title() and strictEnums <a id="section-15"></a>
For `Object` or `TitleValueString`, the SDK looks for an enum `title()` method:
- `strictEnums=true`: a missing `title()` or invalid return type causes `ConfigurationException`.
- `strictEnums=false`: `Object` falls back to title = value; `TitleValueString` to `value|value`.

`title()` must return the human-readable title of the **current** enum value.
Accepted types: string or `Stringable`.

### Priorities and compatibility <a id="section-16"></a>
Enum serialization priority:
1) Property Cast
2) Registry cast for the property type
3) Enum serialization under the current layer's effective policy

Effective policy comes from:
- DX / `toArray()` → `DtoSerializationProfile`
- Wire body → `ClientConfig::wireBodySerializationPolicy`
- Query/header/path → request-level config

Query/header/path accept **only scalars**. `Object` mode is not used there.

### Small example <a id="section-17"></a>
```php
enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function title(): string
    {
        return match ($this) {
            self::Active => 'Активен',
            self::Inactive => 'Неактивен',
        };
    }
}
```

### Where to configure it <a id="section-18"></a>
See [ClientConfig: Serialization](request-parts.md) for the separation between body
DTO profiles and request-level enum policy.
