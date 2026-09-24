<!-- languages --> <a href="request-parts.md">English</a> · <a href="../../../ru/reference/serialization/request-parts.md">Русский</a> <!-- /languages -->
# Assembling request parts <a id="section-1"></a>

Serialization and mapping settings.

Basic required-field and native-type compatibility checks, strict JsonCast, and safe
hydration errors work automatically;
[nullable/default behavior follows DTO declarations](../dto/defaults.md#section-2).
Use [HydrationRules](../../guides/dto/plain-models.md) to forbid implicit scalar
conversions, validate list items, or describe models independently of attributes.
Date profiles and custom casts handle provider-specific formats. The
[original response for diagnostics](../dto/diagnostics.md#section-4) is available with debug disabled.

## NamingStrategy <a id="section-2"></a>
```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\NamingStrategy;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    namingStrategy: NamingStrategy::SnakeCase,
);
```

The default is `NamingStrategy::None` (names are not transformed). Change it if the
API uses another naming convention for request/query/header/path. Incoming naming
fallback is configured separately through `DtoHydrationProfile` or `RulePolicy::naming`
in [external rules](../../guides/dto/plain-models.md). For body DTOs, the recommended
canonical naming policy is set through `DtoSerializationProfile`.

## QueryArrayFormat <a id="section-3"></a>
```php
use ApiSutra\Enums\Http\QueryArrayFormat;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    queryArrayFormat: QueryArrayFormat::Brackets,
);
```

The default is `QueryArrayFormat::Brackets`.
Change it if the provider expects `comma` or `repeat`.

## Text booleans <a id="section-4"></a>

`textBooleanFormat` is optional: default `BooleanFormat::Numeric` sends true as 1 and
false as 0 in query and scalar multipart fields. For APIs expecting words, configure the client:

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Serialization\BooleanFormat;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    textBooleanFormat: BooleanFormat::Literal,
);
```

Override a field with `#[Cast(BooleanCast::class, BooleanFormat::Numeric)]` or Literal;
see [BooleanCast](casts.md#section-8). A cast runs first, then any remaining boolean
is formatted. Strings returned by casts are not reinterpreted. This setting does
not change JSON, DTOs, paths, or headers.

NamingStrategy defines how the SDK converts property names to request keys and back.

## Available modes <a id="section-5"></a>
- `None` — no conversion
- `SnakeCase` — `camelCase` → `snake_case`

`None` is the default.

## Where it applies <a id="section-6"></a>
- **Requests:** query/body assembly when no name is specified.
- **DTOs:** hydration without an explicit From/Map/Nested or `FieldRule::from()` path.
- **DTO serialization:** without To; for DX DTOs, set the recommended naming policy through `DtoSerializationProfile`.

## Overrides <a id="section-7"></a>
- `#[Query(name: ...)]` / `#[Body(nested: ...)]` — requests
- `#[From('data.id')]` — incoming DTO data
- `FieldRule::from('data.id')` — incoming data with external rules
- `#[To('user_id')]` — DTO serialization

## Example <a id="section-8"></a>
```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\NamingStrategy;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    namingStrategy: NamingStrategy::SnakeCase,
);
```

## Recommendations <a id="section-9"></a>
- The `ClientConfig::namingStrategy` example above configures requests. For incoming
  DTOs, set naming in `DtoHydrationProfile` or external `RulePolicy`;
  see [rule priorities](../dto/scalars.md#section-3).
- Enable `SnakeCase` if the API already uses `snake_case`.
- For mixed conventions, use `None` with individual From/To/Query declarations.
- For provider SDKs, centralize DX DTO naming through `DtoSerializationProfile` and
  request-level fallback through `ClientConfig`.

This section explains how the SDK assembles `PreparedRequest`: which fields enter
path/query/body and how naming strategy and casts apply.

## Stage order <a id="section-10"></a>
Before serialization, the SDK performs two validation steps:
1. Standard request attribute validation (Validate).
2. oneOf/discriminator contract validation (`RequestOneOf`, `RequestDiscriminator`).

A contract violation ends the request with `ErrorCode::RequestContractViolation`
before `PreparedRequest` preparation and HTTP.

Inside `Serializer`, the subsequent stages are:
1. `RequestPartsCollector` collects `query/body/files/headers/placeholders`.
2. Enrichment applies (`credentialsConfig` and `requestEnrichers`).
3. `FilePayloadPreparer` finalizes `body/stream/headers`.
4. `PreparedRequest` is assembled.

### Content-Type for JSON bodies <a id="section-11"></a>
For JSON bodies (the default case and Base64), the SDK sets `Content-Type: application/json`
unless the header is already set. Multipart and Binary set their own Content-Type
(boundary, MIME type). Override through Header('Content-Type') or a request enricher.

`RequestOneOf` supports top-level fields and dot paths for nested structures.
`OneOfMode` selects `ExactlyOne` or `AtLeastOne` validation.

## Request parts enrichment (before payload finalization) <a id="section-12"></a>
Enrichment works on `RequestPartsBag`, not a raw JSON string, so it supports
`body/query/multipart form` without disrupting the shared pipeline.

Enable the built-in `CredentialsEnricher` through `ClientConfig::credentialsConfig`.

### Merge policy <a id="section-13"></a>
Priorities:
1. Runtime overrides (`withCredentials...`)
2. Explicit request fields
3. Provider defaults (`credentialsConfig`)

Merge modes:
- `fill-missing` (default) — fills only missing keys.
- `overwrite` — replaces conflicting values.
- `fail-on-conflict` — throws `ConfigurationException` on conflict.

### Scope and exceptions <a id="section-14"></a>
- Scope comes from runtime `withCredentialsScope()`, otherwise `AuthScope`.
- SkipCredentialsEnrichment disables enrichment on the request class.
- Runtime `withCredentialsEnrichment(true/false)` takes priority.

### MVP restrictions <a id="section-15"></a>
- `BodyRoot` is not enriched, to preserve root payload contracts.
- `form` defaults apply only to multipart text fields.
- Enrichment does not modify files (`FileInput`).

## Basic conventions <a id="section-16"></a>
For each request property:
- Path or an endpoint placeholder `{name}` → **path**
- Header → **headers**
- File → **files**
- `BodyRoot` → **the entire body**
- Body → **body**
- Query → **query**
- Without a property attribute, class-level `RequestDefaults`.`unmapped` applies if set.
  Otherwise `GET/DELETE` → **query**, other methods → **body**.

Priorities:
1. Ignore
2. Property attributes (Path, Header, File, Query, `BodyRoot`, Body)
3. Class-level `RequestDefaults`
4. HTTP method convention

Restriction: `BodyRoot` cannot be combined with Body or fields routed to body by default.
Otherwise `ConfigurationException` is thrown.
