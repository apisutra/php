<!-- languages --> <a href="analysis.md">English</a> · <a href="../../../ru/guides/sdk/analysis.md">Русский</a> <!-- /languages -->
# Investigating an external API <a id="section-1"></a>

Record key decisions before implementing a provider to reduce rewrites
and simplify maintenance.

## Basic settings <a id="section-2"></a>
- baseUrl and API versions.
- Required headers and shared parameters.
- Response formats (JSON, XML, files).
- Whether there are several **different** API services with separate baseUrl/auth.
- Whether global settings differ (baseUrl/auth/pagination/serialization).

## Authentication <a id="section-3"></a>
- Type (API key, OAuth, HMAC).
- Whether rotation/refresh is needed.
- Whether different access levels (scopes) exist.
- Where the token is sent (header/query), and whether only some requests need it.
- Whether preparatory/system requests and dependencies between steps exist.
- How 401/403 are handled and how many attempts are allowed.

Recommendation: move auth logic into classes (`AuthenticatorInterface`,
`AuthPolicyInterface`) and pass them to `ClientConfig` to keep configuration thin.

Corresponding APIs: `ClientConfig.auth`, `authScopes`, `AuthPolicy`, `AuthScope`.

## Pagination <a id="section-4"></a>
- Type: offset or cursor.
- Where metadata and items are stored.
- Page limits.
- Whether custom classes are needed: `PaginationMetaResolver`, item collection, DTO container.

Corresponding APIs: `PaginationConfig`, `#[Pagination]`, `PaginationRule`.

## Rate limits and retry <a id="section-5"></a>
- Limits by keys and windows (per-token/per-endpoint).
- Behavior when exceeded (wait/throw).
- Which statuses and exceptions can safely be retried.

Corresponding APIs: `RateLimitConfig`, `RetryConfig`, `#[RateLimit]`, `#[Retry]`.

## Errors and mapping <a id="section-6"></a>
- Provider error structure.
- A unified SDK error model.
- Whether typed error context is needed (traceId/target/hint); **`providerTraceId`** only
  with confirmed provider tracing support (see the [metadata contract](../../reference/results/handles.md#section-11)).

Corresponding APIs: `ClientErrorMapperInterface`, `ErrorContextFactoryInterface`, `ResolvedResultFactoryInterface`.

## DTOs and serialization <a id="section-7"></a>
- Naming strategy.
- Types/formats (dates, money, enums).
- Validation requirements.

Corresponding APIs: [attribute profiles](../../reference/dto/profiles.md) or
[external rules](../../reference/dto/field-rules.md). `ClientConfig.casts` applies
during serialization; the registry does not enable hydration casts.

## Files and archives <a id="section-8"></a>
- File uploads (multipart/binary/base64).
- File and archive downloads.

Corresponding APIs: `#[File]`, `#[Download]`, `ArchiveExtension`, `ArchiveConfig`.

## Structure, DTOs, and enums <a id="section-9"></a>
- Folder structure and entity ownership (resources → requests/DTOs/enums).
- Provider-wide types in `Domain/Dto` and `Domain/Enums`.
- DTO/enum grouping into subfolders when there are many files.
- Base abstractions (BaseRequest/BaseDto/BaseResource).

See the [provider methodology](../../start/create-sdk.md).

## Sample analysis checklist <a id="section-10"></a>
- [] baseUrl, API versions, separate domains.
- [] Multiple services and differences in global settings.
- [] Auth: type, scopes, refresh, 401/403.
- [] Pagination: type, meta/items, limits, nonstandard fields.
- [] Rate limits/retry: limits, codes/exceptions.
- [] Errors: format and mapping.
- [] DTO/serialization: naming, types, casts, validation.
- [] Files/archives: upload/download.
- [] SDK structure: resources, DTOs/enums, Domain layer, base abstractions.
- [] Sandbox/testing: baseUrl, credentials, limitations.

## Final artifact <a id="section-11"></a>

Save an SDK decision map with links to the API version and fixtures. For every decision,
record a confirmed fact, an unknown condition, and how to verify it.
Choose settings in the [ClientConfig catalog](../../reference/client/configuration.md)
and declarations in the [attribute reference](../../reference/attributes/README.md).
Multiple services are covered in a [separate recipe](../integration/multi-service.md).

Provider credentials use [credentialsConfig](../../reference/auth/credentials.md).
Operation-specific exceptions and oneOf contracts are declared on the request:
[RequestDefaults](../../reference/attributes/request.md#section-11),
[BodyRoot](../../reference/attributes/request.md#section-6),
[RequestOneOf](../../reference/attributes/request.md#section-13), and
[RequestDiscriminator](../../reference/attributes/request.md#section-14).
Check them before dispatch through [RequestContractTestHelper](../../reference/testing/mocking.md).

## Protocol details <a id="section-12"></a>
Record protocol details before development: they affect code structure.
- Where auth is sent (header/query) and whether only some requests need it.
- Whether preparatory/system requests and “key → data” dependencies exist.
- Whether special query formats are required (for example, array → string).
- Whether URLs contain substitutions (path parameters).
- Whether responses contain polymorphic arrays (`items[]` with different element types).

Choose mechanisms for these in advance: `authScopes`/`AuthScope`, `QueryArrayFormat`,
`#[Path]`, `DependsOnRequestInterface`, and serialization rules.
If a provider expects a nonstandard format, such as an array encoded as a string,
record it beforehand and see [request serialization](../../reference/serialization/request-parts.md).

Recommendation: always examine responses for polymorphic arrays before developing DTOs.
This lets you choose the appropriate modeling strategy in advance:
- Ordinary `array` or `RawCollection` for flexible mixed structures.
- A typed collection when a common element contract exists.
- `Nested` with polymorphic hydration (`discriminator`/`map`, `Value` or `Key` mode)
  and an explicit policy for unknown variants.
See [DTOs](../dto/attribute-models.md), [Data Transfer attributes](../../reference/attributes/hydration.md),
and [collections](../../reference/dto/collections.md).
