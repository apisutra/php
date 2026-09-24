<!-- languages --> <a href="../../en/glossary/pagination.md">English</a> · <a href="pagination.md">Русский</a> <!-- /languages -->
# Пагинация <a id="section-1"></a>

| Термин | Значение | Подробнее |
| --- | --- | --- |
| <a id="paginableinterface"></a> PaginableInterface | Интерфейс для запросов с поддержкой пагинации. | [Контракт](../reference/execution/pagination.md) |
| <a id="pagination-attribute"></a> Pagination (атрибут) | Атрибут конфигурации пагинации на запросе: пути данных и метаданных, параметры страницы, тип и коллекция элементов. | [Контракт](../reference/execution/pagination.md) |
| <a id="paginationconfig"></a> PaginationConfig | Конфиг пагинации на уровне клиента. | [Контракт](../reference/execution/pagination.md) |
| <a id="paginationrule"></a> PaginationRule | Неизменяемая выборка, стратегия ошибок и конкурентность страниц (default 1). | [Контракт](../reference/execution/pagination.md) |
| <a id="paginationmode"></a> PaginationMode | Enum режима пагинации. | [Контракт](../reference/execution/pagination.md) |
| <a id="paginationmeta"></a> PaginationMeta | Implements ResultMeta. | [Контракт](../reference/execution/pagination.md) |
| <a id="paginationmetaresolverinterface"></a> PaginationMetaResolverInterface | Контракт для извлечения метаданных пагинации из ответа провайдера. | [Контракт](../reference/execution/pagination.md) |
| <a id="paginationmetaoverrideinterface"></a> PaginationMetaOverrideInterface | Интерфейс для запросов, которые сами извлекают мету и хотят переопределить стандартный резолв. | [Контракт](../reference/execution/pagination.md) |
| <a id="paginationitemscontainerinterface"></a> PaginationItemsContainerInterface | Контракт контейнера с items, нужен для DTO‑обёрток пагинированных ответов. | [Контракт](../reference/execution/pagination.md) |
| <a id="abstractpaginationcontainerdto"></a> AbstractPaginationContainerDto | Базовый DTO‑контейнер с items() / withItems() для пагинации. | [Контракт](../reference/execution/pagination.md) |
| <a id="paginatedresult"></a> PaginatedResult | Результат пагинации с aggregated items/pages и метой. | [Контракт](../reference/execution/pagination.md) |

[Все термины](README.md).
