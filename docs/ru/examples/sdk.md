<!-- languages --> <a href="../../en/examples/sdk.md">English</a> · <a href="sdk.md">Русский</a> <!-- /languages -->
# Учебный Records SDK <a id="section-1"></a>

Records — небольшой запускаемый пример **для авторов SDK**. Он показывает, как один
клиент, запрос и набор DTO образуют Composer-пакет для обычного PHP и Laravel.
Используйте его как образец или перенесите нужные части в своё пространство имён.
Приложению с готовым SDK провайдера устанавливать этот пример не требуется.

Сервис вымышленный: пример использует локальные ответы и не отправляет HTTP-запросы.
Ключи доступа, сервер, БД и воркер очереди не нужны.

## Какой API мы описываем <a id="api"></a>

| Часть | Контракт примера |
| --- | --- |
| Операция | GET /records/{id}; id — параметр пути |
| Успех | Объект data содержит record_id, title, created_at и необязательный вложенный author.name |
| DTO | Целочисленный ID, строковый title, дата DateTimeImmutable, nullable authorName/description; неизвестные поля в _extra |
| Ошибка | HTTP 404; тот же скрипт читает её через resolved() |
| Авторизация | Необязательный Bearer token; локальный пример работает без него |

[Фикстуры](../../example/sdk/fixtures/record.json) задают этот учебный контракт,
а не описывают реального провайдера. В SDK один ресурс и одна операция;
дополнительные механизмы разобраны в [тематических примерах](README.md).

## В каком порядке изучать <a id="learning-path"></a>

1. Запустите пример ниже и посмотрите на полученный DTO и ошибку.
2. Пройдите по цепочке run.php → клиент → ресурс → запрос → DTO ответа в таблице исходников.
3. Замените URL, операцию и маппинг на подтверждённые ответы своего API.
   ClientConfigFactory задаёт общие настройки времени выполнения; create() создаёт standalone-конфиг.
