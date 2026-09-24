<!-- languages --> <a href="design.md">English</a> · <a href="../../../ru/guides/sdk/design.md">Русский</a> <!-- /languages -->
# SDK structure and type ownership <a id="section-1"></a>

The goal is a predictable SDK client structure with **minimal** boilerplate, making
full use of the package's capabilities, patterns, and modern coding standards,
without requests, DTOs, and execution policies drifting apart.

## Input artifact <a id="section-2"></a>
Start with a decision map (see [provider analysis](analysis.md)).
Use that map as the requirements source for SDK structure.
For step-by-step work, use the [provider development checklist](coverage.md).

## Decision map → unified model <a id="section-3"></a>
Record and **centralize** what must be consistent:
- Enums for operation keys, statuses, entity types, and errors.
- The error model and SDK mapping rules.
- Standardized DTOs and field formats.
- Basic pagination and metadata rules.
- Logical grouping of requests into resources.

Recommendation: add `title()` **from the start** to every enum used in responses/requests.
This simplifies UX, makes serialization predictable, and avoids rework when enabling
`DtoSerializationProfile` and request-level enum policy.
`title()` must return a human-readable name for the enum's **current** value.

**Recommendation:** centralize rules by direction:
- DTO hydration through `DtoHydrationProfile` or external [`HydrationRules`](../dto/plain-models.md) for models without attributes.
- DX DTO serialization through `DtoSerializationProfile`.
- Wire body semantics through `ClientConfig::wireBodySerializationPolicy`.
- Request/query/header/path semantics through `ClientConfig`.
Recommended defaults for body DTOs:
- `enumOutput: EnumOutput::TitleValueString`.
- `strictEnums: false`.

This provides readable `title|value` output, using a safe fallback when `title()`
is absent instead of breaking the SDK outright.

Practical template:
```php
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
```

## Base classes instead of copying <a id="section-4"></a>
Create your own base classes to set defaults and behavior rules.
This is the main way to reduce boilerplate.

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Core\AbstractClient;

final class ProviderClient extends AbstractClient
{
    public function __construct(TransportInterface $transport)
    {
        parent::__construct(
            new ClientConfig(
                baseUrl: 'https://api.example',
                dtoSerializationProfile: new ProviderDtoSerializationProfile(),
                throwOnErrors: false,
            ),
            $transport,
        );
    }
}
```

Recommended base classes for an attribute-based SDK model:
- `BaseClient` — default `ClientConfig` and shared policies.
- `BaseRequest` — shared serialization/validation/options rules.
- `BaseResource` — consistent navigation and request grouping.
- `BaseDto` — shared input DTO format and `DtoHydrationProfile` / `DtoSerializationProfile` binding.
- `BaseResponseDto` — shared response DTO conventions and `DtoHydrationProfile` / `DtoSerializationProfile` binding.

### Recommendation <a id="section-5"></a>

First create base abstractions extending `AbstractRequest`/`AbstractDto`, etc.,
then derive concrete requests and DTOs from those bases.
This centralizes custom tasks and rules without changing dozens of classes.

Recommended pattern for attribute DTOs:
- Bind `BaseDto` / `BaseResponseDto` to `DtoHydrationProfile`.
- Bind `BaseDto` / `BaseResponseDto` to `DtoSerializationProfile`.
- Where needed, `ClientConfigFactory` passes the serialization profile to `ClientConfig::dtoSerializationProfile`; the input profile remains bound to the class.
- Concrete DTOs normally need no additional annotations.
- Class-level `#[DtoHydrate(...)]` / `#[DtoSerialize(...)]` are reserved for rare overrides.

