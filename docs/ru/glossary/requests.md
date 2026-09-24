<!-- languages --> <a href="../../en/glossary/requests.md">English</a> · <a href="requests.md">Русский</a> <!-- /languages -->
# Запросы <a id="section-1"></a>

| Термин | Значение | Подробнее |
| --- | --- | --- |
| <a id="httpmethod"></a> HttpMethod | HTTP-метод операции: GET, POST, PUT, PATCH или DELETE. | [Контракт](../reference/request/declaration.md) |
| <a id="requestinterface"></a> RequestInterface | Публичный контракт SDK-запроса, реализованный AbstractRequest. | [Контракт](../reference/request/declaration.md) |
| <a id="abstractrequest"></a> AbstractRequest | Базовый класс для всех запросов. | [Контракт](../reference/request/declaration.md) |
| <a id="body-dto"></a> Body DTO | В body можно передавать DTO (input‑контракт). | [Контракт](../reference/request/declaration.md) |
| <a id="abstractresource"></a> AbstractResource | Базовый класс ресурса API. | [Контракт](../reference/client/resources.md) |
| <a id="sdk-asynchronous-sendasync"></a> Асинхронность SDK (sendAsync) | Конкурентное выполнение через Promise; штатный Guzzle совмещает HTTP и ожидания SDK. | [Контракт](../reference/execution/transport.md) |
| <a id="sendasync"></a> sendAsync() | Запускает выполнение и возвращает `ResultPromiseInterface<ResultHandle>`; wait даёт готовый результат. Fire-and-forget не поддерживается. | [Контракт](../reference/execution/transport.md) |
| <a id="deferred-result-polling"></a> Отложенная готовность результата (polling) | Сценарий, где провайдер возвращает промежуточное состояние, а финальные данные становятся доступны позже. | [Контракт](../reference/execution/continuation-await.md) |
| <a id="promiseinterface"></a> PromiseInterface | Контракт Guzzle promise, используемый API результатов; сам тип не определяет конкурентность транспорта. | [Контракт](../reference/execution/transport.md) |
| <a id="compositerequestinterface"></a> CompositeRequestInterface | Интерфейс для композитных (виртуальных) запросов. | [Контракт](../reference/request/composition.md) |
| <a id="dependsonrequestinterface"></a> DependsOnRequestInterface | Интерфейс для запросов с зависимостями. | [Контракт](../reference/request/composition.md) |
| <a id="preparedrequest"></a> PreparedRequest | Подготовленный HTTP-запрос с URL, заголовками, одним источником тела и опциями отправки. | [Контракт](../reference/execution/transport.md) |
| <a id="resolveendpoint"></a> resolveEndpoint() | Метод AbstractRequest для динамического определения endpoint. | [Контракт](../reference/serialization/uri-query.md) |
| <a id="resolvebaseurl"></a> resolveBaseUrl() | Метод AbstractRequest для переопределения базового URL. | [Контракт](../reference/serialization/uri-query.md) |
| <a id="withbaseurl"></a> withBaseUrl() | Создаёт исполнение с переопределённым baseUrl; применяется контракт изоляции назначения. | [Контракт](../reference/serialization/uri-query.md) |

[Все термины](README.md).
