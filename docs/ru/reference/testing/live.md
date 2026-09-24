<!-- languages --> <a href="../../../en/reference/testing/live.md">English</a> · <a href="live.md">Русский</a> <!-- /languages -->
# Инструменты live-проверок <a id="section-1"></a>

Гайд по организации тестов, выполняющих реальные HTTP-запросы к API провайдера.

Важно:
- это не жёсткий контракт core ApiSutra, а набор рекомендуемых provider-side паттернов;
- разные SDK могут использовать только часть этого гайда;
- если у провайдера есть полноценный sandbox и дешёвые smoke-сценарии, live-suite может быть минимальным или вовсе отсутствовать.

## Уровни применимости <a id="section-2"></a>

Чтобы не путать “опционально” с “редко нужно”, полезно делить live-паттерны на 4 уровня:
- базовый минимум — техническая основа live-проверок;
- рекомендуется по умолчанию — для большинства production SDK это быстро окупается;
- нужен при определённых условиях — включается по признакам API;
- операторское удобство — повышает DX и сопровождаемость, но не задаёт контракт SDK.

Практика показывает: если SDK не учебный, а живёт поверх реального внешнего API, то
многие паттерны из этого гайда используются не эпизодически, а регулярно. Поэтому ниже
важно читать “опционально” как “не часть core-контракта”, а не как “почти никогда не нужно”.

## Что даёт core, а что строит SDK <a id="section-3"></a>

### Core ApiSutra <a id="section-4"></a>

Core-пакет предоставляет только базовые primitives:
- `LiveEnvLoader` — загрузка `.env.live.local` в окружение;
- `LivePolling::waitUntil()` — polling-хелпер;
- `LiveResultAssertions::assertSuccess()` и `assertDataInstanceOf()` — framework-agnostic assertions для technical success result-layer и ожидаемого типа `data()`.

`assertSuccess()` не выполняет бизнес-оценку ответа. Он проверяет только, что
`ResolvedResultInterface` помечен как success на уровне result-layer. Бизнес-валидность
ответа SDK-разработчик оценивает отдельно: branded result, свои predicates, анализ DTO,
meta и provider-specific статусов.

### Provider-side слой <a id="section-5"></a>

Всё остальное из этого гайда относится к архитектуре конкретного SDK:
- `LiveEnv`, `LiveTestGuard`, `LiveClientFactory`, `LiveFixtureLoader`, `LiveResponseDumper`;
- структура `tests/Live/*`;
- группировка live-suite;
- кеширование, response dumping, preflight, баланс, Makefile и операторский DX.

Ни один из этих элементов не является обязательным только потому, что SDK построен на ApiSutra.
Но на практике для production SDK с десятками endpoint-ов, сочетанием sync/async flow,
файлами, нестабильными ответами или дорогими запросами такой support-слой обычно стоит
считать рекомендуемым по умолчанию, а не экзотикой.

## ApiSutra helper-методы <a id="section-6"></a>

### `LivePolling::waitUntil()` <a id="section-7"></a>

Polling до готовности:

```php
use ApiSutra\Testing\LivePolling;

$status = LivePolling::waitUntil(
    fetch: fn () => $client->resource()->checkStatus($id)->withoutCache()->send()->resolved()->data(),
    isReady: fn ($dto) => $dto->status === Status::Ready,
    timeoutSeconds: 30,
    intervalMilliseconds: 1000,
);
```

`->withoutCache()` особенно важен, если статус меняется во времени.

Между проверками хелпер кооперативно ожидает внутри async-задачи SDK и реагирует
на её отмену. Вне SDK-задачи ожидание синхронное. Сами callbacks должны избегать
блокирующего ввода-вывода, если соседние задачи должны продолжать работу.

### `LiveResultAssertions` <a id="section-8"></a>

```php
use ApiSutra\Testing\LiveResultAssertions;

$resolved = $client->resource()->get($id)->send()->resolved();
LiveResultAssertions::assertSuccess($resolved, 'resource/get');
LiveResultAssertions::assertDataInstanceOf($resolved, UserDto::class, 'resource/get');
$user = $resolved->data();
```

