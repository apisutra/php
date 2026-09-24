<p align="center"><img src="../assets/apisutra-logo.png" alt="Логотип ApiSutra" width="233"></p>
<h1 align="center">ApiSutra</h1>
<h2 align="center">Создавайте PHP SDK для внешних API</h2>

<p align="center">
  <a href="https://github.com/apisutra/php/actions/workflows/tests.yml"><img src="https://github.com/apisutra/php/actions/workflows/tests.yml/badge.svg?branch=master&amp;event=push" alt="Tests"></a>
  <a href="https://github.com/apisutra/php/actions/workflows/tests.yml?query=branch%3Amaster"><img src="https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fapisutra%2Fphp%2Fbadges%2Ftest-count.json" alt="Test count"></a>
  <a href="https://github.com/apisutra/php/actions/workflows/docs.yml"><img src="https://github.com/apisutra/php/actions/workflows/docs.yml/badge.svg?branch=master&amp;event=push" alt="Docs CI"></a>
  <a href="README.md"><img src="https://img.shields.io/badge/docs-EN%20%2F%20RU-2563eb" alt="Документация: EN / RU"></a>
  <a href="../../composer.json"><img src="https://img.shields.io/badge/PHP-8.4%2B-777BB4" alt="PHP 8.4+"></a>
  <a href="https://packagist.org/packages/apisutra/php"><img src="https://img.shields.io/packagist/v/apisutra/php" alt="Packagist"></a>
  <a href="../../LICENSE"><img src="https://img.shields.io/badge/license-MIT-green" alt="Лицензия MIT"></a>
</p>

<!-- languages --> <a href="../../README.md">English</a> · <a href="overview.md">Русский</a> <!-- /languages -->

ApiSutra — PHP-библиотека для создания SDK внешних API. Вы описываете запросы и DTO ответов; пакет берёт на себя авторизацию, повторы, пагинацию и обработку ошибок.

