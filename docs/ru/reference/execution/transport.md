<!-- languages --> <a href="../../../en/reference/execution/transport.md">English</a> · <a href="transport.md">Русский</a> <!-- /languages -->
# Транспорт, promise и ограничения I/O <a id="section-1"></a>

## Promise API и фактическое выполнение <a id="section-2"></a>

`send()` остаётся синхронным и возвращает готовый ResultHandle. `sendAsync()`
начинает выполнение и возвращает `ResultPromiseInterface<ResultHandle>`;
`wait()` даёт handle. [Типы и цепочки](../results/promises.md).
Запустите несколько вызовов перед ожиданием:

```php
use GuzzleHttp\Promise\Utils;

$first = $client->sendAsync($firstRequest);
$second = $client->sendAsync($secondRequest);
$results = Utils::all([$first, $second])->wait();
```

Штатный Guzzle-адаптер выполняет HTTP конкурентно. Ожидания SDK: delay, backoff,
Retry-After, rate-limit и auth-lock — уступают управление другим исполнениям.
Sync и async используют один пайплайн: безопасность повторов, auth, общие дедлайны,
маскирование, потоковые файлы и выдача результата сохраняют свои контракты. Cache hit
или mock могут завершиться сразу. Ожидание одного промиса продвигает и другие начатые
вызовы. Повторные `raw()` / `resolved()` не отправляют запрос заново; две Fiber могут
ждать один SDK Promise. Поддерживаются `then()`, `otherwise()`, `Utils::all()` и `Each`.

Ядро зависит от `revolt/event-loop`; внутренний адаптер
планировщика владеет только таймерами и ожиданиями SDK, не циклом или обработчиком
ошибок приложения. Штатному HTTP нужны Guzzle и cURL с AsynchDNS. Неблокирующий опрос
cURL существует только при активных передачах; в простое и при ожидании одних таймеров
HTTP-опроса нет. Обычный `send()` не создаёт задач планировщика.

Сторонние транспорты реализуют `ConcurrentTransportInterface::assertSupportsConcurrency()`;
HTTP-адаптеры — `AsyncHttpClientInterface::assertSupportsConcurrency()` и
`sendAsyncWithOptions(RequestInterface, TransportOptions): PromiseInterface`.
RetryDelayPolicyInterface только вычисляет задержку; отдельная async capability не нужна.
Контракты обещают продвижение в цикле SDK и кооперативные ожидания, а не только тип
Promise. Неподдерживаемый async даёт `configuration_error` до auth, квоты и HTTP;
используйте `send()` или конкурентный адаптер. Неявного синхронного fallback нет.
MockTransport работает без Guzzle HTTP и cURL.

Сохраняйте handle и дождитесь результата до выхода из job/HTTP-запроса. Это не
fire-and-forget. `$promise->cancel()` запрашивает отмену по возможности и отклоняет
Promise; отмена прекращает следующие попытки, освобождает ожидания, передачи и
собственные sinks SDK. Во внутренней диагностике это `execution_error` с причиной
`execution_cancelled`. Переданные данные отозвать нельзя; списанная квота не возвращается.
Удаление всех владельцев промиса прекращает ожидающую работу SDK; деструкторы не запускают HTTP
или цикл. Отмена агрегата также отменяет его незавершённых детей.

Неизменяемый request допускает конкурентные `with*`-исполнения: options, context,
trace и pagination helper принадлежат каждому выполнению. В хуке `getContext()` и
protected `$context` относятся к его исполнению; вне выполнения доступен последний
завершённый контекст. Не изменяйте request/client, authenticator или общий поток во
время использования. Конкурентным загрузкам нужны независимые потоки. Синхронные
пользовательские hooks, stores, phpredis, логирование и файловый ввод-вывод могут
блокировать процесс. В native cURL callbacks нельзя приостанавливаться или запускать
вложенную работу SDK. Адаптеров React/Swoole/Octane/FrankenPHP в поставке нет.

Файл полностью получен в собственный sink до разрешения Promise; потребитель может
читать его позже без цикла. Страницы пагинации последовательны,
но другие вызовы продвигаются во время HTTP и ожиданий каждой страницы.

Batch/pool управляют задачами по [своему контракту](batch-pool.md). Отложенная операция
провайдера имеет отдельный [контракт готовности](continuation-state.md).

