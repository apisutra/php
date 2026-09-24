<!-- languages --> <a href="client.md">English</a> · <a href="../../ru/glossary/client.md">Русский</a> <!-- /languages -->
# Client <a id="section-1"></a>

| Term | Meaning | Details |
| --- | --- | --- |
| <a id="abstractclient"></a> AbstractClient | The base class for API clients. | [Contract](../reference/client/configuration.md) |
| <a id="clientinterface"></a> ClientInterface | The interface for SDK clients. | [Contract](../reference/client/configuration.md) |
| <a id="clientconfig"></a> ClientConfig | Immutable client configuration; the transport itself is passed separately to the constructor. | [Contract](../reference/client/configuration.md) |
| <a id="delay"></a> delay | A ClientConfig parameter. | [Contract](../reference/execution/deadlines.md) |
| <a id="throwonerrors"></a> throwOnErrors | A ClientConfig parameter. | [Contract](../reference/results/errors.md) |
| <a id="archiveconfig"></a> ArchiveConfig | A value object for archive configuration. | [Contract](../reference/files/archives.md) |
| <a id="retryconfig"></a> RetryConfig | A value object for retry configuration. | [Contract](../reference/execution/retry.md) |
| <a id="cacheconfig"></a> CacheConfig | A single block containing the store and cache settings in ClientConfig; with() copies individual fields. | [Contract](../reference/execution/cache.md) |
| <a id="cachemode"></a> CacheMode | The cache mode enum. | [Contract](../reference/execution/cache.md) |
| <a id="ratelimitconfig"></a> RateLimitConfig | A value object for rate-limit configuration. | [Contract](../reference/execution/rate-limit.md) |
| <a id="backoffstrategy"></a> BackoffStrategy | The strategy for increasing the delay between retries: constant, linear, or exponential. | [Contract](../reference/execution/retry.md) |
| <a id="poolconfig"></a> PoolConfig | A value object for request pool configuration. | [Contract](../reference/execution/batch-pool.md) |
| <a id="poolexecutor"></a> PoolExecutor | Executes a stream of requests with a limit on active tasks; actual concurrency depends on the transport. | [Contract](../reference/execution/batch-pool.md) |
| <a id="concurrencyresolverinterface"></a> ConcurrencyResolverInterface | An interface that determines the pool's initial concurrency; the API also accepts a callable. | [Contract](../reference/execution/batch-pool.md) |
| <a id="namingstrategy"></a> NamingStrategy | The field naming rule: preserve the name or use snake_case. | [Contract](../reference/dto/profiles.md) |
| <a id="requestpartsenumoutput"></a> requestPartsEnumOutput | A ClientConfig parameter. | [Contract](../reference/serialization/request-parts.md) |
| <a id="requestpartsstrictenums"></a> requestPartsStrictEnums | A ClientConfig parameter. | [Contract](../reference/serialization/request-parts.md) |
| <a id="wirebodyserializationpolicy"></a> wireBodySerializationPolicy | The transport-level body serialization policy in `ClientConfig`. | [Contract](../reference/serialization/dto-output.md) |
| <a id="enumoutput"></a> EnumOutput | The representation of an enum during serialization: its value, name, object, or a string containing its title and value. | [Contract](../reference/serialization/dto-output.md) |
| <a id="requestdatetime"></a> requestDateTime | The request-level date-time serialization policy in `ClientConfig`. | [Contract](../reference/serialization/request-parts.md) |
| <a id="dtohydrate"></a> DtoHydrate | An attribute that partially configures the input model on top of a profile. | [Contract](../reference/dto/profiles.md) |
| <a id="datetimeinvalidbehavior"></a> DateTimeInvalidBehavior | The response to an invalid date during hydration: throw an exception or return null, followed by a field type check. | [Contract](../reference/dto/profiles.md) |
| <a id="environment"></a> Environment | The client's environment; it affects metadata caching and discovery modes, among other things. | [Contract](../reference/client/configuration.md) |
| <a id="errorcode"></a> ErrorCode | An ApiSutra system error code; HTTP statuses are mapped to it separately from the provider's business code. | [Contract](../reference/results/errors.md) |
| <a id="client-auto-discovery"></a> Client auto-discovery | Automatic discovery and registration of the client's request namespaces. | [Contract](../reference/client/discovery.md) |
| <a id="clientregistry"></a> ClientRegistry | Maps request namespaces to clients; selects the longest match and checks request ownership. | [Contract](../reference/client/discovery.md) |
| <a id="clientresolverinterface"></a> ClientResolverInterface | A contract for selecting a client for a request and checking that the request belongs to that client. | [Contract](../reference/client/discovery.md) |
| <a id="clientresolver"></a> ClientResolver | Resolves the client through the registry, taking request execution wrappers into account. | [Contract](../reference/client/discovery.md) |
| <a id="discoveryoptions"></a> DiscoveryOptions | Settings for discovering a client's requests and caching the results. | [Contract](../reference/client/discovery.md) |
| <a id="discoverycachemode"></a> DiscoveryCacheMode | The discovery cache mode: select by environment, force on, or force off. | [Contract](../reference/client/discovery.md) |
| <a id="clientdiscoveryservice"></a> ClientDiscoveryService | Finds the client's request namespaces and registers them in ClientRegistry. | [Contract](../reference/client/discovery.md) |
| <a id="clientdiscoverycache"></a> ClientDiscoveryCache | Stores discovered namespaces in a PSR-16 cache or in process memory. | [Contract](../reference/client/discovery.md) |

[All terms](README.md).
