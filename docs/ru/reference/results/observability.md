<!-- languages --> <a href="../../../en/reference/results/observability.md">English</a> · <a href="observability.md">Русский</a> <!-- /languages -->
# Логи, trace и debug <a id="section-1"></a>

Диагностика доступна через `ResultHandle::raw()` — полный `ExecutionResult`.
Выбор настройки зависит от задачи:

| Задача | Что использовать | Условие |
| --- | --- | --- |
| Найти результат вызова и связанные записи логов | `traceId` результата, `trace` в контексте логгера | Trace создаётся и без debug/логгера |
| Посмотреть события выполнения | `ExecutionResult::audit` | Собирается и без debug/логгера |
| Разобрать подготовленный HTTP-запрос | `requestDebug()` / `requestDebugJson()` | `debug: true`; запрос должен быть подготовлен |
| Получить записи в журнале приложения | PSR-3 `logger` и `logLevel` | Логгер передаётся явно; debug необязателен |

[Язык сообщений](../client/localization.md) задаётся независимо: `localization: 'ru'`
включает русский, по умолчанию английский. Технические коды и сторонние тексты
сохраняются; настройка действует и на собственные сообщения логгера.

## Быстрый пример <a id="section-2"></a>

Фрагмент для уже созданного SDK-клиента с `debug: true`, как в основном README:

```php
$execution = $client->records()->get(7)->withTraceId('record-7');
$handle = $client->send($execution);
$result = $handle->raw();

echo $result->traceId;             // record-7: искать это значение в логах.
echo $handle->requestDebugJson();  // JSON запроса с маскированием секретов.
$durationMs = $result->debug?->duration; // Время выполнения либо null.
```

Повторной отправки при чтении результата нет. `requestDebug()` возвращает тот же
снимок массивом; оба метода доступны у `ResultHandle` и `ExecutionResult`.
[Исполняемый обзор клиента](../../examples/client-showcase.md) показывает
trace, audit, логгер и скрытие Bearer-токена на локальном ответе без сети.

## Logger и logLevel <a id="section-3"></a>

`logger` — подготовленный приложением PSR-3 логгер. Без него SDK не пишет логи.
`logLevel` — минимальный уровень (по умолчанию `INFO`). Например, фрагмент
конфигурации с уже созданным `$logger`:

```php
use ApiSutra\Config\ClientConfig;
use Psr\Log\LogLevel;

$config = new ClientConfig(
    baseUrl: 'https://api.example.test',
    localization: 'ru',
    logger: $logger,
    logLevel: LogLevel::DEBUG,
    debug: true,
);
```

| Уровень | Примеры штатных записей |
| --- | --- |
| `INFO` | Начало и успешное завершение запроса |
| `DEBUG` | Подготовка HTTP-запроса, получение ответа и признак кеша |
| `WARNING` | Retry и отказ от небезопасного повтора |
| `ERROR` | Ошибка валидации, HTTP-ошибка или исключение |

