<!-- languages --> <a href="architecture.md">English</a> · <a href="../../ru/glossary/architecture.md">Русский</a> <!-- /languages -->
# SDK package architecture <a id="section-1"></a>

<a id="multi-service-terminology"></a>

| Term | Meaning | Details |
| --- | --- | --- |
| <a id="provider"></a> Provider | An SDK client for a specific external API offered by a vendor. | [Contract](../reference/client/resources.md) |
| <a id="vendor"></a> Vendor | A supplier that may offer several independent API products (Realty, Tax, etc.). | [Contract](../reference/client/resources.md) |
| <a id="service"></a> Service | A logically separate client with its own global settings (`baseUrl`, `auth`, `pagination`, `serialization`). | [Contract](../reference/client/resources.md) |
| <a id="resource"></a> Resource | A group of requests within one service (for example, entity, drive, rosreestr). | [Contract](../reference/client/resources.md) |
| <a id="baserequest-per-sdk-package"></a> BaseRequest (per SDK package) | An optional common base for SDK requests; the client can be bound explicitly or found through a registered resolver. | [Contract](../reference/client/construction.md) |
| <a id="resolveclient"></a> resolveClient() | A protected AbstractRequest method that obtains the bound client or resolves it through the container. | [Contract](../reference/client/discovery.md) |
| <a id="request-client-binding"></a> Request → Client binding | A request uses an explicitly supplied client or a registered resolution mechanism; resources bind the client when creating a request. | [Contract](../reference/client/discovery.md) |
| <a id="requestoptions"></a> RequestOptions | Immutable settings for one request execution. | [Contract](../reference/request/declaration.md) |
| <a id="paginationoptions"></a> PaginationOptions | Immutable runtime settings for the page, limit, and cursor. | [Contract](../reference/execution/pagination.md) |
| <a id="requestexecution"></a> RequestExecution | A request wrapper with runtime options, returned by with*() methods. | [Contract](../reference/request/declaration.md) |
| <a id="operationinventory"></a> OperationInventory | A read-only introspection layer over SDK request classes. | [Contract](../reference/client/operation-inventory.md) |
| <a id="responsedtocatalog"></a> ResponseDtoCatalog | A thin derived introspection layer over `OperationInventory` that identifies the DTOs actually returned by the SDK. | [Contract](../reference/client/response-dto-catalog.md) |
| <a id="compositeoperationinventory"></a> CompositeOperationInventory | An aggregator over several existing `OperationInventoryInterface` instances. | [Contract](../reference/client/operation-inventory.md) |
| <a id="multiserviceresponsedtocatalogfactory"></a> MultiServiceResponseDtoCatalogFactory | A factory for a multi-service `ResponseDtoCatalog`. | [Contract](../reference/client/response-dto-catalog.md) |
| <a id="servicelabelresolverinterface"></a> ServiceLabelResolverInterface | A presentation resolver that provides a human-readable label for a service client (`KonturRealtyClient` → `"Realty"`). | [Contract](../reference/client/response-dto-catalog.md) |
| <a id="responsedtocatalogproviderinterface"></a> ResponseDtoCatalogProviderInterface | A unified contract for obtaining the response DTO catalog. | [Contract](../reference/client/response-dto-catalog.md) |

[All terms](README.md).