Plain models need neither ApiSutra base DTOs nor attributes. Build one `HydrationRules`
in a factory, connect it as `HydrationConfig(rules: $rules)`, and outside the client,
use `Hydrator::forRules()`. `DTO::from()` does not inherit the rules. Do not combine
`DtoRules` with a hydration profile for the same class; [conflict rules](../../reference/dto/field-rules.md#section-3)
are checked when the hydrator is created. The recommended unknown-field receiver name
is `_extra`; the client with rules excludes it from requests.

Supported DTO inheritance pattern:
- The base DTO holds shared constructor-backed fields.
- A final DTO may add public hydrated properties without its own constructor.
- The hydrator supports this shape through constructor-first creation followed by fallback assignment of remaining properties.

If a provider routinely uses `''` for “no value”, do not normalize it manually in every DTO.
Use:
- Property-level `EmptyStringAsNull` for individual cases.
- Hydration profile-level `emptyStringBehavior` for a centralized policy.

Details and examples: [DTOs](../dto/attribute-models.md).

If the same request body is used by several requests, extract it into a DTO and use it
as a request property (`Body DTO`). See [requests](../requests.md).

If a provider has many endpoints, group them logically into resources in advance
to improve navigation and client API readability. See [resources](../../reference/client/resources.md).

## Structure and entity ownership <a id="section-6"></a>

This section describes an SDK for a specific provider. ApiSutra core's internal
module structure is determined by its mechanisms and contracts.

### Required ownership boundaries <a id="section-7"></a>

- A request and its own DTOs/enums belong to one resource method and are stored together.
- Types used by multiple methods of one resource stay within that resource.
- `Domain/Dto` and `Domain/Enums` are for provider-wide types.
  Do not turn the shared layer into a store of types with no clear owner.
- `Client` owns settings assembly and the entry point. Auth, pagination, and error
  mapping remain separate mechanisms passed into configuration.

### Canonical endpoint structure (strong recommendation) <a id="section-8"></a>

Prefer grouping around the request. Create only the directories and base classes
needed by the SDK features you use:

```text
Base/
  BaseClient.php
  BaseRequest.php
  BaseResource.php
  BaseDto.php
  BaseResponseDto.php
Client/
  ProviderClient.php
  ProviderClientConfig.php
Auth/
Pagination/
Errors/
Domain/
  Dto/
  Enums/
Resources/
  Users/
    UsersResource.php
    Common/
      Dto/
      Enums/
    Requests/
      ListUsers/
        ListUsersRequest.php
        Dto/
          Request/
          Response/
        Enums/
tests/
  Unit/
    Resources/
      Users/
        Requests/
          ListUsers/
            ListUsersTest.php
```

`Common/` is needed only for types used by several resource methods.
The resource root holds the resource itself and its shared components.

Do not spread one method across parallel trees such as
`Requests/ListUsersRequest.php` and `ListUsers/Dto/`: the request, its DTOs, and its
enums must stay in the same `Requests/ListUsers/` subtree.

### Recommendations for large models <a id="section-9"></a>

- A small resource may use a simple structure with few DTOs.
- When file counts grow, group by endpoint, scenario, or meaningful response blocks.
  The same applies to enums.
- For a complex response, `Requests/<Method>/Dto/Response/` may contain groups such as
  `Main/`, `Rights/`, `Bounds/`, `History/`, and `Restrictions/`.
- Introduce resource subgroups based on business context and client API navigation.
  Nesting depth and file count alone do not require new layers.

For resource navigation and binding requests to clients, see [resources](../../reference/client/resources.md).

## Where to define rules <a id="section-10"></a>

Assign rules by responsibility:
- `ClientConfig` — global defaults (retry, rate limits, cache, timeouts).
- `DtoHydrationProfile` — input rules for attribute DTOs (`from()` and pipeline).
- `HydrationRules` — input rules for plain DTOs, strict behavior, shapes, and remaining data; [outgoing request boundaries](../../reference/serialization/receiver-output.md#section-1).
- `DtoSerializationProfile` — DX DTO semantics (`toArray()`, enum output, DTO naming, null policy).
- `ClientConfig::wireBodySerializationPolicy` — transport body semantics.
- Request attributes — endpoint-specific behavior.
- Runtime options — one-off overrides per call.

Practical rule:
- The DTO hydration contract is defined through `DtoHydrationProfile` or external `HydrationRules`.
- The DX DTO contract is defined through `DtoSerializationProfile`.
- The wire body contract is defined through `ClientConfig::wireBodySerializationPolicy`.
- The request/query/header/path contract is defined through `ClientConfig`.
- A serialization profile can be passed to `ClientConfig`; pass hydration rules explicitly to the client and standalone hydrator.

If a provider requires service credentials in `body/query/form`, define them centrally
through `ClientConfig::credentialsConfig`.
This removes duplication across request classes and makes merge policy deterministic.
