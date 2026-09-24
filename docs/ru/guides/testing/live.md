<!-- languages --> <a href="../../../en/guides/testing/live.md">English</a> · <a href="live.md">Русский</a> <!-- /languages -->
# Организовать live-проверки SDK <a id="section-1"></a>

## Когда нужны live-тесты <a id="section-2"></a>

Unit-тесты с `MockTransport` проверяют логику SDK: сериализацию, гидрацию, маппинг ошибок.
Но есть задачи, которые они не покрывают:
- реальный контракт провайдера (поля, статусы, коды ошибок);
- поведение при rate-limit, таймаутах, нестабильной сети;
- корректность async/polling flow с реальными задержками;
- бинарные ответы (файлы, архивы).

Live-тесты особенно важны, когда:
- у провайдера нет sandbox или тестового стенда;
- запросы платные и нужен контроль расхода;
- ответы зависят от реальных данных внешней системы.

Live-тесты обычно не нужны или могут быть сильно упрощены, если:
- у провайдера есть стабильный sandbox с предсказуемыми тестовыми данными;
- все рискованные сценарии уже хорошо покрываются mock/unit-тестами;
- SDK не работает с файлами, async-flow или дорогими endpoint-ами.

## Принципы <a id="section-3"></a>

- Сильно рекомендуется: ручной запуск. Для production-like, платных или нестабильных endpoint-ов live-тесты обычно не запускаются в CI автоматически.
- Сильно рекомендуется: env-gating. Без явного флага и credentials тесты лучше пропускать (`skip`), а не падать.
- Рекомендуется: минимальный конфиг. Держите обязательные настройки live-suite небольшими.
- Опционально: кеширование. Полезно для дорогих или повторяемых сценариев, но не обязательно для каждого SDK.
- Опционально: response dumping. Нужен, если ответы дорогие, большие, file-based или требуют анализа вне теста.
- Опционально: группировка. Имеет смысл для большого live-suite; для нескольких smoke-тестов может быть избыточной.

## Когда включать какой функционал <a id="section-4"></a>

### Базовый минимум <a id="section-5"></a>

- `LiveEnvLoader` нужен почти всегда, если в SDK вообще есть live-тесты.
- `LiveResultAssertions` почти всегда стоит использовать как технический baseline перед business-checks.
- `LiveTestGuard` или эквивалентная skip-логика практически обязательны, если live-запуск зависит от env и credentials.

### Рекомендуется по умолчанию <a id="section-6"></a>

- `record/playback` обычно стоит включать, если API платный, медленный, rate-limited или заметно недетерминированный.
- provider-side `LiveClientFactory` обычно окупается, если live-клиент требует отдельные timeout/cache/debug настройки.
- группировка live-suite почти всегда полезна, когда в SDK больше нескольких live-сценариев.
- встроенные helper-методы ApiSutra особенно полезны, когда live-тесты должны быть единообразными между несколькими пакетами.

### Нужен при определённых условиях <a id="section-7"></a>

- `LivePolling::waitUntil()` нужен, если у провайдера есть async-job, status endpoint, eventual consistency или отложенная готовность результата.
- response dumping сильно рекомендуется, если API асинхронный, file-based, плохо документирован или требует пост-анализа ответа вне теста.
- live-cache нужен, если запросы дорогие, медленные, лимитированные или часто повторяются при отладке.
- negative live-suite нужен, если ошибки составляют существенную часть DX SDK и для них есть дешёвые и безопасные сценарии.
- preflight-проверки стоимости/баланса нужны, если API платный или имеет чувствительные лимиты.
- Laravel smoke имеет смысл, если пакет поставляет service provider, container bindings, config integration или facade/DI слой.

### Операторское удобство <a id="section-8"></a>

- `LiveFixtureLoader` полезен, если живые сценарии требуют наборов входных данных и их приходится переиспользовать между тестами.
- `LiveResponseDumper` полезен, если команда расследует нестабильные ответы, сохраняет бинарные файлы или использует дампы как временный источник состояния.
- Makefile или эквивалентный task-runner удобен, если suite запускается разными группами и его используют не только автор пакета.

## Структура <a id="section-9"></a>

Один из возможных provider-side вариантов:

```text
tests/
├── Unit/
├── Integration/
└── Live/
    ├── Sync/
    ├── Async/
    ├── Negative/
    ├── Laravel/
    ├── Fixtures/
    │   ├── live.dataset.example.php
    │   └── live.dataset.php
    ├── Support/
    │   ├── LiveEnv.php
    │   ├── LiveTestGuard.php
    │   ├── LiveClientFactory.php
    │   ├── LiveFixtureLoader.php
    │   └── LiveResponseDumper.php
    └── .cache/
```

Это только пример. Для части SDK хватит пары файлов, а отдельный `Support/` может быть не нужен.

## Support-слой <a id="section-10"></a>

Рекомендуемый provider-side набор классов:

