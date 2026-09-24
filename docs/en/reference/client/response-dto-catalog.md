<!-- languages --> <a href="response-dto-catalog.md">English</a> · <a href="../../../ru/reference/client/response-dto-catalog.md">Русский</a> <!-- /languages -->
# Response DTO catalog <a id="section-1"></a>

## Response DTO catalog for a mega-client <a id="section-2"></a>
For one list of every DTO returned by a mega-client's services (sync through Returns,
async-final through `ContinuationResult`, downloads through Download), apisutra provides
a multi-service layer over [ResponseDtoCatalog](response-dto-catalog.md#section-9).

### Factory (canonical approach) <a id="section-3"></a>
```php
use ApiSutra\OperationInventory\Catalog\MultiServiceResponseDtoCatalogFactory;

$catalog = (new MultiServiceResponseDtoCatalogFactory())->fromMultiService($mega);

foreach ($catalog->usages() as $dtoClass => $usages) {
    foreach ($usages as $usage) {
        $usage->serviceClass;   // Service client FQCN (e.g., RealtyClient::class)
        $usage->resourceLabel;  // 'Reports / Tasks' within the service
        $usage->kind;           // ResponseDtoKind::Sync | AsyncFinal | Download
    }
}
```

### Unified traversal across different providers <a id="section-4"></a>

Consumers type the completed catalog through `ResponseDtoCatalogProviderInterface`;
see “Unified contract” below for its contract and restrictions.

### Trait (mega-client convenience) <a id="section-5"></a>
Add the trait to obtain `$mega->responseDtoCatalog()` without boilerplate:

```php
use ApiSutra\Contracts\Interfaces\Core\MultiServiceClientInterface;
use ApiSutra\Traits\ProvidesMultiServiceResponseDtoCatalogTrait;

final class MegaClient implements MultiServiceClientInterface
{
    use ProvidesMultiServiceResponseDtoCatalogTrait;

    public function services(): array { /* ... */ }
}

$catalog = $mega->responseDtoCatalog();   // cached on this instance
```

The trait is only a thin factory wrapper. `MultiServiceClientInterface` intentionally
retains one method; this trait is a DX layer, not part of that contract.

### File export <a id="section-6"></a>
The built-in `MarkdownResponseDtoCatalogExporter` detects multi-service mode from
`serviceClass` in usages and:

- Adds a `## Сервисы` section (the export's literal heading) with `Sync` / `AsyncFinal` /
  Download counts per service.
- Adds a `Service` column to all tables: resources, per-DTO usages, and downloads.

Single-client output, where no usage has `serviceClass`, remains completely unchanged.

```php
use ApiSutra\OperationInventory\Catalog\Export\MarkdownResponseDtoCatalogExporter;
use ApiSutra\OperationInventory\Catalog\Export\ResponseDtoCatalogWriter;

$writer = new ResponseDtoCatalogWriter([
    new MarkdownResponseDtoCatalogExporter(),
]);

$writer->writeTo('/path/dto-catalog.md', $mega->responseDtoCatalog());
```

### Custom service labels <a id="section-7"></a>
If the short class name, such as `RealtyClient`, is unsuitable for headings, supply
your own resolver to the exporter:

```php
use ApiSutra\Contracts\Interfaces\Inventory\ServiceLabelResolverInterface;

$resolver = new class implements ServiceLabelResolverInterface {
    public function resolve(string $serviceClass): string
    {
        return match ($serviceClass) {
            RealtyClient::class => 'Realty',
            TaxClient::class    => 'Tax',
            default             => $serviceClass,
        };
    }
};

$exporter = new MarkdownResponseDtoCatalogExporter($resolver);
```

The label is not added to `ResponseDtoUsage`, which stores the raw FQCN; renaming affects
presentation only.

### Internal behavior <a id="section-8"></a>
- `MultiServiceResponseDtoCatalogFactory` gathers `operationInventory()` from every
  service client and combines them in `CompositeOperationInventory`.
- `serviceClass` is set on every `ResponseDtoUsage` using a `requestClass → serviceClass`
  map built in one inventory pass.
- If several services share a `requestClass`, the first in `services()` order wins deterministically.
- Services remain isolated: resource groups are not merged across services. Namespace
  heuristics automatically separate `Reports` in `RealtyClient` from `Reports` in `TaxClient`.

## ResponseDtoCatalog <a id="section-9"></a>
The response DTO catalog is a thin introspection layer over `OperationInventory`,
answering which DTOs the SDK actually returns.

### Catalog contents <a id="section-10"></a>
- `Sync` DTOs from Returns(...) or Returns(type: ...).
- Async-final DTOs from `ContinuationResult`(finalType: ...).
- Download responses (`FileResponse`) from requests with Download.

If Returns declares type, it takes priority in the catalog. A request without Returns,
`ContinuationResult`, or Download is omitted from the catalog but remains in ordinary `OperationInventory`.

### Access <a id="section-11"></a>
From the client:
```php
$catalog = $client->responseDtoCatalog();
```

Or manually:
```php
use ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog;

$catalog = new ResponseDtoCatalog($client->operationInventory());
```

### API <a id="section-12"></a>
- `listAllDtoClasses(includeDownload = false): array<int, class-string>`
- `listSyncDtoClasses(): array<int, class-string>`
- `listAsyncFinalDtoClasses(): array<int, class-string>`
- `listDownloadResponseClasses(): array<int, class-string>`
- `usages(): array<class-string, array<int, ResponseDtoUsage>>`
- `usagesFor(class-string): array<int, ResponseDtoUsage>`
- `inventory(): OperationInventoryInterface`

Download is intentionally excluded from `listAllDtoClasses()` by default to avoid
mixing DTOs with `FileResponse`. Include it explicitly with `includeDownload: true` or
use `listDownloadResponseClasses()`.

### ResponseDtoUsage <a id="section-13"></a>
Every catalog entry is `ResponseDtoUsage` with all fields required by exporters:

- `requestClass`
- `resourcePath: array<int, string>|null`
- `resourceLabel: string|null`
- `httpMethod: HttpMethod|null`
- `endpoint: string|null`
- `title: string|null` / `description: string|null` (from `OperationDescriptor`)
- `responseClass: class-string` (sync DTO / async-final DTO / `FileResponse`)
- `kind: ResponseDtoKind` — `Sync / AsyncFinal / Download`
- `pollRequest: class-string|null` (`AsyncFinal` only)
- `unwrap: string|null` (`Returns::unwrap` or `ContinuationResult::unwrap`)

All necessary data is stored directly in `ResponseDtoUsage`; exporters need not join
with `OperationInventory`.

### Resource grouping <a id="section-14"></a>
A resource is the natural top-level SDK group; real providers often have **nested** resources:

```
\Resources\Reports\Tasks\Requests\CreateByFio\CreateByFioRequest
```

This is the path `['Reports', 'Tasks']`, not a single `Reports` segment.

The resolver is `ResourceNameResolverInterface`:
- Default `ResourceNameResolver` uses a path heuristic between `\Resources\` and `\Requests\` segments.
- A provider may register a custom resolver when constructing `OperationInventoryBuilder`.
- If no path can be determined, `resourcePath` and `resourceLabel` are both null.

`OperationInventory::all()` remains flat: each item stores its `resourcePath`/`resourceLabel`;
the hierarchy is a rendered view, not the underlying data structure.

### File export <a id="section-15"></a>
The catalog knows nothing about formatting. A separate strategy/adapter layer
encapsulates export; new formats require no catalog changes.

Contract:
```php
interface ResponseDtoCatalogExporterInterface
{
    public function format(): string;        // 'md', 'json', 'openapi', ...
    public function export(ResponseDtoCatalog $catalog): string;
}
```

Built-in: `MarkdownResponseDtoCatalogExporter` (format: md).

Writing:
```php
use ApiSutra\OperationInventory\Catalog\Export\MarkdownResponseDtoCatalogExporter;
use ApiSutra\OperationInventory\Catalog\Export\ResponseDtoCatalogWriter;

$writer = new ResponseDtoCatalogWriter([
    new MarkdownResponseDtoCatalogExporter(),
]);

$writer->writeTo('/path/dto-catalog.md', $client->responseDtoCatalog());
```

`writeTo` behavior:
- If format is omitted, infer it from the path extension.
- No extension and no explicit format causes `ConfigurationException`.
- No registered exporter for the format causes `ConfigurationException`.

A custom SDK format:
```php
final readonly class JsonResponseDtoCatalogExporter implements ResponseDtoCatalogExporterInterface
{
    public function format(): string { return 'json'; }

    public function export(ResponseDtoCatalog $catalog): string
    {
        return json_encode([
            'all'   => $catalog->listAllDtoClasses(),
            'sync'  => $catalog->listSyncDtoClasses(),
            'async' => $catalog->listAsyncFinalDtoClasses(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

$writer = new ResponseDtoCatalogWriter([
    new MarkdownResponseDtoCatalogExporter(),
    new JsonResponseDtoCatalogExporter(),
]);

$writer->writeTo('/path/dto-catalog.json', $client->responseDtoCatalog());
```

### Async and sync on one request <a id="section-16"></a>
If a request declares both `Returns(EnvelopeDto::class)` and `ContinuationResult(finalType: FinalDto::class)`:

- `EnvelopeDto::class` appears as `Sync`.
- `FinalDto::class` appears as `AsyncFinal`.
- Both enter `listAllDtoClasses()`.
- `usages()` contains a separate `ResponseDtoUsage` for each DTO.

### Polling requests <a id="section-17"></a>
A polling request is an independent request class with its own Returns and is found
by the normal inventory pass. In the catalog it:

- Appears through its own Returns DTOs as `Sync` usages.
- Also appears in `pollRequest` on the initial request's `AsyncFinal` usage.

### Unified ResponseDtoCatalogProviderInterface contract <a id="section-18"></a>
Every catalog provider, single client or mega-client, implements one contract:

```php
interface ResponseDtoCatalogProviderInterface
{
    public function responseDtoCatalog(): ResponseDtoCatalog;
}
```

Implemented by:

- `AbstractClient` — single-client catalog, with `serviceClass` = null in usages.
- A mega-client using `ProvidesMultiServiceResponseDtoCatalogTrait` — multi-service
  catalog, with `serviceClass` set on every usage.

This enables **unified polymorphic traversal** of different providers:

```php
/** @var array<int, ResponseDtoCatalogProviderInterface> $providers */
$providers = [$realtyClient, $kontur, $tax];

foreach ($providers as $provider) {
    foreach ($provider->responseDtoCatalog()->usages() as $dtoClass => $usages) {
        // ...
    }
}
```

For **one combined catalog** over arbitrary providers rather than iteration over their catalogs:

```php
$catalog = (new MultiServiceResponseDtoCatalogFactory())->merge(
    $realtyClient,   // single AbstractClient
    $kontur,         // mega-client
    $tax,            // mega-client
);
```

`merge()` expands mega-clients through `services()` and assigns `serviceClass` to every usage.

### Multi-service usage (mega-client) <a id="section-19"></a>

Construction through `MultiServiceResponseDtoCatalogFactory` and the optional trait
is shown at the start of this page; it uses the same filtering and export rules.

[Provider catalogs](catalogs.md) · [Operation inventory](operation-inventory.md).
