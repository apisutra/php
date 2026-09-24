<!-- languages --> <a href="../../en/guides/quickstart.md">English</a> · <a href="quickstart.md">Русский</a> <!-- /languages -->
# Первый запрос <a id="section-1"></a>

За несколько минут выполните запрос через учебный SDK: конфигурация → транспорт →
клиент → ресурс → запрос → DTO или ошибка. Нужен PHP 8.4+ и Composer.

## Запуск опубликованного примера <a id="section-2"></a>

В проекте потребителя:

```bash
composer require apisutra/php
php vendor/apisutra/php/docs/example/sdk/run.php
```

В checkout ApiSutra:

```bash
composer install
php docs/example/sdk/run.php
```

Обе команды выполняют [один и тот же файл](../../example/sdk/run.php). Он использует
локальные фикстуры и `MockTransport`: ключи API и сетевой доступ для запуска не нужны.
Результат:

```json
{"id":7,"title":"Первая запись","createdAt":"2026-09-15T10:30:00+00:00","authorName":"Анна","description":null,"extra":{"future_flag":false},"failed":true,"status":404}
```

## Как устроен пример <a id="section-3"></a>

1. [ClientConfigFactory](../../example/sdk/src/Config/ClientConfigFactory.php) задаёт
   `baseUrl` и подключает правила DTO.
2. [DemoClient](../../example/sdk/src/DemoClient.php) принимает конфигурацию и транспорт
   через конструктор `AbstractClient`. Созданная конфигурация действительно используется.
3. [RecordsResource](../../example/sdk/src/Resources/Records/RecordsResource.php)
   возвращает привязанный к клиенту запрос.
4. [GetRecordRequest](../../example/sdk/src/Resources/Records/Get/GetRecordRequest.php)
   объявляет GET, параметр пути, `Returns` с `unwrap: 'data'` и
   [повторы при временных ошибках](../reference/execution/retry.md) через `Retry`.
5. [GetRecordResponseDto](../../example/sdk/src/Resources/Records/Get/GetRecordResponseDto.php)
   наследует `AbstractResponseDto`: `From` берёт `record_id` или запасной `id`,
   читает имя из `author.name`, а `From` и `DateTimeFrom` преобразуют `created_at`
   в `DateTimeImmutable`. `EmptyStringAsNull(blank: true)` заменяет пустое или
   состоящее из пробелов описание на `null`.
   [Правила](../../example/sdk/src/Config/HydrationConfigFactory.php) проверяют строгие скаляры
   и сохраняют неизвестные поля в `_extra`; маппинг свойств остаётся в атрибутах.
6. `dataOrFail()` возвращает DTO или выбрасывает исключение. Второй вызов использует
   `resolved()` и показывает проверку HTTP-ошибки без извлечения данных.

Исходники лежат рядом с пояснениями; их можно скопировать в собственный SDK и
зарегистрировать свой namespace в Composer. `bootstrap.php` нужен только для
автозагрузки учебного пространства имён.

## Перейти к своему API <a id="section-4"></a>

Замените базовый URL, путь запроса и форму DTO по подтверждённому ответу API.
Для настоящего HTTP передайте настроенный транспорт; у него есть собственные
зависимости, ограничения timeout и redirects. См. [standalone-подключение](integration/standalone.md).

В Laravel конфигурация SDK задаётся явным binding клиента; `app(DemoClient::class)`
сам по себе не использует локальную переменную `$config`.
[Полный путь регистрации](https://github.com/apisutra/laravel/blob/master/docs/ru/guides/integration/laravel.md) использует опубликованный service provider.

## Продолжить <a id="section-5"></a>

- [Создать SDK целиком](../start/create-sdk.md) — факты API, проектирование и расширение покрытия.
- [Добавить операцию](../start/add-operation.md) — следующий запрос и его тест.
- [Выбрать модель DTO](../start/describe-dto.md) — внешние правила или атрибуты.
- [Исходники учебного SDK](../examples/sdk.md) — дерево, запуск и ожидаемый результат.