Автор SDK получает единый способ описать API. Приложение — типизированные данные, конкурентные вызовы и диагностику через тот же клиент. **PHP 8.4+.** Ядро работает самостоятельно; [интеграция с Laravel 13](#section-6) поставляется отдельным пакетом.

[Быстрый старт](guides/quickstart.md) · [Карта возможностей](#section-5) · [Документация](README.md)

## Установить и попробовать <a id="section-7"></a>

```bash
composer require apisutra/php
php vendor/apisutra/php/docs/example/sdk/run.php
```

Учебный Records SDK работает на подготовленных ответах: ключи API и доступ к сети не нужны. Для готового SDK следуйте его инструкции по установке и авторизации.

## От запроса к DTO <a id="section-1"></a>

Ниже фрагменты [Records SDK](../example/sdk/src/DemoClient.php); импорты опущены. Адрес API условный. Полная настройка есть в запускаемом примере выше.

### Описать операцию <a id="section-3"></a>

```php
#[Get('/records/{id}')]
#[Retry(attempts: 3)]
#[Returns(GetRecordResponseDto::class, unwrap: 'data')]
final class GetRecordRequest extends AbstractRequest
{
    public function __construct(#[Path] public int $id) {}
}
```

Запрос задаёт маршрут, до трёх попыток и способ чтения ответа. [Декларации запросов](reference/request/declaration.md) также описывают query, заголовки, тело и валидацию.

### Описать данные <a id="section-4"></a>

Сокращённая версия [DTO ответа](../example/sdk/src/Resources/Records/Get/GetRecordResponseDto.php):

```php
final readonly class GetRecordResponseDto extends AbstractResponseDto
{
    public function __construct(
        #[From('record_id', fallback: ['id'])]
        public int $id,
        public string $title,
        #[From('created_at')]
        #[DateTimeFrom(format: DATE_ATOM)]
        public DateTimeImmutable $createdAt,
    ) {}
}
```

Приложение получает объекты с уже преобразованными датами, enum, вложенными моделями и коллекциями. Используйте [штатные правила DTO](guides/dto/showcase.md) или [пользовательский гидратор](reference/dto/hydrators.md) для своей фабрики или библиотеки маппинга. DTO `toArray()` следует правилам сериализации; представление для HTTP настраивается отдельно.

### Отправить и получить результат <a id="section-2"></a>

```php
$client = new DemoClient(
    new ClientConfig(baseUrl: 'https://api.example.test'),
    HttpTransport::createDefault(),
);

$handle = $client->send($client->records()->get(7));
/** @var GetRecordResponseDto $record */
$record = $handle->dataOrFail();
echo $record->createdAt->format('Y-m-d');
```

`send()` дожидается завершения и возвращает `ResultHandle`. `dataOrFail()` читает данные и бросает исключение при FAILED; `resolved()` открывает статус и ошибки, а `raw()` — результат исполнения, HTTP-ответ и диагностику. Чтение того же handle не отправляет запрос заново. [Результаты и ошибки →](reference/results/handles.md)

## Независимые вызовы и большие выборки <a id="async-and-pagination"></a>

Запустите независимые запросы до ожидания результатов — штатный Guzzle-транспорт выполнит HTTP конкурентно:

```php
$first = $client->sendAsync($client->records()->get(7));
$second = $client->sendAsync($client->records()->get(8));

$record7 = $first->wait()->dataOrFail();
$record8 = $second->wait()->dataOrFail();
```

`sendAsync()` возвращает [типизированный промис, совместимый с Guzzle](reference/results/promises.md). Воркер и ручная настройка цикла событий не нужны. Сохраняйте промисы и дожидайтесь их: это не отправка в фоне после завершения приложения.

Для пагинируемого запроса SDK, уже связанного с клиентом, обрабатывайте элементы по мере загрузки страниц. Здесь `$repository` принадлежит приложению:

```php
foreach ($request->paginate()->items() as $item) {
    $repository->save($item);
}
```

[Поток элементов](reference/execution/pagination-items.md) загружает страницы последовательно и не накапливает всю выборку. Страница со статусом FAILED вызывает исключение, а не незаметное окончание списка. Для сбора страниц есть `all()`, `pages()` и `range()`, в том числе с [конкурентной загрузкой независимых страниц](reference/execution/pagination.md#section-21). Для множества независимых запросов [pool `consume()`](reference/execution/pool-consumption.md) передаёт результаты обработчикам без накопления всей коллекции.

## Карта возможностей <a id="section-5"></a>

### Описать API

| Задача | Что даёт ApiSutra |
| --- | --- |
| <a id="capabilities-sdk"></a> Структура SDK | [Клиенты, ресурсы](reference/client/resources.md), версии сервисов и discovery; [каталоги операций](reference/client/operation-inventory.md) и DTO для инструментов. |
| <a id="capabilities-requests"></a> Запросы и входные данные | [Атрибуты](reference/request/declaration.md) для path, query, заголовков и тела; [валидация](reference/client/validation.md) до HTTP. |
| <a id="capabilities-dto"></a> Модели ответа | [DTO](guides/dto/showcase.md), обычные PHP-классы, вложенные коллекции, enum, даты, строгие правила, casts и неизвестные поля; [свои гидраторы с DI](reference/dto/hydrators.md). |
| Исходящие данные | Отдельные правила [сериализации DTO](reference/serialization/dto-output.md) и [HTTP-представления](reference/serialization/request-parts.md); JSON, формы, multipart и бинарное тело. |
| <a id="capabilities-client"></a> Авторизация | API key, Bearer, Basic и [HMAC](reference/auth/strategies.md); [OAuth2](reference/auth/oauth2.md) Client Credentials, Authorization Code с PKCE и обновление токенов. |
| Настройки клиента | [Конфигурация клиента и отдельного вызова](reference/client/configuration.md), изоляция по credentials, интеграция с контейнером и [сообщения EN/RU](reference/client/localization.md). |

### Выполнить, проверить и расширить

| Задача | Что даёт ApiSutra |
| --- | --- |
| <a id="capabilities-execution"></a> Управление исполнением | [Повторы и идемпотентность](reference/execution/retry.md), [общие дедлайны](reference/execution/deadlines.md), отмена, локальные квоты и серверный cooldown; необязательная [координация через Redis](reference/integrations/redis.md). |
| <a id="capabilities-multiple-calls"></a> Конкурентная и массовая обработка | [Типизированный async](reference/results/promises.md), batch/pool, [потоковое потребление](reference/execution/pool-consumption.md) и [зависимые операции](reference/request/composition.md). |
| Пагинация | [Page, offset и cursor](reference/execution/pagination.md), ленивый обход страниц и элементов, конкурентный сбор независимых страниц; конкурентному `all()` нужны `total` и `perPage`. |
| Отложенный результат API | [Готовность и polling](reference/execution/continuation-await.md) для операций, которые завершаются позднее. |
| Меньше повторных обращений | [PSR-16 кеш ответов](reference/execution/cache.md), TTL, режимы отдельного вызова и ключи с учётом credentials. |
| Файлы | [Потоковая отправка](reference/files/uploads.md), [скачивание в файл или поток](reference/files/downloads.md) и работа с архивами. |
| <a id="capabilities-results"></a> Результаты и диагностика | [Статусы и ошибки](reference/results/errors.md), свои методы результата, [trace, audit, debug и маскирование для sync/async](reference/results/observability.md), [наблюдатели исполнения](reference/results/observation.md). |
| <a id="capabilities-extensions"></a> Расширение поведения | [Хуки, обработчики ответов и модули](reference/extensions/extensions.md), свои транспорт, авторизация, casts и гидратация. |
| Тестирование | [Подмены ответов, последовательности и проверки отправок](reference/testing/mocking.md); [запись и воспроизведение фикстур](reference/testing/fixtures.md), проверки на реальном API. |
| Генерация классов | [CLI-команды](reference/client/generation.md) для клиентов, запросов и DTO с учётом пространства имён проекта. |

## Laravel 13 <a id="section-6"></a>

[apisutra/laravel](https://github.com/apisutra/laravel/blob/master/docs/ru/overview.md) подключает тот же SDK к Laravel:

- **Интеграция с приложением:** discovery, DI клиентов и запросов, настройки приложения, валидация, заполнение из входящего HTTP и ответы контроллера.
- **Коллекции:** `Pagination::collect()` оборачивает ленивый поток элементов в `LazyCollection`.
- **Тестирование:** `ApiSutra::for($client)->fake()` с изоляцией ответов, проверками отправок и автоматическим обнаружением пропущенных подмен.
- **Наблюдение:** события исполнения, необязательная интеграция с Telescope и выбранным каналом логов.
- **Очереди и инструменты:** middleware для возврата повторяемых jobs в очередь при ограничении SDK, генераторы Artisan и `artisan about`.

Адаптер устанавливает и ядро. [Использовать SDK в Laravel](https://github.com/apisutra/laravel/blob/master/docs/ru/guides/integration/laravel.md) · [Добавить Laravel в свой SDK](https://github.com/apisutra/laravel/blob/master/docs/ru/guides/sdk/laravel.md).

## Выбрать следующий шаг <a id="section-8"></a>

| Ваша задача | С чего начать |
| --- | --- |
| Создать SDK для API | [Разработка SDK](start/create-sdk.md) |
| Использовать готовый SDK в приложении | [Подключение SDK](start/use-sdk.md) |
| Попробовать возможности локально | [Запускаемые примеры](examples/README.md) |
| Уточнить настройки, поведение или ограничения | [Справочник](reference/README.md) |
| Работать с ИИ-агентом | [Инструкции и карта возможностей](start/agent.md) |

## Участие в разработке <a id="section-9"></a>

[Руководство разработчика](https://github.com/apisutra/php/blob/master/docs/ru/development/README.md) · [Инструкции агенту](https://github.com/apisutra/php/blob/master/.agents/README.md) · [Подготовка вклада](https://github.com/apisutra/php/blob/master/docs/ru/development/contributing.md).

<a id="section-10"></a>
[История изменений](changelog.md) · [Лицензия MIT](../../LICENSE).