4. Установите те же исходники как пакет, затем по
   [руководству автора Laravel SDK](https://github.com/apisutra/laravel/blob/master/docs/ru/guides/sdk/laravel.md)
   добавьте настройки фреймворка и DI. Второй клиент, набор DTO или bridge-пакет не нужны.

## Установить как пакет <a id="section-2"></a>

`example/records-sdk` — локальный образец, не опубликованный в Packagist.
В приложении с установленной ApiSutra подходящей версии выполните:

```bash
composer config repositories.records-example path vendor/apisutra/php/docs/example/sdk
composer require "example/records-sdk:@dev"
```

Для checkout вместо пути в vendor укажите абсолютный путь к его `docs/example/sdk`.
Репозиторий Composer задаётся в приложении. Классы SDK будут подключены его собственным
PSR-4; в Laravel автоматически подключится provider. Настройки, необязательный publish
и DI описаны в [руководстве потребителя](https://github.com/apisutra/laravel/blob/master/docs/ru/guides/integration/laravel.md).
При создании настоящего SDK замените демонстрационное имя, namespace и данные API.

## Запуск <a id="section-3"></a>

Из checkout ApiSutra после `composer install`:

```bash
php docs/example/sdk/run.php
```

Из приложения с установленным пакетом:

```bash
php vendor/apisutra/php/docs/example/sdk/run.php
```

Если пример установлен отдельным пакетом:

```bash
php vendor/example/records-sdk/run.php
```

Ожидаемый результат:

```json
{"id":7,"title":"Первая запись","createdAt":"2026-09-15T10:30:00+00:00","authorName":"Анна","description":null,"extra":{"future_flag":false},"failed":true,"status":404}
```

## Один SDK, три окружения <a id="environments"></a>

| Окружение | Как использовать те же классы |
| --- | --- |
| Обычный PHP | Создать DemoClient явно с ClientConfigFactory::create() и транспортом; это показывает run.php |
| Laravel без apisutra/laravel | Та же явная сборка или run.php; discovery и config:cache безопасны, автоматический DI клиента/запроса SDK сообщает, как установить адаптер |
| Laravel с apisutra/laravel | Discovery регистрирует клиент и запросы; доступны app(DemoClient::class) и внедрение зависимостей с настройками приложения |

SDK требует apisutra/php и только **рекомендует** apisutra/laravel через suggest.
Composer не устанавливает suggest автоматически. Адаптер устанавливает приложение,
когда ему нужна Laravel-интеграция. SDK исключительно для Laravel может вместо этого
потребовать адаптер через require. Ни одному варианту не нужен отдельный bridge-пакет SDK.

Laravel-фабрика использует ClientConfigFactory::defaults() через ClientConfigFactory
адаптера. Значения debug/environment приложения читаются при создании;
containerProvider остаётся null, поэтому конфигурация не закрепляет Application.
Приложение сохраняет возможность явно задать клиент, транспорт и уже связанный запрос.

## Исходники <a id="section-4"></a>

| Файл | Назначение |
| --- | --- |
| [composer.json](../../example/sdk/composer.json) | Установка, PSR-4 и Laravel package discovery |
| [config/records.php](../../example/sdk/config/records.php) | Defaults, environment и необязательный publish |
| [bootstrap.php](../../example/sdk/bootstrap.php) | Автозагрузка пространства имён примера |
| [run.php](../../example/sdk/run.php) | Явная сборка, два вызова и чтение результата |
| [DemoClient](../../example/sdk/src/DemoClient.php) | Вход `records()` |
| [ClientConfigFactory](../../example/sdk/src/Config/ClientConfigFactory.php) | Общие настройки времени выполнения и создание standalone-конфига |
| [HydrationConfigFactory](../../example/sdk/src/Config/HydrationConfigFactory.php) | Строгие типы и сбор неизвестных полей в `_extra` |
| [RecordsResource](../../example/sdk/src/Resources/Records/RecordsResource.php) | Создание привязанного запроса |
| [GetRecordRequest](../../example/sdk/src/Resources/Records/Get/GetRecordRequest.php) | GET, параметр пути, типизированный ответ и повторы при временных ошибках |
| [GetRecordResponseDto](../../example/sdk/src/Resources/Records/Get/GetRecordResponseDto.php) | Наследник AbstractResponseDto: From с fallback и вложенным путём, DateTimeFrom, EmptyStringAsNull |
| [RecordDto с атрибутом](../../example/sdk/src/AttributeExample/RecordDto.php) | Создание DTO через from() без клиента и внешних правил |
| [Laravel provider](../../example/sdk/src/Laravel/DemoServiceProvider.php) | Ленивый клиент, overrides и регистрация запросов |
| [LaravelClientConfigFactory](../../example/sdk/src/Laravel/LaravelClientConfigFactory.php) | Defaults приложения и auth без закрепления контейнера |
| [Успех](../../example/sdk/fixtures/record.json), [ошибка](../../example/sdk/fixtures/error.json) | Вымышленные локальные ответы |

`run.php` не загружает Laravel и не обращается к сети. Проверки выполняют именно этот
опубликованный файл, в том числе после установки без dev-зависимостей. Установка проверяется
во всех трёх окружениях; для Laravel дополнительно проверяются discovery, запрос раньше клиента,
overrides, публикация, кеш конфигурации и два экземпляра Application. Исходники SDK общие
для репозиториев; Laravel-проверки используют выбранную ревизию ядра.

## Использовать как основу <a id="section-5"></a>

Скопируйте нужные классы в namespace своего SDK и зарегистрируйте PSR-4 в Composer.
Замените фикстуры подтверждёнными ответами API. [Quickstart](https://github.com/apisutra/php/blob/master/docs/ru/guides/quickstart.md)
объясняет путь исполнения, а [создание SDK](https://github.com/apisutra/php/blob/master/docs/ru/start/create-sdk.md) задаёт полный
порядок расширения. Для реального транспорта следуйте
[standalone-подключению](https://github.com/apisutra/php/blob/master/docs/ru/guides/integration/standalone.md).
Для поставки Laravel provider следуйте [маршруту автора SDK](https://github.com/apisutra/laravel/blob/master/docs/ru/guides/sdk/laravel.md).
