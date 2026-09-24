<!-- languages --> <a href="operation-inventory.md">English</a> · <a href="../../../ru/reference/client/operation-inventory.md">Русский</a> <!-- /languages -->
# SDK operation inventory <a id="section-1"></a>

Read-only inventory of provider SDK operations, built from request introspection.

## Purpose <a id="section-2"></a>
Use this layer when an SDK needs to:
- List every declaratively described operation.
- Expose method/`endpoint`/`responseType` by request class.
- Expose stable SDK call paths such as `v1()->objects()->getByCadnum()`.
- Build one DX snapshot without a manual aggregator in each provider package.
- Use request metadata in tooling, documentation, admin UIs, and internal catalogs.

If a provider SDK manually scans request classes, resolves `RequestSpec`, normalizes
operation metadata, filters by request/`endpoint`, or reconstructs `resource()->method()`
through local reflection logic, consider `OperationInventory`.

## Definition <a id="section-3"></a>
`OperationInventory` is built on `RequestScanner`, `RequestSpecResolver`, and `OperationDescriptor`.

It is not a runtime layer: it does not use `ExecutionResult`, depend on transport/pipeline,
or perform I/O. It is not a provider catalog: inventory introspects request classes,
while catalogs are a separate aggregated layer of static SDK knowledge.

## Basic API <a id="section-4"></a>
- `OperationInventoryInterface`
- `OperationDescriptorView`
- `OperationInventoryBuilder`

Minimum `OperationDescriptorView` fields:
- `requestClass`
- `httpMethod`
- `endpoint`
- `responseType`
- `operationDescriptor`
- `hasDownload`
- `hasNoAuth`
- `skipCredentialsEnrichment`
- `sdkCallPaths`
- `continuationFinalType` — final DTO for an async chain (`ContinuationResult`.finalType).
- `continuationUnwrap` — final payload path inside the envelope for await.
- `pollRequestClass` — polling request class, if set in `ContinuationResult`.
- `returnsUnwrap` — synchronous payload path inside the envelope (`Returns::unwrap`).
- `resourcePath` / `resourceLabel` — resource hierarchy; see `ResourceNameResolverInterface`.

Convenience accessors:
- `title()`
- `description()`
- `note()`
- `isAsync()` — true when `ContinuationResult` is declared.

## Access <a id="section-5"></a>
From the client: `$client->operationInventory()`.

From the inventory:
- `all()`
- `forRequest(FQCN)`
- `forEndpoint('/path')`

## Example <a id="section-6"></a>
```php
$inventory = $client->operationInventory();

$all = $inventory->all();
$byRequest = $inventory->forRequest(GetHistoryRequest::class);
$byEndpoint = $inventory->forEndpoint('/rights/history');
$paths = $byRequest?->sdkCallPaths ?? [];
```

If a request class declares:
```php
#[OperationDescriptor(
    title: 'Get history',
    description: 'Returns rights history for the object.',
    note: 'Paid operation.',
)]
```

the inventory exposes these values through `operationDescriptor`, `title()`, `description()`, and `note()`.

SDK call paths:
- `sdkCallPaths` lists stable public entry paths from the client root.
- Format: `resource()->method()` or `v1()->resource()->method()`.
- The list is deduplicated and sorted lexicographically.
- Ordering does not distinguish a primary path from an alias.

Example:
```php
$operation = $client->operationInventory()->forRequest(GetByCadnumRequest::class);

// [
//     'objects()->getByCadnum()',
//     'v1()->objects()->getByCadnum()',
// ]
$paths = $operation?->sdkCallPaths ?? [];
```

## How sdkCallPaths are built <a id="section-7"></a>
`sdkCallPaths` resolves only when building inventory for a specific client:
- Through `$client->operationInventory()`.
- Through `OperationInventoryBuilder::buildForClient(...)`.

For `OperationInventoryBuilder::buildForRootNamespace(...)`, `sdkCallPaths` is always [].
Without a concrete client class, introspection cannot establish a stable SDK entry path contract.

## Supported patterns <a id="section-8"></a>
- Public client/resource/router methods with declared return types.
- client → resource → request chains.
- Public aliases returning the same request class.
- Version shortcuts such as `v1()`, `v2()`, and `v3()`.
- Version-aware `requestByVersion([...])` / `resourceByVersion([...])` routing when the
  path can be narrowed statically to a concrete request class.

## Unsupported patterns <a id="section-9"></a>
- Parameterized selectors such as `useVersion(...)` as stable paths.
- `__call` magic.
- Methods without declared return types.
- Chains built only at runtime.
- Methods returning `ResultHandle`, `ResolvedResultInterface`, DTOs, scalars, or other non-request entry points.

## Restrictions <a id="section-10"></a>
- Inventory uses the **declarative request spec**, not runtime overrides.
- If `resolveEndpoint()` changes a request `endpoint`, inventory shows the declarative attribute `endpoint`.
- `sdkCallPaths` also describes declarative introspection, not runtime overrides.
- A version-dependent path is not exported if runtime context is needed to narrow it to one request class.
- Inventory does not replace provider catalogs.
- Inventory does not construct a resource tree as a first-class model.

## Choosing inventory or a catalog <a id="section-11"></a>
Use `OperationInventory` for:
- A general snapshot of all SDK request operations.
- Introspection by method/`endpoint`/request class.
- Stable SDK call paths for a request class.
- A DX/tooling layer over request classes.

Use `ProviderCatalog*` for:
- Pricing catalogs.
- Capability catalogs.
- Operation descriptor catalogs as independent static datasets.
- Request-bound or domain-bound knowledge that cannot be reduced to one `RequestSpec`.
