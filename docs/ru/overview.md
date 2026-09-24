<p align="center"><img src="../assets/apisutra-logo.png" alt="Логотип ApiSutra" width="233"></p>
<h1 align="center">ApiSutra</h1>
<h2 align="center">Декларативный PHP SDK для внешних API</h2>

<p align="center">
  <a href="https://github.com/apisutra/php/actions/workflows/tests.yml"><img src="https://github.com/apisutra/php/actions/workflows/tests.yml/badge.svg?branch=master&amp;event=push" alt="Tests"></a>
  <a href="https://github.com/apisutra/php/actions/workflows/tests.yml?query=branch%3Amaster"><img src="https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fapisutra%2Fphp%2Fbadges%2Ftest-count.json" alt="Test count"></a>
  <a href="https://github.com/apisutra/php/actions/workflows/docs.yml"><img src="https://github.com/apisutra/php/actions/workflows/docs.yml/badge.svg?branch=master&amp;event=push" alt="Docs CI"></a>
  <a href="README.md"><img src="https://img.shields.io/badge/docs-multilingual-2563eb" alt="Мультиязычная документация"></a>
  <a href="../../composer.json"><img src="https://img.shields.io/badge/PHP-8.4%2B-777BB4" alt="PHP 8.4+"></a>
  <a href="https://packagist.org/packages/apisutra/php"><img src="https://img.shields.io/packagist/v/apisutra/php" alt="Packagist"></a>
  <a href="../../LICENSE"><img src="https://img.shields.io/badge/license-MIT-green" alt="Лицензия MIT"></a>
</p>

<p align="center">
<!-- languages --> <a href="../../README.md">English</a> · <a href="overview.md">Русский</a> <!-- /languages -->
</p>

