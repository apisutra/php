<!-- languages --> <a href="architecture.md">English</a> · <a href="../../ru/development/architecture.md">Русский</a> <!-- /languages -->
# ApiSutra architecture <a id="section-1"></a>

A component map for package developers: decision ownership, execution-flow relationships,
and extension points. Public parameters, precedence, and limitations are described
in the [reference](../reference/README.md).

## SDK goals <a id="section-2"></a>

- Declarative requests through attributes.
- A unified execution pipeline.
- Extensibility through public contracts.
- Predictable results and errors.

## Key components <a id="section-3"></a>

| Component | Responsibility |
| --- | --- |
| [ClientConfig](../../../src/Config/ClientConfig.php) | Settings and dependencies of a specific client |
| [AbstractClient](../../../src/Core/AbstractClient.php) | Assembles services and the pipeline, sends requests, and creates result representations |
| [AbstractRequest](../../../src/Core/AbstractRequest.php) / [RequestExecution](../../../src/Request/RequestExecution.php) | Operation declaration and its wrapper with runtime options |
| [RequestSpecResolver](../../../src/Request/RequestSpecResolver.php) | Reads request attributes and builds RequestSpec |
| [ClientExecutor](../../../src/Execution/ClientExecutor.php) | Selects single execution or page traversal |
| [Pipeline](../../../src/Pipeline/Pipeline.php) | Coordinates one execution and delivers its result |
| [Serializer](../../../src/Serialization/Serializer.php) / [Hydrator](../../../src/Serialization/Hydrator.php) | Transform requests into HTTP representations and response data into DTOs |
| [HydrationRules](../../../src/Serialization/Rules/HydrationRules.php) / [HydrationScope](../../../src/Serialization/Rules/HydrationScope.php) | Describe external DTO rules and preserve them during nested hydration |
| [ExecutionResult](../../../src/Result/ExecutionResult.php) / [ResultHandle](../../../src/Result/ResultHandle.php) | Store the execution outcome and provide ways to access it |

