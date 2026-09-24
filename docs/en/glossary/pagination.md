<!-- languages --> <a href="pagination.md">English</a> · <a href="../../ru/glossary/pagination.md">Русский</a> <!-- /languages -->
# Pagination <a id="section-1"></a>

| Term | Meaning | Details |
| --- | --- | --- |
| <a id="paginableinterface"></a> PaginableInterface | An interface for requests that support pagination. | [Contract](../reference/execution/pagination.md) |
| <a id="pagination-attribute"></a> Pagination (attribute) | A request-level pagination configuration attribute: data and metadata paths, page parameters, item type, and collection. | [Contract](../reference/execution/pagination.md) |
| <a id="paginationconfig"></a> PaginationConfig | Client-level pagination configuration. | [Contract](../reference/execution/pagination.md) |
| <a id="paginationrule"></a> PaginationRule | Immutable selection, failure strategy and page concurrency (default 1). | [Contract](../reference/execution/pagination.md) |
| <a id="paginationmode"></a> PaginationMode | The pagination mode enum. | [Contract](../reference/execution/pagination.md) |
| <a id="paginationmeta"></a> PaginationMeta | Implements ResultMeta. | [Contract](../reference/execution/pagination.md) |
| <a id="paginationmetaresolverinterface"></a> PaginationMetaResolverInterface | A contract for extracting pagination metadata from the provider's response. | [Contract](../reference/execution/pagination.md) |
| <a id="paginationmetaoverrideinterface"></a> PaginationMetaOverrideInterface | An interface for requests that extract metadata themselves and override standard resolution. | [Contract](../reference/execution/pagination.md) |
| <a id="paginationitemscontainerinterface"></a> PaginationItemsContainerInterface | A container contract with items, required for DTO wrappers of paginated responses. | [Contract](../reference/execution/pagination.md) |
| <a id="abstractpaginationcontainerdto"></a> AbstractPaginationContainerDto | A base DTO container with items() / withItems() for pagination. | [Contract](../reference/execution/pagination.md) |
| <a id="paginatedresult"></a> PaginatedResult | A pagination result with aggregated items/pages and metadata. | [Contract](../reference/execution/pagination.md) |

[All terms](README.md).