ApiSutra — PHP-библиотека для SDK внешних API: декларации запросов и DTO, настройки авторизации и исполнения, типизированные результаты и диагностика. **PHP 8.4+.** Работает самостоятельно; [интеграция с Laravel 13](#section-6) — отдельный пакет.

[Быстрый старт](guides/quickstart.md) · [Карта возможностей](#section-5) · [Документация](README.md)

## Установить <a id="section-7"></a>

```bash
composer require apisutra/php
```

### Запустить пример <a id="run-example"></a>

Учебный [Records SDK](examples/sdk.md) использует подготовленные ответы: ключи API и доступ к сети не нужны.

```bash
php vendor/apisutra/php/docs/example/sdk/run.php
```

## От запроса к DTO <a id="section-1"></a>

Ниже фрагменты [Records SDK](../example/sdk/src/DemoClient.php); импорты опущены, адрес API и токен условные.

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

[Запрос](reference/request/declaration.md) задаёт маршрут, до трёх попыток и DTO ответа.

### Описать данные <a id="section-4"></a>

Сокращённая версия [DTO ответа](../example/sdk/src/Resources/Records/Get/GetRecordResponseDto.php):

```php
final readonly class GetRecordResponseDto extends AbstractDto
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

[Подробный DTO с атрибутами](guides/dto/showcase.md#section-3): маппинг, casts, вложенные DTO, коллекции, extras и файлы. Доступны [свои гидраторы](reference/dto/hydrators.md); правила сериализации `toArray()` и HTTP раздельны.

### Настроить клиент и отправить запрос <a id="section-2"></a>

В `ClientConfig` обязателен только `baseUrl`. Остальные политики задавайте по потребностям интеграции, например:

```php
$config = new ClientConfig(
    baseUrl: 'https://api.example.test',
    auth: new BearerAuthenticator('your-api-token'),
    timeout: 15,
    retry: new RetryConfig(attempts: 3, totalTimeoutMs: 30_000),
    hydration: new HydrationConfig(policy: new RulePolicy(scalars: ScalarPolicy::Strict)),
    throwOnErrors: false,
);

$client = new DemoClient($config, HttpTransport::createDefault());

$handle = $client->send($client->records()->get(7)->withTimeout(5));
/** @var GetRecordResponseDto $record */
$record = $handle->dataOrFail();
echo $record->createdAt->format('Y-m-d');
```

Лимиты клиента — 15 секунд на HTTP-попытку и 30 секунд на исполнение; `withTimeout(5)` задаёт этому запросу 5 секунд на попытку. `$config->with(...)` создаёт новую конфигурацию. [Другие настройки](guides/client/showcase.md): кеш, квоты, логи, сериализация и расширения.

`send()` дожидается завершения и возвращает `ResultHandle`. С `throwOnErrors: false` можно изучить отказ и выбрать способ обработки:

| Чтение handle | Значение и назначение |
| --- | --- |
| `dataOrFail()` | Объявленный DTO для логики приложения; при FAILED бросает исключение даже с `throwOnErrors: false`. Другие запросы могут вернуть коллекцию, массив, скаляр, текст, null или `FileResponse`. |
| `resolved()` | `ResolvedResultInterface`: данные, статус, сообщения и преобразованные ошибки для логики приложения или UI. Чтение FAILED без исключения. |
| `raw()` | `ExecutionResult`: исходный HTTP-ответ, ошибки, метаданные, дочерние результаты, trace/audit/debug для диагностики и своей обработки. Не переключает ответ в режим без декодирования. |

Все три читают одно исполнение без повторного HTTP. [Представления результата и ошибки →](reference/results/handles.md)

## Независимые вызовы и большие выборки <a id="async-and-pagination"></a>

Запустите независимые запросы до ожидания результатов — штатный Guzzle-транспорт выполнит HTTP конкурентно:

```php
$first = $client->sendAsync($client->records()->get(7));
$second = $client->sendAsync($client->records()->get(8));

$record7 = $first->wait()->dataOrFail();
$record8 = $second->wait()->dataOrFail();
```

`sendAsync()` возвращает [типизированный Guzzle-совместимый промис](reference/results/promises.md). Дождитесь его; воркер и ручная настройка цикла событий не нужны.

Здесь `$request` — запрос с пагинацией, связанный с клиентом, а `$repository` — хранилище приложения:

```php
foreach ($request->paginate()->items() as $item) {
    $repository->save($item);
}
```

[Поток элементов](reference/execution/pagination-items.md) загружает страницы последовательно без накопления выборки; FAILED вызывает исключение. Сбор и конкурентность — в [пагинации](reference/execution/pagination.md#section-21), потоковая обработка независимых запросов — в [pool `consume()`](reference/execution/pool-consumption.md).

## Карта возможностей <a id="section-5"></a>

### Организовать SDK <a id="capabilities-sdk"></a>

| Задача | Что даёт ApiSutra |
| --- | --- |
| Структура SDK | [Клиенты и вложенные ресурсы](reference/client/resources.md), [несколько сервисов](guides/integration/multi-service.md), [версии API](reference/client/versioning.md) и [discovery клиентов](reference/client/discovery.md). |
| Каталоги SDK | [Каталог операций](reference/client/operation-inventory.md), [каталог DTO ответов](reference/client/response-dto-catalog.md) и [статические справочники провайдера](reference/client/catalogs.md): возможности, тарифы и словари без HTTP. |

### Настроить клиент, транспорт и авторизацию <a id="capabilities-client"></a>

| Задача | Что даёт ApiSutra |
| --- | --- |
| Конфигурация | [Настройки клиента, копии и переопределения для вызова](reference/client/configuration.md), [необязательный контейнер](reference/client/construction.md), [мультиязычность: язык клиента и свои переводы сообщений](reference/client/localization.md). |
| HTTP и async | [Интеграция транспорта PSR-18/PSR-17](reference/execution/transport.md), синхронный `send()`, конкурентный `sendAsync()`, отмена; [типизированные Guzzle-совместимые промисы](reference/results/promises.md) с `wait`/`then`/`otherwise`. Своему транспорту нужна явная поддержка async. |
| Авторизация и токены | [API key, Bearer, Basic, HMAC и auth-scopes](reference/auth/strategies.md); [кеш токенов, refresh после 401 и блокировки refresh](reference/auth/tokens.md). Общее хранилище само по себе не гарантирует межпроцессную блокировку. |
| OAuth2 | [Client Credentials и Authorization Code с PKCE S256](reference/auth/oauth2.md), проверка state, автоматический refresh, effective scopes, export/restore токенов и попыток авторизации. Хранение и межпроцессную координацию ротации обеспечивает приложение. |
| Credentials и адреса | [Подстановка credentials и защита по origin](reference/auth/credentials.md), изоляция контекстов авторизации; [полные и подписанные URL](reference/serialization/uri-query.md) без автоматической передачи credentials клиента. |

### Описать запросы и исходящие данные <a id="capabilities-requests"></a>

| Задача | Что даёт ApiSutra |
| --- | --- |
| Декларации запросов | [HTTP-атрибуты, поля path/query/header/body, oneOf и discriminator](reference/request/declaration.md); [проверки запроса до HTTP, свои preflight-проверки и явная валидация DTO](reference/client/validation.md). Для правил `Validate` нужен Illuminate Validation. |
| Сериализация | Раздельные [правила DTO `toArray()`](reference/serialization/dto-output.md) и [HTTP-именования, форматов массивов и boolean](reference/serialization/request-parts.md); даты, enum, JSON/формы, [JSON внутри поля](reference/serialization/casts.md), [корневое тело для JSON Patch/bulk](reference/serialization/body.md). |
| Форматы ответа | [DTO с явным `unwrap` или `RawResponse`](reference/attributes/response.md); [JSON-массивы, скаляры, null и текст без DTO](reference/results/handles.md#section-3). `raw()` читает детали исполнения; `RawResponse` выбирает тело без декодирования. |
| Файлы и архивы | [Потоковые multipart/binary и Base64](reference/files/uploads.md), [файловые поля DTO](guides/dto/showcase.md#section-7), [скачивание в файл или поток](reference/files/downloads.md), [просмотр, чтение и извлечение архивов](reference/files/archives.md). Base64 загружает содержимое целиком. |

### Преобразовать ответы и DTO <a id="capabilities-dto"></a>

| Задача | Что даёт ApiSutra |
| --- | --- |
| Описание моделей | [Атрибуты на обычных и readonly-классах](reference/dto/declarations.md), необязательный базовый DTO, [внешние правила без изменения моделей](reference/dto/field-rules.md), [наследование и рекурсивные модели](reference/dto/models.md). |
| Маппинг полей | [Входные имена, вложенные пути и fallback](reference/dto/profiles.md); независимые выходные имена, профили гидратации и [общая политика](reference/dto/configuration.md). |
| Контракты полей | [Отсутствие и null, обязательное присутствие, запрет null, defaults и пустые строки](reference/dto/defaults.md); [проверка значений, заданных конструктором](reference/dto/constructor-values.md). |
| Типы и точность | [Преобразование скаляров, явно включаемый Strict, unions и большие целочисленные ID без потери цифр](reference/dto/scalars.md); enum, [форматы дат и часовые пояса](reference/dto/profiles.md). |
| Сложные структуры | [Вложенные DTO и строгие формы списков](reference/dto/shapes.md), [типизированные коллекции](reference/dto/collections.md), [варианты элементов по discriminator](reference/dto/variants.md). |
| Дополнительные данные | [Сохранение непрочитанных полей через `Extras`](reference/dto/extras.md), включение в `toArray()` и [исключение приёмника из исходящих запросов](reference/serialization/receiver-output.md). |
| Свои преобразования | [Входные/выходные casts и вложенные преобразования с контекстом, в том числе без HTTP](reference/dto/scope.md), [вычисляемые значения](reference/dto/lifecycle.md); [свои гидраторы с DI и штатным fallback](reference/dto/hydrators.md). |

### Управлять исполнением, нагрузкой и кешем <a id="capabilities-execution"></a>

| Задача | Что даёт ApiSutra |
| --- | --- |
| Безопасные повторы | [Политики retry, backoff, Retry-After и идемпотентность](reference/execution/retry.md), переопределения запроса и проверка повторной отправки файлов. |
| Ограничение времени | [Таймаут попытки, общий бюджет и общие дедлайны](reference/execution/deadlines.md) для повторов, авторизации и зависимых вызовов. |
| Квоты запросов | [Совместные квоты клиента и операции, ожидание или отказ](reference/execution/rate-limit.md); локальный учёт или необязательный [атомарный Redis-backend](reference/integrations/redis.md). |
| Серверный cooldown | [Общий запрет Retry-After после 429](reference/execution/cooldown.md) с учётом операции/группы, origin и credentials; ожидание в пределах бюджета или отказ, общий backend для клиентов/процессов по выбору. |
| Кеш ответов | [PSR-16, TTL, режимы вызова, очистка и изоляция SDK/credentials](reference/execution/cache.md). Кеширование HTTP и хранение токенов управляются раздельно. |

### Объединять вызовы и обрабатывать большие выборки <a id="capabilities-multiple-calls"></a>

| Задача | Что даёт ApiSutra |
| --- | --- |
| Batch и pool | [Последовательный/конкурентный batch, конкурентный pool, лимиты конкурентности и стратегии отказов](reference/execution/batch-pool.md); собранные результаты и диагностика дочерних вызовов. |
| Потоковая обработка | [Pool `consume()` / `consumeAsync()`](reference/execution/pool-consumption.md): iterable неизвестного размера, обработчики и сводка без накопления всех результатов; остановка при отказе по выбору. |
| Пагинация | [Схемы page/offset/cursor, типизированные элементы, DTO-контейнеры метаданных и защита обхода](reference/execution/pagination.md); ленивые [страницы/элементы](reference/execution/pagination-items.md), упорядоченный конкурентный сбор независимых страниц и общий дедлайн. Cursor последовательный; конкурентному `all()` нужны `total`/`perPage`; коллизии строковых ключей агрегата дают явную ошибку. |
| Зависимые операции | [Составные запросы и зависимости между шагами](reference/request/composition.md), объединение результатов и общий бюджет исполнения. |
| Отложенный результат API | [Критерии Pending/Ready](reference/execution/continuation-state.md), [continuation-токены, await и ограниченный polling](reference/execution/continuation-await.md). Это готовность операции провайдера, отдельная от конкурентного HTTP. |

### Получать результаты и разбирать ошибки <a id="capabilities-results"></a>

| Задача | Что даёт ApiSutra |
| --- | --- |
| Результаты и ошибки | [Handle, прикладное представление и полный результат](reference/results/handles.md); [SUCCESS/PARTIAL/FAILED, результат или исключение, преобразование ошибок провайдера](reference/results/errors.md), [фабрика исключений](reference/results/exceptions.md) и [свои методы результата](guides/recipes/custom-result.md). |
| Трассировка и диагностика | [Дерево sync/async-вызовов, корреляция логов, audit, debug и маскирование секретов](reference/results/observability.md); машинные причины/этапы и [пути ошибок DTO с положением в исходном ответе](reference/dto/diagnostics.md). |
| Наблюдение | [Безопасные снимки исполнения](reference/results/observation.md): операция, исход, корреляция, число и длительность попыток, их необязательные детали и диагностическая метка клиента. Доставкой занимается приложение или Laravel-адаптер. |

### Расширять, тестировать и генерировать SDK <a id="capabilities-extensions"></a>

| Задача | Что даёт ApiSutra |
| --- | --- |
| Точки расширения | [Хуки жизненного цикла](reference/extensions/hooks.md), [модули, обработчики форматов ответа и атрибутов](reference/extensions/extensions.md), свои auth/casts/гидратация, декораторы [транспорта](reference/execution/transport.md) и [исполнителя](reference/extensions/execution.md). |
| Тестирование SDK | [Fake, динамические/файловые ответы, последовательности, проверки отправок и пропущенных подмен, обратимые сессии](reference/testing/mocking.md); [record/playback](reference/testing/fixtures.md) и [помощники live-тестирования](reference/testing/live.md). |
| Генерация классов | [CLI-генераторы](reference/client/generation.md) клиентов, запросов и DTO в пространстве имён проекта; [запускаемые примеры и Records SDK](examples/README.md). |

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
