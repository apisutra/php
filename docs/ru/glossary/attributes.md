<!-- languages --> <a href="../../en/glossary/attributes.md">English</a> · <a href="attributes.md">Русский</a> <!-- /languages -->
# Атрибуты <a id="section-1"></a>

| Термин | Значение | Подробнее |
| --- | --- | --- |
| <a id="attributeregistry"></a> AttributeRegistry | Сервис для регистрации связки атрибут → обработчик. | [Контракт](../reference/attributes/README.md) |
| <a id="attributecontext"></a> AttributeContext | Контекст для обработчика атрибута. | [Контракт](../reference/attributes/README.md) |
| <a id="attributecontexttype"></a> AttributeContextType | Указывает, к чему применяется обработчик атрибута: запросу или DTO. | [Контракт](../reference/attributes/README.md) |
| <a id="attributehandlerinterface"></a> AttributeHandlerInterface | Интерфейс обработчика кастомного атрибута. | [Контракт](../reference/attributes/README.md) |
| <a id="get-post-put-delete"></a> Get, Post, Put, Delete | Атрибуты HTTP-методов. | [Контракт](../reference/attributes/http.md) |
| <a id="returns"></a> Returns | Атрибут, указывающий класс DTO для десериализации ответа. | [Контракт](../reference/attributes/response.md) |
| <a id="path-query-body-header-ignore"></a> Path, Query, Body, Header, Ignore | Атрибуты для маппинга свойств запроса в HTTP. | [Контракт](../reference/attributes/request.md) |
| <a id="validate-attribute"></a> Validate (атрибут) | Атрибут для валидации свойств запроса перед отправкой. | [Контракт](../reference/client/validation.md) |
| <a id="label-attribute"></a> Label (атрибут) | Атрибут для человекочитаемого имени поля в сообщениях валидации. | [Контракт](../reference/client/validation.md) |
| <a id="about-attribute"></a> About (атрибут) | Атрибут для бизнес-описания DTO-поля в документации, анализе и export tooling. | [Контракт](../reference/attributes/hydration.md) |
| <a id="validationerror"></a> ValidationError | Value Object ошибки валидации. | [Контракт](../reference/client/validation.md) |
| <a id="validationmessages"></a> validationMessages() | Статический метод в классе запроса для кастомных сообщений валидации. | [Контракт](../reference/client/validation.md) |
| <a id="beforesend-afterresponse-beforehydrate-afterhydrate-attributes"></a> BeforeSend, AfterResponse, BeforeHydrate, AfterHydrate (атрибуты) | Атрибуты для подключения переиспользуемых классов-обработчиков к хукам жизненного цикла. | [Контракт](../reference/attributes/hooks.md) |
| <a id="queryarrayformat"></a> QueryArrayFormat | Способ кодирования массива в query: скобки, индексы, запятая или повтор имени параметра. | [Контракт](../reference/serialization/uri-query.md) |
| <a id="serializenulls"></a> serializeNulls | Параметр клиентского request-level конфига. | [Контракт](../reference/serialization/request-parts.md) |

[Все термины](README.md).
