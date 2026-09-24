<!-- languages --> <a href="catalogs.md">English</a> · <a href="../../../ru/reference/client/catalogs.md">Русский</a> <!-- /languages -->
# Static provider catalogs <a id="section-1"></a>

## Purpose <a id="section-2"></a>
Use this layer when an SDK must expose **known provider reference data** useful for
developer experience and application logic, in addition to runtime request results.

Typical scenarios:
- Show available provider operations in an application.
- Expose a pricing/tariff catalog without a network call.
- Store method capabilities (`supportsAsync`, formats, restrictions).
- Keep static dictionaries and schema descriptors alongside SDK code.
- Bind a catalog to request classes when the knowledge belongs to an endpoint.

Data that **does not come from a particular HTTP response**, but must be exposed
consistently and predictably by the SDK, is a good candidate for a provider catalog.

## Definition <a id="section-3"></a>
A static provider catalog contains prepared SDK data that:
- Does not depend on a particular `ExecutionResult`.
- Is not extracted from HTTP responses.
- Performs no I/O.
- Lives in the provider SDK as part of its DX layer.

Examples include pricing/tariff catalogs, capability catalogs, operation descriptors,
static dictionaries, and catalogs bound to specific request classes.

## Difference from runtime metadata <a id="section-4"></a>
**Runtime metadata:**
- Source: `ExecutionResult`.
- Tool: `ResultMetaExtractorInterface`.
- Purpose: technical metadata for one request execution.

**Static catalog:**
- Source: the SDK itself.
- Tool: `ProviderCatalogInterface` + `ProviderCatalogRegistryInterface`.
- Purpose: a read-only layer of provider knowledge.

Keep these layers separate.

## Basic contracts <a id="section-5"></a>
- `ProviderCatalogMetaInterface`
- `ProviderCatalogInterface`
- `RequestBoundProviderCatalogInterface`
- `ProviderCatalogRegistryInterface`

Minimum catalog metadata:
- `generatedAt(): DateTimeImmutable`
- `source(): ?string`
- `sourceVersion(): ?string`

`generatedAt` is required.

## Invariants <a id="section-6"></a>
- The catalog performs no I/O.
- It does not depend on transport, pipeline, or result lifecycle.
- It is read-only.
- The core does not know billing/capability semantics; these belong to the provider package.

## Wiring <a id="section-7"></a>
Connect through `ClientConfig::providerCatalogRegistry`.

Access:
- `$client->providerCatalogs()`
- `$client->providerCatalog('pricing')`
- `$client->providerCatalogsForRequest(SomeRequest::class)`

The core intentionally leaves `ClientInterface` unchanged; catalog access remains a
DX method on `AbstractClient`.

## Version 1 scope <a id="section-8"></a>
Included:
- Basic catalog contracts.
- A read-only registry.
- Optional `ClientConfig` wiring.
- `AbstractClient` DX accessors.

Excluded:
- Network synchronization/lazy loading.
- Billing semantics in the core.
- Mandatory Laravel/container integration.
- A registrar/discovery layer for multi-service catalogs.

These can be added separately if a concrete need arises.

## Provider implementation example <a id="section-9"></a>
```php
use ApiSutra\Catalog\ProviderCatalogMeta;
use ApiSutra\Catalog\ProviderCatalogRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Catalog\ProviderCatalogInterface;
use ApiSutra\Contracts\Interfaces\Catalog\ProviderCatalogMetaInterface;
use DateTimeImmutable;

final readonly class PricingCatalog implements ProviderCatalogInterface
{
    public function key(): string
    {
        return 'pricing';
    }

    public function meta(): ProviderCatalogMetaInterface
    {
        return new ProviderCatalogMeta(
            generatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            source: 'provider-docs',
            sourceVersion: '2026-01-01',
        );
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    providerCatalogRegistry: new ProviderCatalogRegistry([
        new PricingCatalog(),
    ]),
);
```

## When a request-bound catalog is appropriate <a id="section-10"></a>
Use `RequestBoundProviderCatalogInterface` when the catalog naturally belongs to requests:
- Operation descriptors by request class.
- Capability matrices for specific methods.
- Request-specific schema catalogs.

`ProviderCatalogInterface` is sufficient for a catalog shared across the SDK.

## What this does not replace <a id="section-11"></a>
`OperationDescriptor` and provider catalogs are different mechanisms.

- `OperationDescriptor` is request-class metadata (title, description, note).
- A provider catalog is a separate, aggregated, read-only SDK layer.

A catalog must not depend on `OperationDescriptor`; `OperationDescriptor` must not be
considered a simplified catalog.

## When not to use a catalog <a id="section-12"></a>
- A particular HTTP response's envelope/metadata.
- Continuation tokens.
- Pagination metadata.
- Debug/audit/request traces.

These remain in the runtime result layer.

For a derived response type list, use [ResponseDtoCatalog](response-dto-catalog.md).