| Класс | Назначение |
|-------|------------|
| `LiveEnv` | Чтение env через `getenv()`. Типизированные методы: `apiKey()`, `baseUrl()`, `isEnabled()`. Не знает о файлах. |
| `LiveTestGuard` | Skip-логика: пропустить тест, если флаг запуска или credentials не установлены. |
| `LiveClientFactory` | Создание клиента с `HttpTransport`. Кеш, timeouts и прочие live-настройки добавляются только если это оправдано для провайдера. |
| `LiveFixtureLoader` | Загрузка входных данных из `live.dataset.php`. |
| `LivePolling` | **ApiSutra.** `ApiSutra\Testing\LivePolling::waitUntil(callback, isReady, timeout)` для async/polling flow. Вызовы с `->withoutCache()`. |
| `LiveResultAssertions` | **ApiSutra.** `assertSuccess()` и `assertDataInstanceOf()`. Проверка technical success result-layer и DTO-контракта. |
| `LiveResponseDumper` | Опциональный helper для сохранения ответов в файлы. |

## Env-конвенция <a id="section-11"></a>

### Файлы <a id="section-12"></a>

Обычно в корне SDK-пакета:
- `.env.live.example` — шаблон без секретов;
- `.env.live.local` — реальные значения, в `.gitignore`.

### Обязательные переменные <a id="section-13"></a>

Стремитесь к минимуму:

```env
PROVIDER_LIVE_TESTS=1
PROVIDER_API_KEY=your-key-here
```

Остальное задавайте только если это действительно нужно конкретному SDK.

### Загрузка <a id="section-14"></a>

ApiSutra предоставляет `LiveEnvLoader`:

```php
// tests/bootstrap.php
require_once __DIR__ . '/../vendor/autoload.php';

\ApiSutra\Testing\LiveEnvLoader::loadForTests(__DIR__);
```

`loadForTests(__DIR__)` ищет `.env.live.local` в корне пакета. Файл опционален.
Существующие переменные окружения не перезаписываются.

### Provider-side reader <a id="section-15"></a>

Один из возможных вариантов:

```php
final class LiveEnv
{
    public static function isEnabled(): bool
    {
        $value = getenv('PROVIDER_LIVE_TESTS');
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function apiKey(): string
    {
        return (string) getenv('PROVIDER_API_KEY');
    }

    public static function baseUrl(): string
    {
        $url = getenv('PROVIDER_LIVE_BASE_URL');
        return is_string($url) && $url !== '' ? $url : 'https://api.provider.com/v1';
    }
}
```

## Разделение данных: env vs фикстуры <a id="section-16"></a>

Чёткое разграничение:

| Что | Где | Пример |
|-----|-----|--------|
| Credentials (API-ключи) | `.env.live.local` | `PROVIDER_API_KEY=abc123` |
| Конфигурация (base URL, флаги) | `.env.live.local` | `PROVIDER_LIVE_TESTS=1` |
| Входные данные для запросов | `live.dataset.php` | Идентификаторы, адреса, даты |

Фикстуры входных данных в PHP-файле удобны, но это provider-side pattern, а не требование core ApiSutra.

```php
// tests/Live/Fixtures/live.dataset.example.php
return [
    'entity_id' => 'EXAMPLE-ID-123',
    'search_query' => 'Пример запроса',
    'date' => '2025-01-15',
];
```

## Negative-тесты <a id="section-17"></a>

Это опциональный слой. Добавляйте его, если:
- у провайдера есть устойчивые и безопасные negative-сценарии;
- ошибки составляют важную часть DX SDK;
- стоимость таких проверок контролируема.

Рекомендации:
- используйте только дешёвые методы;
- выбирайте безопасные сценарии: невалидный формат идентификатора, пустые обязательные поля;
- не используйте валидный формат с несуществующими данными, если провайдер может списать за попытку;
- проверяйте тип ошибки, error code, контекст и корректность маппинга.

## Контроль стоимости <a id="section-18"></a>

Этот раздел актуален только для платных или лимитированных API. Для бесплатного sandbox
он обычно не нужен.

Возможные provider-side меры:
- preflight-проверка баланса;
- кеш для снижения повторных списаний;
- вывод дельты баланса до/после прогона;
- ручной запуск только осознанных сценариев.

## Sandbox и тестовые данные <a id="section-19"></a>

До старта разработки выясните:
- есть ли у провайдера sandbox/тестовый стенд и отдельный baseUrl
- какие тестовые ключи/креды доступны и как они отличаются от production
- есть ли ограничения по rate‑limit для sandbox
- есть ли официальные примеры/фикстуры/статические ответы

Это важно, чтобы заранее продумать конфигурацию и архитектуру:
- разделить `ClientConfig` для prod/sandbox
- подготовить стратегию моков/фикстур (`MockTransport`, record/playback)
- заложить точки расширения для тестов (hooks/extension)

Подробности: [Тестирование](unit.md).
