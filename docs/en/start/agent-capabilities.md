<!-- languages --> <a href="agent-capabilities.md">English</a> · <a href="../../ru/start/agent-capabilities.md">Русский</a> <!-- /languages -->
# Capability map for agents <a id="section-1"></a>

A guide for [agents using the package](agent.md). Each row connects a need to a suitable
mechanism and the conditions for choosing it. You do not need to enable every mechanism;
follow the links for the full contract.

- [SDK structure](#section-2)
- [Setup and configuration](#section-3)
- [Requests and outgoing data](#section-4)
- [DTOs and hydration](#section-5)
- [Execution policies](#section-6)
- [Multiple calls](#section-7)
- [Results and diagnostics](#section-8)
- [Extensions and verification](#section-9)

## SDK structure <a id="section-2"></a>

| Need | Mechanism and selection criteria |
| --- | --- |
| Organize external API operations | [Client and resources](../reference/client/resources.md), [SDK structure](../guides/sdk/design.md). Operation types belong to that operation; extract shared models when they are actually reused. |
| Support multiple services or API versions | [Multiple services](../guides/integration/multi-service.md), [versions](../reference/client/versioning.md). Choose boundaries based on service contracts and API versions. |
| Expose operations and types to tools | [Operation inventory](../reference/client/operation-inventory.md), [DTO catalog](../reference/client/response-dto-catalog.md), [provider catalogs](../reference/client/catalogs.md). Static descriptions are separate from the metadata of a specific call. |

## Setup and configuration <a id="section-3"></a>

| Need | Mechanism and selection criteria |
| --- | --- |
| Create a client and configure its environment | [ClientConfig](../reference/client/configuration.md), [client construction](../reference/client/construction.md). The container is optional; account for transport and auto-resolve dependencies separately. The [client overview](../guides/client/showcase.md) shows setup options. |
| Override an individual call | [Request options](../reference/request/declaration.md#section-14), [full URL](../reference/request/declaration.md#section-16). Settings for one execution are separate from shared client configuration. |
| Supply credentials or work with multiple accounts | [Auth strategies and scopes](../reference/auth/strategies.md), [credential storage](../reference/auth/credentials.md), [token refresh](../reference/auth/tokens.md). Choose a strategy for the API protocol; address changes must account for OriginPolicy. |
| Obtain and renew OAuth2 tokens | [Client Credentials](../reference/auth/oauth2.md#client-credentials), [Authorization Code with PKCE S256 and state](../reference/auth/oauth2.md#authorization-code). [Credential lifecycle](../reference/auth/oauth2.md#credential) covers automatic refresh, local coordination and a saving callback; [snapshots and scopes](../reference/auth/oauth2.md#storage) preserve token/attempt state. Redirects, storage and [coordination between workers](../reference/auth/oauth2.md#workers) belong to the application. |
| Implement managed token authentication | [ManagedTokenAuthenticatorInterface](../reference/auth/oauth2.md#managed-auth) binds state to an execution scope, constructs refresh requests after ownership and observes token versions. Custom auth can also implement only AuthenticatorInterface; one-time exchanges need explicit retry limits. |
| Use Laravel | [SDK setup](https://github.com/apisutra/laravel/blob/master/docs/en/guides/integration/laravel.md), [integrating your SDK](https://github.com/apisutra/laravel/blob/master/docs/en/guides/sdk/laravel.md). Providers, DI, configuration, validation, RequestFactory, and the response adapter belong to the integration; [standalone use](../guides/integration/standalone.md) remains an independent option. |
| Choose the message language | [Localization](../reference/client/localization.md): English by default, Russian through `localization: 'ru'`, or a custom catalog through configuration. Machine error codes are language independent. |

## Requests and outgoing data <a id="section-4"></a>

| Need | Mechanism and selection criteria |
| --- | --- |
| Describe an operation and its response type | [Request declaration](../reference/request/declaration.md), [HTTP attributes](../reference/attributes/http.md), [Returns and unwrap](../reference/attributes/response.md). Derive the method, route, and data path from the API contract. |
| Place data in path, query, headers, and body | [Request parts](../reference/serialization/request-parts.md), [body and BodyRoot](../reference/serialization/body.md). A root list, an object, and a named field are different request shapes. |
| Configure collections, booleans, or nested JSON | [Query and URI](../reference/serialization/uri-query.md), [part formats](../reference/serialization/request-parts.md), [JsonCast](../reference/serialization/casts.md). A query array and a JSON string within one field require different mechanisms. |
| Validate data before sending | [Validation](../reference/client/validation.md), [polymorphic body](../reference/request/declaration.md). Validate needs a configured validator; response hydration serves a different purpose. |
| Separate a convenient DTO representation from the API format | [DX and wire serialization](../reference/serialization/dto-output.md). The result of `toArray()` need not match the HTTP payload. |
| Send or receive a file | [Multipart, binary, and Base64](../reference/files/uploads.md), [download to a file or stream](../reference/files/downloads.md), [archives](../reference/files/archives.md). Choose format and storage for the API and data volume; an [executable example](../guides/recipes/files.md) is available. |

## DTOs and hydration <a id="section-5"></a>

| Need | Mechanism and selection criteria |
| --- | --- |
| Describe DTOs with attributes or use plain models | [Models](../reference/dto/models.md), [declarations](../reference/dto/declarations.md), [HydrationConfig](../reference/dto/configuration.md). Inheriting the base DTO is optional; external rules can describe models without attributes. |
| Construct DTOs through application factories | [Custom hydrators](../reference/dto/hydrators.md). Global instance or per-request hydrator class with DI; native fallback for unsupported types. |
| Transform data without HTTP | [Hydrator::forConfig()](../reference/dto/configuration.md), [DTO output](../reference/serialization/dto-output.md). DTO::from() does not inherit client settings; create a hydrator with explicit configuration to use them. |
| Map field names and paths | [From, To, Map](../reference/attributes/hydration.md), [FieldRule](../reference/dto/field-rules.md). Input mapping and output names are chosen separately; overlapping declarations have explicit rules. |
| Distinguish a missing field, null, and a default | [Defaults and providers](../reference/dto/defaults.md), [RequiredInput](../reference/dto/declarations.md), [ConstructorValue](../reference/dto/constructor-values.md). Required presence, permitted null, and checking a constructor value are separate conditions. |
| Control types, enums, dates, and shared policy | [Scalars](../reference/dto/scalars.md), [value shapes](../reference/dto/shapes.md), [profiles](../reference/dto/profiles.md). Strict is selected explicitly; conversion must not lose meaningful API data. |
| Parse a nested object, list, or response variant | [Collections and Nested](../reference/dto/collections.md), [variants and discriminator](../reference/dto/variants.md), [shapes](../reference/dto/shapes.md). Define object, list, and dictionary shapes from the actual payload. |
| Preserve undescribed input data | [Extras](../reference/dto/extras.md), [receiver output](../reference/serialization/receiver-output.md). Declare the receiver explicitly in the model; the name `_extra` alone enables nothing. DX output and exclusion from requests have different contracts. |
| Perform a custom transformation | [Casts and providers with context](../reference/dto/scope.md). Nested transformations through the context preserve current rules; the context cannot be used after the handler returns. |

The [DTO overview](../guides/dto/showcase.md) collects examples in one model.
[Hydration diagnostics](../reference/dto/diagnostics.md) explains error paths and boundaries.

## Execution policies <a id="section-6"></a>

| Need | Mechanism and selection criteria |
| --- | --- |
| Retry a temporarily unsuccessful request | [Retry, backoff, and idempotency](../reference/execution/retry.md). The operation's effects determine whether retries are allowed; retrying a modifying request requires separate justification. |
| Bound waiting time | [Timeouts and deadlines](../reference/execution/deadlines.md). A timeout for one attempt and a budget for the entire operation address different concerns. |
| Respect API quotas | [Rate limiting](../reference/execution/rate-limit.md), [shared limits with Redis](../reference/integrations/redis.md). Choose the scope for the provider's quota: operation, client, or multiple processes. |
| Respect a server prohibition after 429 | [Shared cooldown](../reference/execution/cooldown.md): automatic per-class/origin/credential coordination within one client by default; an explicit local/Redis backend shares matching scopes. Budget-aware waits and fail-closed storage; no application queue. |
| Reuse responses and tokens | [HTTP cache](../reference/execution/cache.md), [auth cache and locks](../reference/auth/tokens.md). CacheConfig combines store and settings; disabling HTTP caching does not disable auth storage. |

## Multiple calls <a id="section-7"></a>

| Need | Mechanism and selection criteria |
| --- | --- |
| Traverse API pages | [Pagination](../reference/execution/pagination.md). Independent pages/offsets support explicit concurrency with an ordered aggregate and one deadline; cursors and lazy iteration stay sequential. Items-only pagination remains raw without explicit HydrationConfig; DTO declarations alone do not switch this path. A DTO container suits page metadata. |
| Consume pagination elements | [items()](../reference/execution/pagination-items.md). Lazy, sequential, preserves DTOs; FAILED throws even with throwOnErrors=false, PARTIAL supplies data. |
| Start independent HTTP calls concurrently | [sendAsync and Promise API](../reference/execution/transport.md#section-2). The built-in Guzzle adapter overlaps HTTP and SDK waits without loop configuration; `send()` remains synchronous. Custom transports need concurrency support; otherwise async returns `configuration_error`. Synchronous application callbacks can still block the loop. |
| Execute a set of requests | [Concurrent batch/pool, concurrency limits, and error strategies](../reference/execution/batch-pool.md). Both builders offer copying `withConcurrency`; batch uses `withFailStrategy` for failure handling. Pool resolvers must return int. Choose parallel batch/pool for independent calls and sequential batch for strict ordering. |
| Process a large or unknown input | [Pool consume/consumeAsync](../reference/execution/pool-consumption.md) reads by available slots and returns counters. Application handlers own persistence and checkpoints; send retains the full collection. |
| Await or cancel concurrent work | [Wait and cancellation](../reference/execution/transport.md#section-2), [shared Promise waiters](../reference/extensions/execution.md#section-5). Keep promises and await completion before a job exits; this is not fire-and-forget. Cancellation releases SDK waits and transfers but cannot recall already transmitted data. |
| Compose async results with type information | [ResultPromiseInterface](../reference/results/promises.md) gives each async method its sync counterpart’s result after wait. Typed wait/then/otherwise unwrap SDK promises; Guzzle Utils::all can combine them. PHPStan 2.2.13 is verified; PhpStorm completion and automatic DTO inference from dataOrFail are not promised. |
| Choose continuation and error delivery independently | [FailStrategy](../reference/execution/batch-pool.md#section-14) controls whether remaining requests run; [throwOnErrors](../reference/results/errors.md#section-2) controls delivery of the final aggregate. PARTIAL returns normally; FAILED throws only when enabled. |
| Connect dependent operations | [Composite and DependsOn](../reference/request/composition.md). [sendInContextAsync](../reference/extensions/execution.md#section-3) runs a child with the parent context. Use composition for actual result dependencies; a [shared deadline](../reference/execution/deadlines.md) bounds the entire scenario. |
| Await a long-running operation | [Pending/Ready](../reference/execution/continuation-state.md), [await and polling](../reference/execution/continuation-await.md). [SkipContinuation](../reference/attributes/behavior.md#skip-continuation) excludes service requests from client mode mapping. The provider contract determines readiness; a token or successful hydration cannot replace it. |

## Results and diagnostics <a id="section-8"></a>

| Need | Mechanism and selection criteria |
| --- | --- |
| Obtain data or execution information | [ResultHandle, ResolvedResult, ExecutionResult](../reference/results/handles.md). `send()` returns a handle, `resolved()` an application result, and `raw()` execution information. `dataOrFail()` extracts data or throws an error on FAILED. |
| Give SDK users custom result methods | [Custom request results and factories](../guides/recipes/custom-result.md). Wrapper behavior is separate from the response DTO; IDE typing needs an explicit declaration in the SDK's public API. |
| Represent external API errors | [Errors and ErrorContext](../reference/results/errors.md). Errors, trace, and execution metadata do not become DTO business fields. Add a provider trace ID when the API protocol has one. |
| Investigate call chains and failures | [Tracing, audit, debug, and logs](../reference/results/observability.md), [diagnostics](diagnose.md). Sync and async executions have separate execution IDs and preserve parent links; cancellation and abandoned work have terminal diagnostics. [Request-local sensitive fields](../reference/auth/oauth2.md#execution) add masking without affecting other request types. Masking and sending trace headers have separate rules. |

## Extensions and verification <a id="section-9"></a>

| Need | Mechanism and selection criteria |
| --- | --- |
| Extend package behavior | [Hooks](../reference/extensions/hooks.md), [extensions and response handlers](../reference/extensions/extensions.md), [custom transport](../reference/execution/transport.md). Choose a narrow extension point; a non-null response handler result bypasses standard unwrap and Returns hydration. |
| Observe or wrap every execution, including nested requests | [Executor decorators](../reference/extensions/execution.md#section-3). Use for behavior around the entire operation; stage-specific changes belong in hooks. Forward dispatcher per call so child/auth/page/poll executions retain the outer decorator; public send overrides cover only direct calls. |
| Build custom orchestration with canonical results | [ClientExecutorInterface](../reference/extensions/execution.md#section-2) returns finalized ExecutionResult, including FAILED, independently of throwOnErrors. [createScope()](../reference/extensions/execution.md#section-4) supplies the client's clock/logger/trace; the orchestration owner manages its own lifecycle and public delivery. |
| Test an SDK without an external service | [Fakes, sequences, and assertions](../reference/testing/mocking.md), [record/playback](../reference/testing/fixtures.md), [test examples](../guides/testing/unit.md). Check request shape, data conversion, and significant errors in the changed scenario. |
| Compare behavior with the real API | [Live checks](../reference/testing/live.md), [API analysis](../guides/sdk/analysis.md), [SDK coverage](../guides/sdk/coverage.md). Explicit run conditions and permitted data are required; passing fake tests does not prove conformity to the external API. |

[Reference](../reference/README.md) · [Attributes](../reference/attributes/README.md) ·
[Glossary](../glossary/README.md) · [Examples](../examples/README.md).

## Tooling and Laravel <a id="laravel-tools"></a>

[CLI](../reference/client/generation.md) · [Laravel fake, events, queues, Artisan](https://github.com/apisutra/laravel/blob/master/docs/en/README.md) · [Observers without I/O](../reference/results/observation.md).
