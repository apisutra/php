<!-- languages --> <a href="scope.md">English</a> · <a href="../../../ru/reference/dto/scope.md">Русский</a> <!-- /languages -->
# Transformation contexts <a id="section-1"></a>

A handler receives the mandatory context for its direction. This works identically
for attributes, profiles, external HandlerSpec, and per-item casts. Use the context
for nested DTOs: it preserves current rules and the current traversal branch.

## Casts and providers <a id="section-2"></a>

| Interface | Method |
| --- | --- |
| HydrationCastInterface | `hydrate(mixed $value, HydrationContext $context): mixed` |
| SerializationCastInterface | `serialize(mixed $value, SerializationContext $context): mixed` |
| CastInterface | Extends both directional interfaces |
| DefaultValueProviderInterface | `resolve(mixed $value, ValueState $state, array $source, HydrationContext $context): mixed` |

Cast interfaces live in `ApiSutra\Contracts\Interfaces\Casting`, the provider
in `Contracts\Interfaces\DataTransfer`, and contexts in `ApiSutra\Serialization\Context`.
`source` contains the DTO's original data after unwrap/computed;
[ValueState meanings and default ordering](defaults.md) remain unchanged.

The complete [EntryCast](../../../example/hydration-rules/src/EntryCast.php) calls
`$context->hydrate($value, EntryDto::class)` and is executed by the
[shipped example](../../examples/hydration-rules.md).

`HandlerSpec($class, $args)` instantiates a handler for each field or item application.
A property Cast is instantiated per field; attribute Nested.itemCast once per list
traversal. Profile and explicitly supplied instances retain their lifecycle. A
separate context is created before every handler call, including built-in casts.

## Nested transformations <a id="section-3"></a>

| Context | Method | Behavior |
| --- | --- | --- |
| HydrationContext | `hydrate(array\|object $data, string $class): object` | Current rules; return type matches the DTO class |
| HydrationContext | `hydrateCollection(array $items, string $class): array` | A list of DTOs under the same rules |
| SerializationContext | `serialize(object $dto): array` | Current serialization branch and receiver exclusion |
| HydrationContext | `http(): ?HttpMappingContext` | Same HTTP extension, or null outside HTTP |
| Both | `extension(string $type): ?object` | Extension by exact class name, or null |

During nested hydration, the source is considered transformed by the handler:
[diagnostics](diagnostics.md) retains Boundary without inventing an exact sourcePath.
In DX serialization, a child DTO resolves its own profile. In wire/serializeWithPolicy,
it inherits the explicitly selected policy and registry. Cycles are checked in the
current branch; a repeated reference in sibling items is allowed.

A cast over a visible receiver is still forbidden before the handler runs. A DTO
created inside a handler can be serialized through the context; ordinary
[receiver rules](../serialization/receiver-output.md) apply.

## Lifetime <a id="section-4"></a>

After the handler returns or throws, **every method on its context** throws
ConfigurationException. The context releases references to state and HTTP extensions.
Do not retain it in a singleton, registry, or DTO for later operations. A nested
handler gets its own context while the outer context remains valid. Concurrent use
of one context from different Fibers is unsupported.

Direct cast calls without a context are unsupported. For standalone transformations,
use Hydrator/DtoSerializer with a handler declaration. A new Hydrator::default() call
inside a handler does not inherit the current client's rules.

## HTTP data <a id="section-5"></a>

`$context->extension(HttpMappingContext::class)` returns a snapshot of references for
the current call. The class lives in `ApiSutra\Serialization\Integration`.

| Readonly field | Type / purpose |
| --- | --- |
| traceId | string call identifier |
| request | RequestInterface, original request instance |
| response | ?ProviderResponse, current response without fallback to lastResponse |
| role | RequestRole |
| options | ?RequestOptions |
| paginationOptions | ?PaginationOptions |

The snapshot neither reads nor clones streams. Its readonly wrapper preserves
nested object identity without making those objects immutable. During HTTP request
serialization, response is normally null. Standalone calls and hydration of a stored
await outcome do not create an HTTP extension.

PipelineContext and ClientConfig are unavailable through extension. Pass stable
settings as handler dependencies; control retry, budget, parent, and pipeline changes
in [hooks](../extensions/hooks.md) or middleware.

Custom [DTO hydrators](hydrators.md) receive HydrationContext too. Use `$context->http()` for the same extension; extension() remains available. The selected hydrator follows nested transformations.
