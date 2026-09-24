<!-- languages --> <a href="configuration.md">English</a> · <a href="../../../ru/reference/client/configuration.md">Русский</a> <!-- /languages -->
# ClientConfig parameters <a id="section-1"></a>

The [single-example showcase](../../guides/client/showcase.md) demonstrates how
settings affect request execution. The complete parameter catalog follows.

`ApiSutra\Config\ClientConfig` is immutable configuration for one client.
`baseUrl` is required; transport is passed separately to the client constructor.
See [complete construction](construction.md) and the
[executable example](../../../example/sdk/src/Config/ClientConfigFactory.php).

## Creating and changing configuration <a id="section-2"></a>

Snippet for an application with Composer autoload:

```php
use ApiSutra\Config\ClientConfig;

$config = new ClientConfig(baseUrl: 'https://api.example.test');
$another = $config->with(timeout: 15, hydration: null);
```

`with()` creates new configuration, preserves fields without overrides, and honors
explicit null. For example, `with(hydration: null)` disables the rules in the copy.
Changing configuration does not rebuild an existing client: use the copy for a new
instance. The optional `ApiSutra\Laravel\ClientConfigFactory` in `apisutra/laravel` obtains application defaults;
build external rules in a factory/provider, not in a cacheable config array.

The constructor validates parameter combinations and throws `ConfigurationException`
for invalid configuration. `timeout`/`connectTimeout` are in seconds, `delay` in
milliseconds; other units are specified in their topic references.

## Parameter catalog <a id="section-3"></a>

The table lists every constructor parameter. Complete defaults, priorities, and
restrictions belong to the linked reference rather than being duplicated here.

