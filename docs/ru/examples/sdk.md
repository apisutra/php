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
| Успех | Объект data содержит поля записи, author/contact, tags, assets и метаданные |
| DTO | Граф readonly DTO, enum, даты, типизированная коллекция и варианты по discriminator; неизвестные поля в _extra |
| Ошибка | HTTP 404 через resolved(); двенадцать некорректных HTTP 200 через raw() с диагностикой полей |
| Авторизация | Необязательный Bearer token; локальный пример работает без него |

[Фикстуры](../../example/sdk/fixtures/record.json) задают этот учебный контракт,
а не описывают реального провайдера. В SDK один ресурс и одна операция;
возможности DTO разобраны ниже, остальные механизмы — в [тематических примерах](README.md).

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

Вывод — форматированный JSON с семью разделами:

| Раздел | Что посмотреть |
| --- | --- |
| dto | Автор/контакты, значение и название enum, метки, типы вложений, 185 секунд из `03:05`, точный большой ID и файловое превью |
| serialized | Настоящий `toArray()` всего графа: имена полей, даты в UTC, значение enum, обратный cast в `03:05`, Base64 и неизвестные данные |
| copy | Новый заголовок через `with()` рядом с неизменившимся исходным |
| standalone | Равенство с явным Hydrator; `from()` небольшой отдельной атрибутной модели |
| defaults | Запасной ID, отсутствующий/null заголовок, отсутствующая коллекция, nullable-дата, revision 0 и явное имя автора вместо default provider |
| httpError | `failed: true`, `status: 404` |
| hydrationErrors | Двенадцать отказов: code/reason, путь DTO, исходный sourcePath/kind и HTTP-статус 200 |

## Возможности DTO в этом SDK <a id="dto-features"></a>

Начните с [модели ответа](../../example/sdk/src/Resources/Records/Get/GetRecordResponseDto.php)
и [JSON-фикстуры](../../example/sdk/fixtures/record.json). Все вспомогательные типы
принадлежат `Resources/Records/Get/`; вложенные модели лежат в `Dto/`.

| Возможность | Где показана |
| --- | --- |
| From + fallback, To, Map | record_id/id, state/status и выходные имена полей |
| Вложенный путь | metrics.rating становится rating; непрочитанный metrics.votes остаётся в _extra |
| Readonly и ConstructorValue | kind=record; типы вложений сверяются со значениями, заданными конструктором |
| Граф вложенных DTO | AuthorDto → ContactDto, включая extras обоих уровней |
| Default provider | Отсутствующий display_name вычисляется из first_name/last_name; явное значение имеет приоритет |
| Типизированная коллекция | TagCollection проверяет TagDto и даёт first/count/mapToArray; отсутствующие tags становятся пустой коллекцией |
| ListShape и варианты | related_ids требует int; assets[*].value.type выбирает DTO изображения или документа |
| Без тихой потери элементов | Неизвестный вариант вложения даёт ошибку; rank обёртки и дополнительные поля вариантов сохраняются в _extra |
| Даты и выходной часовой пояс | DateTimeFrom проверяет DATE_ATOM; DateTimeTo сериализует тот же момент в UTC |
| Enum | RecordStatus даёт типизированное значение и title(); DtoSerialize выбирает значение для toArray() |
| Свой двусторонний cast | ReadingTimeCast преобразует MM:SS ↔ секунды с проверкой формата и границ |
| Файл внутри JSON | DataUriBase64FileCast создаёт Base64File; toArray() возвращает чистый Base64. Маленькое превью загружается целиком, не потоком |
| Отсутствие/null/defaults | DefaultValue для title, EmptyStringAsNull для description/phone, nullable updatedAt, ForbidExplicitNull для revision |
| Строгие скаляры и точные ID | Числовая строка не проходит в int; external_id остаётся строкой со всеми цифрами |
| Сохранение дополнительных данных | Корень, автор, контакты, метки, варианты и остатки each-обёрток сохраняют false, ноль, null и пустые списки |
| Сериализация и копии | toArray() рекурсивно применяет атрибуты; with() создаёт поверхностную неизменяемую копию |
| Диагностика | data.author.contact.email указывает на /data/author/contact/email; пути вложений учитывают исходную обёртку value |

Например, после получения `$record` в run.php:

```php
$name = $record->author->displayName;
$email = $record->author->contact->email;
$firstTag = $record->tags->first()?->name;
$statusTitle = $record->status->title();
$array = $record->toArray();
$renamed = $record->with(title: 'Обновлённая запись');
```

`toArray()` — объявленное представление DTO, а не побайтовое восстановление ответа:
входные обёртки проецируются в DTO, имена и форматы следуют правилам вывода,
неизвестные значения остаются в `_extra`. Это не автоматически готовое тело запроса:
[у HTTP-сериализации свои правила](../reference/serialization/dto-output.md).
Главный README сохраняет сокращённую модель; [другие примеры DTO](../guides/dto/showcase.md)
показывают иные стили моделей и сериализацию запросов.

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
| [run.php](../../example/sdk/run.php) | Граф DTO, сериализация, standalone, defaults и отказы |
| [DemoClient](../../example/sdk/src/DemoClient.php) | Вход `records()` |
| [ClientConfigFactory](../../example/sdk/src/Config/ClientConfigFactory.php) | Общие настройки времени выполнения и создание standalone-конфига |
| [HydrationConfigFactory](../../example/sdk/src/Config/HydrationConfigFactory.php) | Строгие типы и сбор неизвестных полей в `_extra` |
| [RecordsResource](../../example/sdk/src/Resources/Records/RecordsResource.php) | Создание привязанного запроса |
| [GetRecordRequest](../../example/sdk/src/Resources/Records/Get/GetRecordRequest.php) | GET, параметр пути, типизированный ответ и повторы при временных ошибках |
| [GetRecordResponseDto](../../example/sdk/src/Resources/Records/Get/GetRecordResponseDto.php) | Корень DTO: маппинг, даты, shapes, extras и правила вывода |
| [AuthorDto](../../example/sdk/src/Resources/Records/Get/Dto/AuthorDto.php), [ContactDto](../../example/sdk/src/Resources/Records/Get/Dto/ContactDto.php) | Вложенные модели, defaults и extras |
| [TagDto](../../example/sdk/src/Resources/Records/Get/Dto/TagDto.php), [TagCollection](../../example/sdk/src/Resources/Records/Get/Dto/TagCollection.php) | Элементы и контейнер типизированной коллекции |
| [ImageAttachmentDto](../../example/sdk/src/Resources/Records/Get/Dto/ImageAttachmentDto.php), [DocumentAttachmentDto](../../example/sdk/src/Resources/Records/Get/Dto/DocumentAttachmentDto.php) | Варианты по discriminator, фиксированные типы и Base64 |
| [RecordStatus](../../example/sdk/src/Resources/Records/Get/RecordStatus.php) | Backed enum с понятным названием |
| [ReadingTimeCast](../../example/sdk/src/Resources/Records/Get/ReadingTimeCast.php), [AuthorDisplayNameProvider](../../example/sdk/src/Resources/Records/Get/AuthorDisplayNameProvider.php) | Своё преобразование и производное значение по умолчанию |
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
