<!-- languages --> <a href="../../en/guides/troubleshooting.md">English</a> · <a href="troubleshooting.md">Русский</a> <!-- /languages -->
# Диагностика по симптомам <a id="section-1"></a>

Краткие решения частых проблем.

## Клиент не установлен для запроса <a id="section-2"></a>
**Причина:** запрос отправляется без клиента и без `ClientResolver`.
**Решение:** вызвать `$request->setClient($client)` или настроить резолвер через контейнер.

## Auth scope не найден <a id="section-3"></a>
**Причина:** используется `#[AuthScope]`, но scope отсутствует в `ClientConfig.authScopes`.
**Решение:** добавить scope в конфиг или изменить атрибут.

## Auth включен, но auth не настроен <a id="section-4"></a>
**Причина:** вызван `withAuth()`/`forceAuth()` или задан `AuthPolicy`, но `auth` не задан.
**Решение:** задать `ClientConfig.auth` или убрать принудительное включение.

## Неожиданный результат union DateTime/string <a id="section-5"></a>
**Симптом:** поле `string|DateTimeInterface` имеет неожиданное сериализованное представление.
**Причина:** ветка union выбирается по runtime-значению, а не по первому объявленному типу.
`DateTimeCast::serialize()` принимает только `DateTimeInterface`; DX и wire serialization
могут использовать разные политики.
**Решение:** проверьте тип поля, `DateTimeFrom` / `DateTimeTo`, `DtoSerializationProfile`
и при необходимости `ClientConfig.requestDateTime` / `wireBodySerializationPolicy`.

## Запрос не поддерживает пагинацию <a id="section-6"></a>
**Причина:** `paginate()` вызван на запросе без `PaginableInterface`.
**Решение:** наследоваться от `AbstractPaginatedRequest`.

## DTO‑контейнер пагинации не реализует интерфейс <a id="section-7"></a>
**Причина:** для paginated запроса задан `#[Returns]`, но DTO не реализует
`PaginationItemsContainerInterface`.
**Решение:** реализовать интерфейс или убрать `#[Returns]` для этого запроса.

## Файлы не скачиваются <a id="section-8"></a>
**Причина:** нет `#[Download]` или ответ не является файловым.
**Решение:** добавить `#[Download]` и использовать `FileResponse`.

## MultipartStream недоступен <a id="section-9"></a>
**Причина:** отсутствует `guzzlehttp/psr7` для multipart.
**Решение:** установить `guzzlehttp/psr7`.

## Валидация DTO не срабатывает <a id="section-10"></a>
**Причина:** нет доступного validator‑factory.
**Решение:** настроить `ContainerProvider` или вызвать `Validator::useFactory()`.

## Незамоканный запрос в тестах <a id="section-11"></a>
**Причина:** включён `preventStrayRequests()` и нет фикстуры/мока.
**Решение:** добавить `fake()`/фикстуру или отключить защиту.

## Rate Limit не разделяется между процессами <a id="section-12"></a>
**Причина:** стандартный backend принадлежит экземпляру клиента.
**Решение:** для атомарного совместного учёта подключите [Redis backend](../reference/integrations/redis.md)
с одинаковым scope и сервером у workers. PSR-16 store сохранён для одиночной квоты,
но его get/set не гарантируют атомарность.

В Laravel provider подключается package discovery без обязательной публикации конфига.
Обычный DI сохраняет заданные значения SDK-запроса; перенос входящих HTTP-данных
выполняется явной RequestFactory. Пользовательские bindings имеют приоритет.
[Подключение и тестирование](https://github.com/apisutra/laravel/blob/master/docs/ru/guides/integration/laravel.md).
