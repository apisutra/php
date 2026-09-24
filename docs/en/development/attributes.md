<!-- languages --> <a href="attributes.md">English</a> · <a href="../../ru/development/attributes.md">Русский</a> <!-- /languages -->
# Attribute internals <a id="section-1"></a>

How the core reads, caches, and processes attributes. Public declarations are in the
[attribute reference](../reference/attributes/README.md); handler invocation points
are described in the [pipeline](pipeline.md#section-7).

## Role of attributes <a id="section-2"></a>

Attributes provide declarative configuration for requests and DTOs. They describe:

- HTTP method and endpoint;
- data sources (query/body/path/header/file);
- validation and transformation rules;
- pipeline behavior (cache/retry/timeout/ratelimit).

## Consumers <a id="section-3"></a>

- **RequestSpecResolver** builds request metadata (HTTP, responseType, behavior).
- **Serializer** applies request attributes to build PreparedRequest.
- **Hydrator** applies DTO attributes during hydration.
- **HookRunner** executes hook attributes.
- **AttributeRegistry** runs custom attribute handlers.

## Resolution and caching <a id="section-4"></a>

- [MetadataCatalog](../../../src/Metadata/MetadataCatalog.php) performs the shared property traversal.
  `ClassMetadata` stores properties as `ReflectionProperty`: ready recipes with declaring
  class, native types, and modifiers; an individual object's state is checked on reading.
- `AttributeMetadataCache` owns the shared structural catalog. Directional
  `:hydration-plan`, `:serialization-plan`, and `:request-parts-plan` plans select their
  own attributes and check applicability at the existing points. Constructor parameters
  are read only for the input plan; their defaults are not evaluated during discovery.
  [Input plans](hydration.md) are separated by validated rule description in a WeakMap;
  the shared catalog stores neither client policy nor live profile results.
- `warmup()` uses the same catalog. The shared class key remains an `AttributeRegistry`
  adapter containing recipes in Reflection order, rather than a transformation plan.
  Warmup does not create user-defined attribute arguments, profiles, or handlers.
- Hydrator and serializers create attribute values with object arguments for the
  current node; scalar/enum values and arrays of values allow template reuse.
  PHP evaluates constructor defaults when an argument is omitted.
- Clients disable caching in `Environment::Local/Testing` and enable it in Production.
  `Hydrator::default()` discovers attribute declarations without configuration;
  `Hydrator::forRules()` creates its own enabled cache. Object isolation does not
  depend on the cache mode.

User-defined constructors run at different times for [DTO defaults](../reference/dto/lifecycle.md#section-3)
and [attribute arguments](../reference/dto/lifecycle.md#section-2).
The metadata cache is not intended for shared mutable state.

### DTO rule publication <a id="section-5"></a>

`RuleSetCompiler` belongs to its configuration. It stores completed descriptions
separately from drafts for the current root. Internal `Node → Node` and `A → B → A`
references can read a draft; only a fully validated group becomes available to the
executor. A native object type alone does not trigger compilation of a child DTO.

An external reentrant call for a class that is not ready during compilation,
including from Fiber/autoload, receives `ConfigurationException`. Completed descriptions
remain available. Rejecting reentry does not damage the suspended operation. There is
no separate state machine per DTO field: the compiler owns the lifecycle.

Drafts are removed after errors. An error before the first `CompiledDtoRules` is created
preserves previous completed entries; an error after that boundary clears the owner's
completed rules and receiver cache. The structural catalog remains usable. Errors are
not cached; the next call validates the declaration again. Disabling
`AttributeMetadataCache` does not disable existing memoization of completed rules.

Checks: [shared structure and recipes](../../../tests/Unit/Serialization/MetadataCatalogTest.php),
[recursion and failure](../../../tests/Unit/Serialization/MetadataCompilationTest.php),
[value isolation](../../../tests/Unit/Serialization/MetadataValueIsolationTest.php).

## Custom attributes (AttributeRegistry) <a id="section-6"></a>

[AttributeRegistry](../../../src/Attributes/AttributeRegistry.php) registers mappings from
attribute classes to handler classes. Its resolver creates a handler on first access;
the instance is then reused. Current execution data is passed through the context.

### Handler types <a id="section-7"></a>

- **AttributeHandlerInterface**: a simple handler receiving the attribute,
  Reflection target, and `PipelineContext`.
- **AttributeContextHandlerInterface**: a contextual handler receiving
  `AttributeContext` with `PipelineStage`, `type`, `data`, and the class attributes;
  it can return modified data.

### Traversal order <a id="section-8"></a>

1. Class attributes.
2. Property attributes.

The order follows Reflection declaration order.

## AttributeContext <a id="section-9"></a>

The context supplied to a handler contains:

- `attribute`: attribute instance;
- `target`: ReflectionClass or ReflectionProperty;
- `type`: Request/Dto;
- `stage`: PipelineStage;
- `context`: PipelineContext;
- `data`: current data, if the handler processes a stage.

## StageProcessor <a id="section-10"></a>

`StageProcessor` connects Pipeline to `AttributeRegistry::processStage()`.
Actual invocation points are listed in the [pipeline](pipeline.md#section-7).
A contextual handler can return modified data for the next handler; the calling stage
determines how to use the final value. Stage entries in audit do not automatically
invoke handlers.

[AttributeRegistryTest](../../../tests/Unit/Attributes/AttributeRegistryTest.php) checks
registration and data passing.

## Limitations <a id="section-11"></a>

- Attributes must not execute business logic.
- Move expensive computation into services/hooks.