`debug: true` собирает снимки результата, а `logLevel: DEBUG` разрешает записи
соответствующего уровня. Эти настройки независимы: debug не подключает логгер
и не меняет порог логирования. Структурированный контекст штатных логов проходит
через [RedactionPolicy](#section-8). Ошибка форматирования,
маскирования или внешнего logger не меняет HTTP, данные, исходное исключение
и сохранённый файл. Audit собирается независимо от sink; повторной отправки лога
через отказавший logger нет. Ошибки бизнес-хуков и DTO сохраняют обычную доставку.

## TraceId <a id="section-4"></a>

`ExecutionResult::traceId` связывает результат с полем `trace` в контексте PSR-3
логов. Можно передать идентификатор входящего запроса приложения или задания:

```php
$execution = $client->records()->get(7)->withTraceId('job-42');
$handle = $client->send($execution);
$traceId = $handle->raw()->traceId; // job-42
```

Без override клиент создаёт случайный идентификатор. `withTraceId()` задаёт его
для конкретного выполнения; `$client->setTraceId('job-42')` задаёт значение
по умолчанию для последующих вызовов этого экземпляра клиента.
`$client->clearTraceId()` снимает этот default; уже полученные результаты не меняются.

У конкурентных вызовов отдельные execution ID и audit, даже при повторном использовании
одного неизменённого request. Смена default клиента влияет на последующие старты,
но не на приостановленные исполнения. Несколько ожидающих одного handle наблюдают
тот же запуск: ожидание не создаёт повторных `started` или терминальных событий.

Приоритет источников:

1. Runtime-опции `RequestExecution` / `RequestOptions` (`withTraceId()`).
2. Override самого запроса (`getTraceIdOverride()`), если он его предоставляет.
3. Trace, переданный исполнителю: у обычного вызова — значение клиента, у вложенного — родительского контекста.
4. Значение pipeline по умолчанию, затем автоматически созданный идентификатор.

Trace не добавляет HTTP-заголовок автоматически. Если API ожидает идентификатор
в `X-Trace-Id` или другом заголовке, задайте его отдельно через
[опции запроса](../request/declaration.md#section-14).

## Идентичность и дерево операций <a id="section-5"></a>

`ExecutionResult::trace` — неизменяемый `ExecutionTrace` с тремя полями:

| Поле | Назначение |
| --- | --- |
| `traceId` | Группа связанных операций; совпадает с `ExecutionResult::traceId` |
| `executionId` | Уникальный ID конкретного запуска, включая повтор того же request |
| `parentExecutionId` | ID родительского запуска, либо `null` у корня |

В логах им соответствуют `trace`, `executionId`, `parentExecutionId`; машинный
`event` независим от языка сообщения. Для корня приоритет trace: runtime override →
override запроса → client default → случайный ID. Дочерний вызов наследует trace
родителя, если у него нет явного override. При явном другом trace связь через
`parentExecutionId` сохраняется. ID не добавляются к cache key или HTTP-заголовкам.

Composite и DependsOn имеют собственные запуски, дети доступны в `nested`.
У DependsOn основной HTTP продолжает тот же запуск после обработки зависимостей.
Пагинация имеет корень и отдельные запуски страниц; trace агрегата равен trace
корня. Await создаёт узел ожидания, связанный со стартовым результатом; poll — его
дети. Передача trace не продлевает и не наследует истёкший HTTP-бюджет старта.

Независимые элементы самостоятельного batch/pool могут иметь разные trace;
агрегат без общего запуска сохраняет `trace: null`. Дочерние результаты сохраняют
trace, audit и debug и при преобразовании exception/rejection в результат.
Локализация, прикрепление meta и чтение готового handle не создают новые ID.
Старый вручную созданный результат или событие может иметь `trace: null`.

## Audit‑лог <a id="section-6"></a>

`ExecutionResult::audit` — массив `PipelineEvent`. У начатого запуска один `started`
и один терминал: `completed` для SUCCESS/PARTIAL или `failed` для FAILED.
Throw/rejection не добавляет второго терминала. Прежняя политика выдачи ошибок
сохраняется: агрегированный FAILED Composite/pagination сам по себе не включает throw. Неудача проверки до отправки
не создаёт фиктивный HTTP-attempt.

```php
foreach ($result->audit as $event) {
    echo $event->stage->value . PHP_EOL;
    $executionId = $event->trace?->executionId; // Событие можно связать с запуском отдельно от результата.
    $elapsedMs = $event->duration;              // От начала запуска; у started — null.
    $attempt = $event->context['attempt'] ?? null;
}
```

Обычный GET: `started → http_request → http_response → completed`. При retry
пары HTTP-событий повторяются; `context.attempt` нумерует фактические попытки.
`http_response` содержит `httpStatus` либо класс исключения отправки. Обновление
авторизации — отдельный дочерний запуск. Cache hit не создаёт HTTP-событий.
Для собственного retry handler одна попытка означает один вызов handler: ядро
не видит его внутренние отправки. Остальные значения PipelineStage сами по себе
не означают обязательной записи на каждом внутреннем шаге.

Событие содержит `stage`, `timestamp`, `duration`, `requestClass`, `role`, `payload`,
`trace` и небольшой структурированный `context`, включая машинный `event`.
Timestamp — календарное Unix-время в секундах; duration — монотонно измеренные
миллисекунды от начала запуска. Перевод системных часов не меняет duration.
Payload появляется только при debug и может оставаться `null`.

Итератор пагинации начинает запуск при потреблении. При естественном исчерпании
он завершается; освобождение незавершённого генератора даёт `abandoned` с причиной
`iteration_stopped`, без следующего HTTP. `break` с сохранённым генератором ещё
не освобождает его. Аварийное завершение процесса не гарантирует terminal-событие.
Повторный обход создаёт новый запуск; журналы родителей не копируют audit детей.

Явная отмена async отклоняет публичный Promise и сразу записывает `failed` с
`reason: execution_cancelled`. Последующая очистка Fiber не добавляет второго
терминального события. Освобождение всех handles без предварительной явной отмены записывает
`abandoned` с `reason: execution_cancelled` для каждой активной области, включая
вложенные запросы и ожидания continuation. Завершённые области не меняются. Это
закрывает локальную диагностику без продвижения цикла и новых HTTP-запросов, но не
доказывает отмену работы, уже полученной внешним сервером. Аварийное завершение
процесса по-прежнему может помешать записи последних событий.

## Debug <a id="section-7"></a>

`ClientConfig(debug: true)` включает `ExecutionResult::debug` (`DebugInfo`):
подготовленный `preparedRequest`, полученный `response` и `duration` в миллисекундах.
Отдельные поля могут быть `null`, если выполнение не дошло до соответствующего шага.
Ответ также доступен как `ExecutionResult::response` без включения debug;
его `duration` измеряет HTTP-обмен, а не весь путь выполнения SDK.
Результаты вложенных вызовов читайте через `nested` и их собственные
`audit`/`debug`; общего дерева в `DebugInfo::nested` pipeline не строит. При сборе пагинации
у корня нет HTTP-снимка; диагностика страниц лежит в `result->nested` по номеру, даже
при ином порядке завершения. Время корня — duration его завершающего события audit.

`requestDebug()` экспортирует **запрос**, не ответ:

| Поля снимка | Содержание |
| --- | --- |
| `method`, `url`, `headers` | Подготовленные HTTP-метод, адрес и заголовки |
| `bodyRaw`, `body`, `query`, `form` | Тело и структурированные части, если доступны |
| `hasStream` | Наличие потока; диагностика его не читает |
| `bodySize`, `bodyOmitted`, `bodyOmissionReason` | Размер строкового тела и причина пропуска |
| `oneOf` | При контрактной диагностике: `contract`, `matchedVariant`, `discriminator` |
| `credentialsEnrichment` | При enrichment: `applied`, `scope`, `mergeMode`, затронутые `fields` |

Без debug или без подготовленного запроса `requestDebug()` и `requestDebugJson()`
возвращают `null`; JSON-метод также может вернуть `null` при невозможности кодирования.
Для разбора ошибок используйте безопасный снимок: сырые `debug`, `response` и
payload событий не проходят маскирование при прямом чтении.

## Маскирование безопасного экспорта <a id="section-8"></a>

**Параметр `ClientConfig::redaction` необязателен:** по умолчанию уже используется
`new RedactionPolicy()`. Создавать и передавать этот объект для включения базовой
защиты не нужно. Достаточно обычного `new ClientConfig(baseUrl: ...)`.

Общая `RedactionPolicy` применяется к `requestDebug()`/`requestDebugJson()`,
структурированному context штатного PSR-3 logger и записываемым фикстурам.
Она маскирует стандартные credential headers, Cookie/Set-Cookie, известные
password/token/secret-поля, URL userinfo и query credentials, включая повторяющиеся
и percent-encoded имена. `credentialsConfig.secretKeys` дополняет правила для
подготовленного запроса. Исходные HTTP-данные и позиция stream не изменяются.

Для полей одной операции реализуйте на запросе
`ApiSutra\Contracts\Interfaces\Diagnostics\SensitiveFieldsProviderInterface`
и добавьте метод:

```php
public function sensitiveFields(): array
{
    return ['approval_code', 'verification_secret'];
}
```

Возвращайте имена полей, не секретные значения. Эти правила дополняют безопасный
экспорт и запись данного запроса/ответа; другие типы запросов и HTTP-данные не
меняются. Встроенные OAuth2-запросы уже объявляют дополнительные секретные поля.

Явно передавайте политику, когда у провайдера есть **дополнительные** секретные
заголовки, поля или вложенные пути, которых нет во встроенных правилах. Если
стандартных правил и `credentialsConfig.secretKeys` достаточно, параметр опустите.
Например, для специфичных credentials провайдера:

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Diagnostics\RedactionPolicy;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    redaction: new RedactionPolicy(
        headers: ['X-Provider-Credential'],
        fields: ['provider_secret'],
        paths: ['accounts.*.credential'],
    ),
);
```

`fields` действуют на любой глубине, `paths` задают пути внутри JSON-тела/данных;
`*` соответствует одному уровню. Правила добавляются к встроенным и не отключают
их. `ClientConfig::with()` сохраняет политику, а `$client->record()` передаёт её
recorder. При самостоятельной сборке `RecordingTransport` базовая политика также
работает автоматически; аргумент `redaction` нужен только для собственных правил.

Невалидный JSON в безопасном экспорте заменяется маркером `[redacted-body]`;
form-urlencoded маскируется по именам полей. Произвольный текст ошибок,
неструктурированный текст и нестандартные форматы не гарантированно очищены:
правила не ищут любой возможный секрет в любом месте строки.

Прямые `ExecutionResult::debug`, `response` и audit payload остаются raw-объектами.
Их произвольная сериализация не является безопасным экспортом. Для осознанного
raw-снимка запроса доступны `requestDebug(false)` и `requestDebugJson(false)`.

Recorder применяет базовую защиту даже без пользовательского Fixture. Fixture
добавляет свои правила и replacement values; ранее записанные файлы автоматически
не переписываются.

## Размер safe debug/log <a id="section-9"></a>

По умолчанию тело в safe debug/log ограничено 64 KiB (65536 байт). При превышении
тело пропускается целиком: `bodyOmitted=true`, `bodyOmissionReason=body_size_limit`,
`bodySize` содержит исходный размер. JSON не обрезается. Исходный HTTP-ответ и replay
fixture сохраняют свой размер; потоки не читаются и не перематываются для диагностики.
`ProviderResponse::duration`, `DebugInfo::duration` и `PipelineEvent::duration` измеряются
в миллисекундах; timestamp остаётся Unix-временем в секундах.

RedactionPolicy необязательна. Её передают для дополнительных секретных
полей либо когда нужно увеличить лимит, сохранив маскирование:

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Diagnostics\RedactionPolicy;

$config = new ClientConfig(
    baseUrl: 'https://api.example.test',
    redaction: new RedactionPolicy(maxBodyBytes: 262144),
);
```

При чтении больших тел из `requestDebug()` учитывайте маркер пропуска или
увеличьте предел. Явный `requestDebug(false)` остаётся raw-доступом
без маскирования и ограничения размера; для обычного разбора увеличьте safe-предел.

## Environment <a id="section-10"></a>

`environment` управляет кешем метаданных, независимо от debug:

- `Local` / `Testing` — кеш отключён.
- `Production` / `Staging` — включён.

Кеш хранит описание деклараций, а не общие объекты из constructor defaults или
аргументов атрибутов. Выбор environment не меняет их изоляцию между DTO и операциями
одного клиента. См. [defaults DTO](../dto/lifecycle.md#section-3)
и [объектные аргументы атрибутов](../dto/lifecycle.md#section-2).
Также environment используется в auto-discovery при `DiscoveryCacheMode::Auto`.

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;

$config = new ClientConfig(
    baseUrl: 'https://api.example.test',
    environment: Environment::Testing,
    debug: true,
);
```