| Parameter | PHP type | Contract reference |
| --- | --- | --- |
| `baseUrl` | `string` | [URI assembly and address overrides](../serialization/uri-query.md) |
| `auth` | `?AuthenticatorInterface` | [Authentication selection](../auth/strategies.md) |
| `authScopes` | `array` | [Authentication selection](../auth/strategies.md) |
| `authPolicy` | `?AuthPolicyInterface` | [Authentication selection](../auth/strategies.md) |
| `authRetryOn401` | `bool` | [Refresh and 401](../auth/tokens.md) |
| `authRetryAttempts` | `int` | [Refresh and 401](../auth/tokens.md) |
| `logger` | `?LoggerInterface` | [Logs and debug](../results/observability.md) |
| `logLevel` | `string` | [Logs and debug](../results/observability.md) |
| `cacheConfig` | `?CacheConfig` | [Unified cache configuration](../execution/cache.md): store and HTTP/`auth` parameters |
| `timeout` | `int` | [Time limits](../execution/deadlines.md) |
| `connectTimeout` | `int` | [Time limits](../execution/deadlines.md) |
| `retry` | `?RetryConfig` | [Retries](../execution/retry.md) |
| `rateLimit` | `?RateLimitConfig` | [Quotas](../execution/rate-limit.md) |
| `pool` | `?PoolConfig` | [Batch and pool](../execution/batch-pool.md) |
| `queryArrayFormat` | `QueryArrayFormat` | [Query format](../serialization/uri-query.md) |
| `serializeNulls` | `bool` | [Request parts assembly](../serialization/request-parts.md) |
| `namingStrategy` | `NamingStrategy` | [Request parts assembly](../serialization/request-parts.md) |
| `casts` | `array` | [Outgoing casts](../serialization/casts.md) |
| `dtoSerializationProfile` | `?DtoSerializationProfileInterface` | [DTO: DX and wire](../serialization/dto-output.md) |
| `wireBodySerializationPolicy` | `?DtoSerializationPolicy` | [DTO: DX and wire](../serialization/dto-output.md) |
| `requestPartsEnumOutput` | `EnumOutput` | [Request part values](../serialization/request-parts.md) |
| `requestPartsStrictEnums` | `bool` | [Request part values](../serialization/request-parts.md) |
| `requestDateTime` | `?DateTimeSerializationPolicy` | [Outgoing dates](../serialization/dto-output.md) |
| `delay` | `int` | [Time limits](../execution/deadlines.md) |
| `resultExceptions` | `?ResultExceptionConfig` | [Messages and custom exceptions](../results/exceptions.md) |
| `throwOnErrors` | `bool` | [Error delivery](../results/errors.md) |
| `debug` | `bool` | [Logs and debug](../results/observability.md) |
| `environment` | `Environment` | [Logs and debug](../results/observability.md) |
| `idempotencyHeader` | `string` | [Retries](../execution/retry.md) |
| `extensions` | `array` | [Extensions](../extensions/extensions.md) |
| `paginationConfig` | `?PaginationConfig` | [Pagination](../execution/pagination.md) |
| `archive` | `?ArchiveConfig` | [Archives](../files/archives.md) |
| `paginationRule` | `?PaginationRule` | [Pagination](../execution/pagination.md) |
| `resolvedResultFactory` | `?ResolvedResultFactoryInterface` | [Contract](../results/handles.md#section-10), [custom result recipe](../../guides/recipes/custom-result.md) |
| `errorMapper` | `?ClientErrorMapperInterface` | [Error mapping](../results/errors.md) |
| `errorContextFactory` | `?ErrorContextFactoryInterface` | [Error mapping](../results/errors.md) |
| `responseFactory` | `?ClientResponseFactoryInterface` | [Result representation](../results/handles.md) |
| `containerProvider` | `?ContainerProviderInterface` | [Container and explicit assembly](construction.md) |
| `requestEnrichers` | `array` | [Credentials and origin](../auth/credentials.md) |
| `credentialsConfig` | `?CredentialsEnrichmentConfig` | [Credentials and origin](../auth/credentials.md) |
| `continuationTokenExtractor` | `?ContinuationTokenExtractorInterface` | [Waiting and token](../execution/continuation-await.md) |
| `resultMetaExtractor` | `?ResultMetaExtractorInterface` | [Runtime result metadata](../results/handles.md#section-11) |
| `providerCatalogRegistry` | `?ProviderCatalogRegistryInterface` | [SDK catalogs](catalogs.md) |
| `defaultContinuationMode` | `ContinuationMode` | [Operation state](../execution/continuation-state.md) |
| `defaultPollRequest` | `?string` | [Waiting and token](../execution/continuation-await.md) |
| `continuationModeApplicator` | `?ContinuationModeApplicatorInterface` | [Operation state](../execution/continuation-state.md) |
| `redaction` | `RedactionPolicy` | [Logs and debug](../results/observability.md) |
| `textBooleanFormat` | `BooleanFormat` | [Request part values](../serialization/request-parts.md) |
| `originPolicy` | `OriginPolicy` | [Credentials and origin](../auth/credentials.md) |
| `includeClientQuota` | `bool` | [Quotas](../execution/rate-limit.md) |
| `rateLimitBackend` | `?RateLimitBackendInterface` | [Quotas](../execution/rate-limit.md) |
| `continuationStateResolver` | `?ContinuationStateResolverInterface` | [Operation state](../execution/continuation-state.md) |
| `localization` | `LocalizationConfig` &#124; `string` (property `LocalizationConfig`) | [Message language and SDK catalogs](localization.md) |
| `hydration` | `?HydrationConfig` | [Shared DTO policy and rules](../dto/configuration.md) and [outgoing receiver](../serialization/receiver-output.md) |

## Client configuration boundary <a id="section-4"></a>

A request runtime override does not change ClientConfig. Settings follow their topic
contracts: “every override always wins” does not replace the specific rules for
authentication, accumulated quotas, wire policy, or external rules.

The HTTP cache stores provider responses, not completed DTOs. Metadata caching is a
separate mechanism; enabling it must not change isolation of object defaults and
attribute arguments. See [DTO lifecycle](../dto/lifecycle.md).

[Client section](README.md).

`with(cacheConfig: ...)` replaces the entire block; null removes it. To change individual
fields, pass a `CacheConfig::with(...)` copy. See
[disabling and replacing settings](../execution/cache.md#section-3).

Execution extension points belong to [the client executor](../extensions/execution.md).
`throwOnErrors` controls [final public delivery](../results/errors.md#section-2), including
batch/pool/pagination; it does not alter internal collection or readiness decisions.
`resultMetaExtractor` follows the [per-request lifecycle](../results/handles.md#meta-lifecycle).


ClientConfig::cooldown defaults to CooldownConfig(): automatic local coordination after 429.
ClientConfig::cooldownBackend defaults to null; an explicit LocalCooldownBackend or PhpRedisCooldownBackend shares state across matching scopes.
See [fields, overrides and wait limits](../execution/cooldown.md).

`diagnosticLabel: ?string` explicitly labels a client in the
[observer snapshot](../results/observation.md#observer). It does not select a client
or participate in credentials, cache keys, quotas or cooldown.