Ожидание источника не завершает всех [ожидающих](../extensions/execution.md#section-5).

Штатная сериализация принимает относительные endpoint и сохраняет base path,
исходный query и повторяющиеся параметры по [контракту URI](../serialization/uri-query.md#section-5).
Полные и подписанные URL поддерживаются через `withUrl()` или абсолютный endpoint;
для них и внешнего `withBaseUrl()` действует [изоляция назначения](../serialization/uri-query.md).

## Замена и очистка тела PreparedRequest <a id="section-3"></a>

Обычная сериализация работает автоматически; новые настройки клиента не нужны.
Следующий API предназначен для hooks и собственных адаптеров, которые меняют уже
подготовленное HTTP-тело. У `PreparedRequest` ровно один источник: строка `body`
(включая `''`), поток `stream` либо отсутствие тела (оба `null`).

| Операция | Результат |
| --- | --- |
| `withBody($text)` или `with(body: $text)` | Строка заменяет всё тело, прежний поток убирается. |
| `withStream($stream)` или `with(stream: $stream)` | Поток заменяет всё тело, прежняя строка убирается. |
| `withoutBody()` | Убирает строку и поток. Повторная очистка допустима. |
| `with(body: null)` / `with(stream: null)` | Сохраняет прежнее тело; это не очистка. |

Каждый метод возвращает новую копию, не читает, не перематывает и не закрывает поток.
Одновременные ненулевые `body` и `stream` в конструкторе или `with()` вызывают
`ConfigurationException`, в том числе для пустой строки. В pipeline сохраняется
обычная обработка ошибок: `configuration_error` либо исключение при `throwOnErrors`.
Некорректный запрос не отправляется; hook после auth не отменяет уже выполненную auth.

При замене/очистке SDK удаляет унаследованные `Content-Length` и `Transfer-Encoding`
без учёта регистра. Явный `headers` в том же `with()` заменяет весь массив заголовков
и сохраняется как новый снимок. Перед HTTP SDK проверяет явную длину по известному
размеру передаваемого тела. Неверная или дублирующаяся `Content-Length`, а также её
сочетание с `Transfer-Encoding` дают `configuration_error`. Неизвестный размер потока
не вычисляется чтением: за явно указанную длину отвечает вызывающий код.

`Content-Type` и прикладные заголовки сохраняются. При смене формата задайте тип явно;
SDK не угадывает MIME по содержимому. Собственные digest и подписи пересчитывает
код, который их сформировал. Пример замены multipart на JSON:

```php
use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\VO\Pipeline\PipelineContext;

final class ReplacePayloadHook implements HookInterface
{
    public function handle(PipelineContext $context): ?array
    {
        $context->preparedRequest = $context->preparedRequest
            ->withBody('{"mode":"metadata"}')
            ->withHeader('Content-Type', 'application/json');

        return null;
    }
}
```

Для полной очистки в hook используйте
`$context->preparedRequest = $context->preparedRequest->withoutBody()`.
URL, destination policy, auth-заголовки, transport options, общий бюджет и цель download
сохраняются. Происхождение файловой операции и запрет её кеширования тоже сохраняются.

После замены удаляется прежний снимок `meta.body`/`bodyIsRoot`. Debug показывает новую
строку в `bodyRaw` либо `hasStream: true`, без чтения потока и восстановления структуры
из raw-тела. При `debug: true` итоговый debug берёт запрос из полученного ответа,
а при ошибке без ответа — из актуального контекста. Redaction действует как обычно.
Смена тела между попытками запрещает retry с `body_changed`;
[подробности повторов](retry.md).

`with(body: ...)` заменяет поток строкой. Выберите один источник вместо
одновременных `body`/`stream`. Для очистки используйте `withoutBody()`;
`with(...: null)` сохраняет тело. При смене формата обновляйте `Content-Type`;
неактуальные framing headers удаляются автоматически.

## Потоковые файлы <a id="section-4"></a>

Штатный транспорт автоматически поддерживает binary/multipart upload и `#[Download]`
с ограниченным расходом памяти. Сторонний транспорт, PSR-клиент и собственный retry
handler должны реализовать `FileStreamingInterface::assertSupportsFileTransfer()`.
Проверка происходит до auth/HTTP. Одного PSR-18 недостаточно для гарантии памяти;
неподдерживаемый адаптер даёт `configuration_error`, без fallback к строке.

`FileTransferOptions` описывает upload/download и необязательную цель. Режим передаётся
через `PreparedRequest.fileTransfer` и `TransportOptions.fileTransfer`. Для download
`HttpTransport` создаёт отдельный sink каждой попытки в `TransportOptions.sink`.
PSR-клиент должен писать прямо в него и вернуть его как body PSR-ответа; не закрывать
sink. `HttpTransport` возвращает `ProviderResponse(body: null, stream: ...)`.
Для обычного ответа `body` остаётся строкой, `stream = null`.

Адаптер обязан сохранять upload-диапазон и не закрывать чужие потоки. Он не должен
материализовывать большой файл, включать скрытый sink/debug, менять тело defaults
или добавлять redirects. Штатный Guzzle подавляет эти файловые defaults и отклоняет
raw cURL overrides для потокового вызова. Таймауты и origin policy продолжают действовать.
Custom retry handler обязан передавать файловые опции транспорта без изменений.

Контракт владения — в [гайде файлов](../../guides/recipes/files.md). Сам пакет не получает
новых обязательных зависимостей; standalone адаптер может работать без Laravel
и Guzzle HTTP Client.

## Контракт <a id="section-5"></a>
```php
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use GuzzleHttp\Promise\PromiseInterface;

interface TransportInterface
{
    public function send(PreparedRequest $request): ProviderResponse;
    public function sendAsync(PreparedRequest $request): PromiseInterface;
}
```

## HttpTransport (PSR‑18/PSR‑17) <a id="section-6"></a>
Дефолтная реализация использует:
- PSR‑18 `ClientInterface`
- PSR‑17 `RequestFactoryInterface` и `StreamFactoryInterface`

В Laravel `SdkServiceProvider` автоматически создаёт `GuzzleHttpClient` с известным
cURL handler, если пользовательский PSR-клиент/транспорт не задан. Вне Laravel:

```php
use ApiSutra\Transport\HttpTransport;

$transport = HttpTransport::createDefault();
// Передайте $transport в конструктор SDK-клиента.
```

Для этой штатной сборки нужны `guzzlehttp/guzzle` и `ext-curl`. Guzzle HTTP Client
остаётся опциональной зависимостью ядра. PSR-17 фабрики уже входят в зависимости
пакета. Дефолты 30/10 секунд применяются автоматически.

### Собственные HTTP-клиенты <a id="section-7"></a>

Конструктор `HttpTransport($httpClient, $requestFactory, $streamFactory)` сохранён.
Чтобы применять SDK-лимиты, PSR-18 клиент дополнительно реализует
`HttpClientOptionsInterface`: `assertSupportsTimeouts(TransportOptions)` проверяет
возможности, `sendWithOptions()` применяет `effective()` непосредственно перед HTTP.
Штатный `GuzzleHttpClient` реализует оба контракта. Для дополнительных параметров:

```php
use ApiSutra\Transport\GuzzleHttpClient;
use GuzzleHttp\Psr7\HttpFactory;

$factory = new HttpFactory();
$transport = new HttpTransport(
    new GuzzleHttpClient(['verify' => '/path/to/ca.pem']),
    $factory,
    $factory,
);
```

Адаптер передаёт `timeout` и `connect_timeout` отдельно для каждой отправки, не меняя
глобальный HTTP-клиент. `http_errors=false` и `allow_redirects=false` сохраняют
обработку 4xx/5xx и redirects в SDK. `sendAsync()` использует конкурентный адаптер, описанный выше.

Обычный `GuzzleHttp\Client`, переданный напрямую как PSR-18, не объявляет поддержку
этих опций. Для стандартной сборки замените его на `GuzzleHttpClient` либо используйте
`createDefault()`. Штатный адаптер владеет cURL handler и отклоняет `config['handler']`.
Если нужен существующий Guzzle-клиент с собственным middleware/handler, сохраните
его в своём адаптере `HttpClientOptionsInterface` и явно обеспечьте оба таймаута.
SDK не заменяет пользовательский stack и не определяет capability только по имени класса.

Собственный `TransportInterface` с лимитами реализует `TimeoutAwareTransportInterface`.
При неподдерживаемом ненулевом лимите, включая дефолты, `assertSupportsTimeouts()`
должен выбрасывать `ConfigurationException` с именем неподдерживаемого параметра до
HTTP. Опции берутся из `PreparedRequest::transportOptions`; `effective()` проверяет
deadline и ограничивает таймаут остатком после всех ожиданий. Все копии запроса должны
сохранять опции. Прямой вызов `send()` с PreparedRequest без опций сохраняет старое
поведение: конфиг SDK в таком вызове отсутствует.

Для обычного PSR-18 клиента без адаптера допустим явно выбранный режим
`timeout: 0`, `connectTimeout: 0`, без общего бюджета. Собственные лимиты этого
HTTP-клиента остаются в его ведении. Неизвестный транспорт с активными SDK-лимитами
даёт `configuration_error` до отправки. Контракт, приоритеты и ограничения:
[Timeouts & Delay](deadlines.md).

## TransportResolver для high-level make API <a id="section-8"></a>
Для провайдерных SDK (например, `ProviderClient::make(...)`) используйте
`TransportResolver` из ядра:
- если `transport` передан явно — используется он;
- иначе резолв из контейнера через `ContainerProviderInterface`;
- при ошибке кидается стандартизированный `ConfigurationException`.

Это позволяет держать конструктор клиента low-level и предсказуемым
(`new Client($config, $transport)`), а удобный DX вынести в `make(...)`.

```php
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Support\TransportResolver;

public static function make(
    string $accessKey,
    string $secretKey,
    ?TransportInterface $transport = null,
): self {
    $config = ProviderClientConfigFactory::make($accessKey, $secretKey);
    $resolvedTransport = TransportResolver::resolve($transport, $config->containerProvider);

    return new self($config, $resolvedTransport);
}
```

## MockTransport и RecordingTransport <a id="section-9"></a>
Для тестов доступны:
- `MockTransport` — фейковые ответы, паттерны, последовательности; принимает опции
  для проверки pipeline, фактического HTTP и принудительного прерывания нет
- `RecordingTransport` — запись фикстур в JSON

## PreparedRequest и ProviderResponse <a id="section-10"></a>
`PreparedRequest` содержит: метод, URL, заголовки, тело/stream, meta и опциональные `transportOptions`.
`ProviderResponse` содержит: статус, заголовки, body, duration.

## Изоляция назначения <a id="section-11"></a>

Для готового URL и смены origin транспорт должен реализовать
`ApiSutra\Contracts\Interfaces\Core\DestinationAwareInterface`:
метод `assertSupportsDestination(RequestDestination $destination): void` подтверждает,
что фактическая отправка сохраняет target, исключает redirects и автоматические
credentials исходного клиента. Если это невозможно, метод бросает `ConfigurationException`
до I/O. Одна лишь поддержка таймаутов этой гарантии не даёт.

`HttpTransport` проверяет поддержку также у вложенного PSR-клиента. Если тот реализует
`HttpClientOptionsInterface`, `TransportOptions.destination` передаёт выбранное назначение
в `sendWithOptions()` и сохраняется при `effective()`. PSR-клиент без options-интерфейса
может заявлять capability только если обеспечивает её при любом `sendRequest()`.
Копии `PreparedRequest.with()` сохраняют `destination`. Перед отправкой изменённой копии
проверяйте `DestinationGuard::checkRequest($request)`; при собственном retry handler
нужна та же capability и проверка непосредственно в месте I/O.

Штатные HttpTransport, MockTransport и RecordingTransport поддерживают контракт;
recorder делегирует проверку вложенному транспорту. `HttpTransport::createDefault()`
выбирает всё автоматически. Для обычных относительных same-origin запросов новый
интерфейс не обязателен. Прямой низкоуровневый PreparedRequest без destination не
содержит исходного origin: гарантия pipeline не появляется от одного абсолютного URL.

Штатный Guzzle-адаптер использует отдельный клиент для защищаемых вызовов. Он сохраняет
настройки verify, proxy, force_ip_resolve, version и таймаутов. Auth, cookies, произвольные
headers/query/body defaults, callbacks/debug и клиентские TLS cert/ssl_key не наследуются.
Непустые низкоуровневые `curl` overrides дают ошибку для защищаемого вызова;
их совместимость нельзя предполагать. Для обычной отправки исходная конфигурация сохранена.
`ExactTargetCurlFactory` сохраняет path и пустой query delimiter на cURL-границе,
включая absolute-form при HTTP proxy. Redirects автоматически не выполняются.

Hooks и собственный PHP-код остаются доверенными расширениями. Проверка защищает
штатный путь отправки; она не изолирует код, который самостоятельно копирует секреты,
удаляет контракт назначения или обращается к сети. Проверка destination не является
SSRF-фильтром, DNS-политикой или ограничением доступных адресов.

Пользовательский API: [внешние URL](../serialization/uri-query.md).
