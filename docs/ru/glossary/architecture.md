<!-- languages --> <a href="../../en/glossary/architecture.md">English</a> · <a href="architecture.md">Русский</a> <!-- /languages -->
# Архитектура SDK-пакета <a id="section-1"></a>

<a id="multi-service-terminology"></a>

| Термин | Значение | Подробнее |
| --- | --- | --- |
| <a id="provider"></a> Provider | SDK‑клиент под конкретное внешнее API вендора. | [Контракт](../reference/client/resources.md) |
| <a id="vendor"></a> Vendor | Поставщик, у которого может быть несколько независимых API‑продуктов (Realty, Tax и т.д.). | [Контракт](../reference/client/resources.md) |
| <a id="service"></a> Service | Логически отдельный клиент с собственными глобальными настройками (`baseUrl`, `auth`, `pagination`, `serialization`). | [Контракт](../reference/client/resources.md) |
| <a id="resource"></a> Resource | Группировка запросов внутри одного сервиса (например, entity, drive, rosreestr). | [Контракт](../reference/client/resources.md) |
| <a id="baserequest-per-sdk-package"></a> BaseRequest (per SDK-пакет) | Необязательная общая база запросов SDK; клиент можно привязать явно либо найти через зарегистрированный resolver. | [Контракт](../reference/client/construction.md) |
| <a id="resolveclient"></a> resolveClient() | Защищённый метод AbstractRequest для получения привязанного клиента или разрешения через контейнер. | [Контракт](../reference/client/discovery.md) |
| <a id="request-client-binding"></a> Request → Client связь | Запрос использует явно переданного клиента либо зарегистрированный механизм разрешения; ресурсы привязывают клиента при создании запроса. | [Контракт](../reference/client/discovery.md) |
| <a id="requestoptions"></a> RequestOptions | Неизменяемые настройки отдельного исполнения запроса. | [Контракт](../reference/request/declaration.md) |
| <a id="paginationoptions"></a> PaginationOptions | Неизменяемые runtime-настройки страницы, лимита и курсора. | [Контракт](../reference/execution/pagination.md) |
| <a id="requestexecution"></a> RequestExecution | Обёртка запроса с runtime-опциями; возвращается методами with*(). | [Контракт](../reference/request/declaration.md) |
| <a id="operationinventory"></a> OperationInventory | Read-only introspection-слой над request-классами SDK. | [Контракт](../reference/client/operation-inventory.md) |
| <a id="responsedtocatalog"></a> ResponseDtoCatalog | Тонкий derived introspection-слой поверх `OperationInventory`, отвечающий на вопрос «какие DTO реально возвращает SDK». | [Контракт](../reference/client/response-dto-catalog.md) |
| <a id="compositeoperationinventory"></a> CompositeOperationInventory | Агрегатор поверх нескольких готовых `OperationInventoryInterface`. | [Контракт](../reference/client/operation-inventory.md) |
| <a id="multiserviceresponsedtocatalogfactory"></a> MultiServiceResponseDtoCatalogFactory | Фабрика multi-service `ResponseDtoCatalog`. | [Контракт](../reference/client/response-dto-catalog.md) |
| <a id="servicelabelresolverinterface"></a> ServiceLabelResolverInterface | Презентационный резолвер: даёт человекочитаемый label для сервис-клиента (`KonturRealtyClient` → `"Realty"`). | [Контракт](../reference/client/response-dto-catalog.md) |
| <a id="responsedtocatalogproviderinterface"></a> ResponseDtoCatalogProviderInterface | Унифицированный контракт «отдай мне каталог response DTO». | [Контракт](../reference/client/response-dto-catalog.md) |

[Все термины](README.md).