`AbstractClient` assembles the hydrator, serializer, registries, and metadata cache.
Ready-made hook, attribute, and extension registries can be passed to its constructor.
One HydrationConfig block defines the policy and external rule set. Through internal
assembly, the client passes a shared RuleSetCompiler to the hydrator and serializer;
the same hydrator handles the final continuation result. Assembly and client entry
points are covered in [HTTP boundaries](request-serialization.md). Cache value isolation
is described in [attribute internals](attributes.md#section-4).

## Message localization <a id="section-4"></a>

`Localization/Message` stores a key and parameters; `MessageFormatter` selects a template
using immutable `LocalizationConfig`. `Localization/Resources/{en,ru}` holds catalogs
by domain. Only immutable catalogs are cached globally; execution language belongs
to client or standalone-component configuration.

Metadata stores message declarations; client, result, and serializer boundaries select
the representation. `LocalizableExceptionTrait` creates a copy through a specialized
factory preserving fields and the original exception in previous. When adding an
exception type with its own constructor, implement `copyForLocalization()`. Do not
translate third-party-owned strings or add message parameters to log context while
bypassing redaction. See the [public contract](../reference/client/localization.md).

## Principles and boundaries <a id="section-5"></a>

- Substitute transport through [TransportInterface](../reference/execution/transport.md).
- The container is optional. Auto-resolve requires a registered resolver; built-in
  validation requires a factory, which can be supplied explicitly. See
  [client construction](../reference/client/construction.md) and [validation](../reference/client/validation.md).
- By default, pipeline errors are delivered through results; `throwOnErrors`,
  `dataOrFail()`, and `await()` establish explicit exception boundaries. See
  [error delivery](error-handling.md).
- Configuration and runtime-option values are immutable; working execution state
  belongs in `PipelineContext`.
- Attributes describe configuration; external API protocol specifics belong to the provider SDK.

## Execution flows <a id="section-6"></a>

### Laravel boundary <a id="section-7"></a>

`apisutra/php` owns framework-independent mechanisms and optional standalone validation.
`apisutra/laravel` owns Laravel providers, configuration defaults, RequestFactory and
the response adapter. It registers a default through ContainerProviderRegistry;
the core performs no framework detection. Explicit providers retain priority.
DefaultTransportFactory remains in the core because it only needs the generic
container contract. Each SDK supplies its own client binding and protocol settings.

### Request execution <a id="section-8"></a>

Ordinary sending goes through `AbstractClient` to `ClientExecutor`: a single request
enters `Pipeline`, while `Paginator` invokes the executor for each page. Batch and pool
coordinate multiple sends through the client. Within the pipeline, composite aggregates
child request results; depends-on runs dependencies before the main operation.

Continuation starts when the result is explicitly awaited: a separate service evaluates
operation readiness and, when needed, sends poll requests through the client. This is
a separate provider-protocol loop. `sendAsync()` defines a promise-returning interface
and does not itself guarantee nonblocking HTTP.

Executor selection, option forwarding, and child context are described in
[execution flows](execution.md).

### Simplified pipeline <a id="section-9"></a>

For an ordinary request: context and budget → validation → HTTP request preparation →
auth and hooks → cache or transport with retry/rate-limit → response processing →
hydration → result.

An HTTP cache hit skips transport but preserves response processing. Validation errors
end the request before sending; composite has its own result-assembly branch. Exact
order, extension points, and early exits are described in the [pipeline](pipeline.md).

## Client resolution <a id="section-10"></a>

`$client->send($request)` explicitly selects the executor. With `$request->send()`,
[AbstractRequest](../../../src/Core/AbstractRequest.php) uses its bound client or obtains
`ClientResolverInterface` through `ContainerProviderRegistry`.

[ClientResolver](../../../src/Resolver/ClientResolver.php) unwraps `RequestExecution` to the
original request and consults [ClientRegistry](../../../src/Resolver/ClientRegistry.php).
The registry selects the longest matching namespace and caches the client found for
the request class; a new registration clears that cache.

Auto-discovery populates the registry in advance. It does not replace client lookup
at send time. Without a resolver, send explicitly through the client or use `setClient()`;
if a resolver exists but no match is found, `ConfigurationException` is raised. During
`setClient()`, an available resolver also checks ownership by the client class.

`ClientResolver` selects the client; `ClientExecutor` selects the execution method;
`RequestSpecResolver` selects metadata. Registration, discovery, and container setup
are covered in [client discovery](../reference/client/discovery.md). Behavior is checked
by [ClientResolverTest](../../../tests/Unit/Resolver/ClientResolverTest.php) and
[ContainerProviderRequestResolverTest](../../../tests/Unit/Core/ContainerProviderRequestResolverTest.php).

## Extension points <a id="section-11"></a>

| Task | Mechanism and details |
| --- | --- |
| Wrap root and nested executions | [ClientExecutorInterface and decorators](../reference/extensions/execution.md) |
| Act at a sending or hydration boundary | [Hooks](../reference/extensions/hooks.md), executed by HookRunner |
| Attach several handlers as one module | [ExtensionInterface and registries](../reference/extensions/extensions.md) |
| Handle a custom response format | [Response handler](../reference/extensions/extensions.md#section-7), selected by MIME |
| Transform a field value | [Casts](../reference/serialization/casts.md); for nested hydration, [HydrationContext](../reference/dto/scope.md) |
| Describe DTOs without attributes | [External field rules](../reference/dto/field-rules.md) |
| Add a custom declaration | [AttributeRegistry and attribute handlers](attributes.md#section-6) |
| Substitute an HTTP client or auth strategy | [Transport](../reference/execution/transport.md) and [auth](../reference/auth/strategies.md) |

Handler invocation points are in the [pipeline](pipeline.md#section-7). Public extension
capabilities are available to SDK authors; the internal services below serve changes
to the core itself.

## Internal serialization/hydration layer <a id="section-12"></a>

- **PropertyTypeInspector** reads declared types and checks value compatibility.
- **SerializationPlanCompiler / SerializationPlan** build output field plans from the
  shared catalog, separating structure and recipes from current policy and values.
  See [serialization execution](serialization.md).
- **RequestPartsPlanCompiler / RequestPartsPlan** define request field placement and
  argument recipes; RequestPartsCollector executes the plan with the current method,
  URL, and policy. See [request plans and HTTP adapters](request-serialization.md).
- **SerializationValueResolver** executes shared value plans for `DtoSerializer` and
  query/body in `RequestPartsCollector`; the specific branch follows the live value.
- **HydrationTypeSelector** selects the union/declared-type branch for hydration.
- **BuiltinHydrationCaster** selects attribute/profile casts and built-in conversions.
- **RuleSetCompiler** resolves attributes and external declarations, checks conflicts,
  normalizes Shape into ValueShape, caches immutable plans, and lazily resolves the
  runtime class's receiver.
- **MetadataCatalog** shares structural class traversal between hydration, DTO
  serialization, request parts, and the attribute registry; it stores unevaluated
  recipes without policy or call data.
- **HydrationPlanCompiler / HydrationFieldExecutor / HydrationObjectFactory** provide
  the input plan, field stages, and a separate DTO construction phase. Arguments are
  created for each node; constructor defaults only for omitted arguments.
  See [hydration execution](hydration.md).
- **HydrationScope** holds paths, transformation boundaries, and current-call state;
  it is not stored in the description cache. See the [contract](../reference/dto/field-rules.md).
- **SafeScalarHydrationCaster** safely converts scalar values according to DTO types.

Develop shared transformation mechanisms in this layer, keeping `Hydrator`,
`DtoSerializer`, and `RequestPartsCollector` consistent. Public rules are split into
[DTO hydration](../reference/dto/README.md) and [output serialization](../reference/serialization/README.md).

## Transformation state and handlers <a id="section-13"></a>

`TraversalState` stores depth and IDs of active ancestors only. HydrationScope counts
DTO/collection nodes; DtoSerializer counts active objects; ReceiverOutput counts mixed
nodes with local depth and passes active ancestors by reference to avoid copying at
each level. EnumSerializationHelper retains a scalar counter for a separate array
segment, reset after passing through a DTO. It uses the shared limit without creating
an object per array. Every successful stateful entry is closed through finally;
sibling references are not cycles. DtoSerializer retains a separate TraversalState
frame: reentry into the same facade continues the active branch. Between operations,
the frame is empty and reuses ID-set capacity; it retains no DTO, policy, payload,
or context. Completing one call does not remove another unfinished facade call's IDs.
Stateless profile resolvers/type inspectors are reused; live policy results are not.

HydrationContext/SerializationContext are created lazily for a handler invocation and
closed in finally. Closing removes scope, callbacks, and extensions; a context retained
by user code does not retain payload or PipelineContext. HttpMappingAdapter snapshots
HTTP references; directional contexts do not export mutable PipelineContext.
See the [public contract and lifetime](../reference/dto/scope.md).