Эти helper-методы не заменяют provider-specific assertions:
- если провайдер возвращает business status внутри payload, проверяйте его отдельно;
- если нужен branded result, business-инварианты удобно инкапсулировать именно там.

## Кеширование <a id="section-9"></a>

Кеширование полезно, если live-запросы дорогие, медленные или повторяются.
Для маленького и дешёвого smoke-suite отдельный live-cache может быть не нужен.

ClientConfig принимает `Psr\SimpleCache\CacheInterface` (PSR-16). Для файлового кеша
можно использовать Symfony Cache (`FilesystemAdapter` + `Psr16Cache`) или любую другую
PSR-16-совместимую реализацию.

```php
use ApiSutra\Config\CacheConfig;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

$cacheDir = __DIR__ . '/../.cache';
$cache = new Psr16Cache(new FilesystemAdapter('', 0, $cacheDir));

$client = ProviderClient::make(
    apiKey: LiveEnv::apiKey(),
    transport: $transport,
    cacheConfig: new CacheConfig(store: $cache, ttl: 604800),
);
```

Файловый кеш часто становится хорошим provider-side default для production SDK, но остаётся
архитектурным решением самого SDK, а не контрактом ApiSutra.

## Response dumping <a id="section-10"></a>

Response dumping нужен не всегда. Это provider-side pattern для случаев, когда:
- ответы дорогие и их нужно анализировать без повторного вызова;
- есть file/download сценарии;
- в async-flow полезно сохранять промежуточные идентификаторы и состояния.

На практике для SDK с sync + async сценариями, бинарными ответами или слабой внешней
документацией response dumping часто оказывается не просто удобством, а рабочим инструментом
диагностики и воспроизводимости.

Один из возможных подходов:
- JSON-ответы сохранять как `{resource}/{method}.json`;
- файловые ответы сохранять как бинарник + `.meta.json`;
- при необходимости использовать дампы как источник состояния для следующих прогонов.

## Группировка тестов <a id="section-11"></a>

Группировка live-тестов имеет смысл, если suite достаточно большой. Для пары smoke-тестов
можно обойтись без отдельных групп.

Один из вариантов:
- `live-check` — preflight;
- `live-sync` — синхронные методы;
- `live-async` — async/polling flow;
- `live-negative` — проверка error-handling;
- `live-laravel` — container smoke.

## Makefile DX <a id="section-12"></a>

Makefile-обвязка полностью опциональна. Она удобна для большого live-suite, но не входит
в контракт ApiSutra.

Один из возможных вариантов:

```makefile
PEST = ./vendor/bin/pest
LIVE_ENV = .env.live.local

live-sync:
	@set -a; . $(LIVE_ENV); set +a; $(PEST) --group live-sync

live-one:
	@set -a; . $(LIVE_ENV); set +a; $(PEST) --filter "$(TEST)"
```

## .gitignore <a id="section-13"></a>

Обычно имеет смысл добавить:

```gitignore
.env.live.local
tests/Live/.cache/
tests/Live/Fixtures/live.dataset.php
.live-dumps/
```

## Live-тестирование (real-request) <a id="section-14"></a>

Для тестов, выполняющих реальные HTTP-запросы к API провайдера, см. отдельный гайд:
[Live-тестирование](../../guides/testing/live.md).

Что даёт core ApiSutra:
- `LiveEnvLoader` — загрузка `.env.live.local` без дополнительных зависимостей;
- `LivePolling::waitUntil()` — polling-хелпер для async/live flow;
- `LiveResultAssertions::assertSuccess()` и `assertDataInstanceOf()` — проверки technical success result-layer и ожидаемого типа `data()`.

Что остаётся на стороне SDK-провайдера:
- решение, нужны ли live-тесты вообще;
- env-конвенция конкретного SDK;
- `LiveEnv`, `LiveTestGuard`, `LiveClientFactory`, `LiveFixtureLoader`, `LiveResponseDumper`;
- структура `tests/Live`, Makefile/DX запуска, balance preflight, response dumping.

Практическое правило:
- если SDK небольшой, sandbox-ориентированный и покрывает основные риски через mock/unit-тесты, live-контур может быть минимальным;
- если SDK живёт поверх production-like API, включает async/file flows или дорогие запросы, live primitives и provider-side support-слой обычно стоит планировать сразу.
