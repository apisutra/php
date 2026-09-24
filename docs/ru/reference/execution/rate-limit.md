<!-- languages --> <a href="../../../en/reference/execution/rate-limit.md">English</a> · <a href="rate-limit.md">Русский</a> <!-- /languages -->
# Квоты запросов <a id="section-1"></a>

По умолчанию ограничения выключены (`ClientConfig::rateLimit = null`). Для локальной
общей квоты достаточно одной настройки; Redis, Laravel и префиксы не нужны:

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RateLimitConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    rateLimit: new RateLimitConfig(limit: 100, period: 60),
);
```

`rateLimit` ограничивает суммарное число разрешений для участвующих операций клиента.
Атрибут операции добавляет независимый предел:

```php
use ApiSutra\Attributes\Behavior\RateLimit;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;

#[Get('/reports')]
#[RateLimit(limit: 5, period: 60, behavior: RateLimitBehavior::Throw)]
final class ReportsRequest extends AbstractRequest {}
```

При общей квоте 100/min пять отчётов расходуют по пять единиц обоих счётчиков.
Шестой отчёт не расходует ни одной квоты. Если общий остаток равен двум, можно
выполнить только два отчёта, даже когда собственная квота ещё свободна.

## Параметры <a id="section-2"></a>

| RateLimitConfig | По умолчанию | Значение |
| --- | --- | --- |
| limit | 100 | Положительное число разрешений |
| period | 60 | Положительная длительность окна в **секундах** |
| behavior | RateLimitBehavior::Wait | Ожидание либо локальный отказ Throw |
| store | null | PSR-16 путь только для одной квоты без явного backend |
| key | null | Явная группа квоты; автоматические ключи описаны ниже |

| ClientConfig | По умолчанию | Значение |
| --- | --- | --- |
| rateLimit | null | Общая квота |
| includeClientQuota | true | Участие операций, если атрибут не уточнил его |
| rateLimitBackend | null | Атомарный backend; без него используются локальные счётчики или совместимый одиночный PSR-16 путь |

Период должен быть представим в микросекундах штатного sleeper
(`period <= intdiv(PHP_INT_MAX, 1_000_000)`); переполнение конца окна также отклоняется.
Неположительные значения дают ConfigurationException. Нулевой limit не выключает механизм.

## Участие и overrides <a id="section-3"></a>

Для AbstractRequest собственная квота выбирается по приоритету: runtime options →
настройка экземпляра → атрибут с полной парой → собственная квота отсутствует.
Общая квота берётся отдельно из ClientConfig.rateLimit и не заменяется этим выбором.

- `#[RateLimit(limit: 5, period: 60)]` добавляет собственную квоту.
- `includeClientQuota: null` или отсутствие аргумента наследует клиентский флаг.
- `#[RateLimit(limit: 5, period: 60, includeClientQuota: false)]` учитывает только собственную.
- `#[RateLimit(includeClientQuota: false)]` исключает общую; без собственного runtime
  override квот у такого запроса нет. Числа общей квоты не копируются в собственную.
- `#[RateLimit(includeClientQuota: true)]` включает участие при клиентском default=false.
  Если общей квоты нет, флаг её не создаёт.
- `withoutRateLimit()` полностью отключает квоты данного запуска; `withRateLimit()`
  снова включает ограничение и задаёт собственную квоту по действующему приоритету overrides.

Пара limit/period задаётся целиком либо отсутствует. Пустой атрибут не меняет настройки;
key или отличный от Wait behavior без пары отклоняются. Валидация значений атрибута
происходит при применении в выполнении, до backend/HTTP; чтение metadata/inventory
не применяет квоты. Для RequestInterface вне AbstractRequest используется путь
без introspection атрибутов и runtime overrides: только клиентское правило участия/квоты.

## Backend и ключи <a id="section-4"></a>

Без внешнего backend состояние принадлежит экземпляру клиента и переживает переключение
fake/record/playback. Новый клиент создаёт независимые локальные счётчики. Локальные
окна используют monotonicMs и не сдвигаются при изменениях календарных часов.

В атомарном пути общая квота имеет отдельную группу client; собственная — группу
operation и класс запроса по умолчанию. `key` объединяет операции явно. Одинаковый текст
key общей и собственной квот не объединяет их друг с другом. Идентификаторы хешируются;
query/body/credentials не добавляются в них. В действующем окне одна группа должна
иметь одинаковые limit/period: конфликт даёт configuration_error без сброса счётчика.
После окончания окна допустимо новое определение.

Для нескольких workers используйте готовый
[Redis/phpredis backend](../integrations/redis.md). Он принимает весь набор одним
атомарным действием. Redis подключается явно, SDK не выбирает его по наличию Laravel.

PSR-16 store сохранён для **одной применимой квоты при rateLimitBackend=null**. Для
собственной квоты используются её store либо store клиента. Ключ этого пути:
хеш `apisutra.rate-limit.v2:` + custom key/baseUrl. Прямой RateLimiter::acquire тоже
принимает передаваемый ключ и Unix-секунды. get/set не гарантируют
межпроцессной атомарности, даже если сам cache store использует Redis.

Две квоты со store либо явный backend вместе со store несовместимы. Ошибка возникает
до чтения/записи и HTTP; конфликт клиентского store/backend — уже при создании конфига.
SDK не игнорирует store и не списывает половину набора в отдельное хранилище.
Настройки HTTP/auth cache от этого не меняются.

## Ожидание и ошибки <a id="section-5"></a>

Одна фактическая HTTP-попытка получает по одному разрешению каждой квоты. Retry,
auth recovery, дочерние запросы и страницы также учитываются; cache hit и composite
обёртка без HTTP квоту не расходуют. Алгоритм — fixed window с первым разрешением
в начале окна; это не sliding window/token bucket и не гарантия отсутствия всплеска
на границе двух окон.

RateLimiter организует ожидание для всех backend. При нескольких блокирующих Wait
ждёт максимум их сроков и проверяет весь набор заново. Неблокирующая Throw-квота не
мешает ожиданию. Если хотя бы одна **блокирующая** квота имеет Throw — немедленный
RateLimitException; retryAfter равен максимуму всех блокирующих окон, округлённому
вверх в секунды. Это оценка, а не резервирование будущего места.

Ожидание и I/O входят в общий `RetryConfig.totalTimeoutMs`, если он задан. Это отдельная
настройка от HTTP timeout. После истечения бюджета HTTP не начинается. Доступные локальные
проверки transport/timeouts/body/destination выполняются до списания разрешения.

| Ситуация | Результат |
| --- | --- |
| Исчерпание с Throw | rate_limited, reason=local_rate_limit_exceeded, retryAfter в секундах |
| Ошибка backend | execution_error, reason=rate_limit_backend_error, stage=rate_limit_store; без автоматического retry |
| Deadline | timeout с соответствующим этапом |
| Некорректная конфигурация/конфликт определения | configuration_error до HTTP |

Действуют стандартные result-first, throwOnErrors и Promise-контракты. Локальный отказ
не создаёт HTTP 429: RateLimitException.response=null. Если предыдущая попытка получила
ответ, исключение сохраняет lastResponse и итоговый результат может содержать именно его.
Сырой previous доступен явно; стандартная диагностика не раскрывает текст ошибки backend.

Разрешение не равно доставленному HTTP. После grant может истечь deadline, пропасть
соединение или остановиться worker. Автоматического refund нет: SDK не знает,
дошёл ли запрос до внешнего API. Потеря состояния backend может обнулить квоты.

## Собственный атомарный backend <a id="section-6"></a>

Реализуйте RateLimitBackendInterface из ApiSutra\RateLimiting:
`tryAcquire(array $quotas, ?int $timeoutMs = null): RateLimitDecision`.
RateLimitQuota содержит key, limit и **periodMs**. Backend возвращает granted либо
blockedIds и положительный **retryAfterMs**, проверяя все квоты до записи. Совпадающие
ID нормализуются в одно списание; противоречивые определения отклоняются. Backend не
спит и не выполняет HTTP. timeoutMs — оставшаяся длительность обращения, не абсолютное
время. Общий Wait/Throw и повторную проверку после ожидания обеспечивает RateLimiter.

## Выбор квот <a id="section-7"></a>

Клиентский rateLimit задаёт общую квоту. Лимит операции дополняет её:
отчёт с лимитом 5/min расходует квоту операции и общую квоту 100/min.
Для явного исключения используйте includeClientQuota=false; если нужны только
лимиты операций, не задавайте клиентскую квоту. Параметры атрибута limit/period могут быть null.

Для одного экземпляра используйте локальный backend, для workers — Redis с общим scope.
PSR-16 store поддерживает только одну квоту без явного backend.
Проверка поддержки timeout выполняется до acquire, в том числе на PSR-16 пути:
неверная конфигурация транспорта вызывает ошибку до расходования квоты.

## Rate Limit (уровень клиента) <a id="section-8"></a>
```php
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;

$config = $config->with(rateLimit: new RateLimitConfig(
    limit: 60,
    period: 60,
    behavior: RateLimitBehavior::Wait,
));
```

## Rate Limit для конкретного запроса <a id="section-9"></a>
```php
use ApiSutra\Attributes\Behavior\RateLimit;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;

#[RateLimit(limit: 10, period: 1, behavior: RateLimitBehavior::Throw)]
final class Search extends AbstractRequest {}
```

`ClientConfig::rateLimit` задаёт общую квоту; атрибут и `withRateLimit()` задают
дополнительную квоту операции. Перед каждой HTTP-попыткой разрешение нужно по обеим.
`includeClientQuota: false` исключает общую квоту, `withoutRateLimit()` отключает все.
Без настройки backend учёт локальный, в экземпляре клиента. Общая квота имеет одну группу client, собственная — по классу запроса; custom key объединяет
операции. Пространства общей и собственной квот различаются.

PSR-16 store сохранён только для одной применимой квоты и не гарантирует атомарности
между процессами. Для нескольких workers есть необязательный
[Redis backend](../integrations/redis.md). Defaults, ожидание, ключи и изменения совместимости:
[полный контракт rate-limit](rate-limit.md).


Серверный Retry-After учитывает независимый [cooldown](cooldown.md). withoutRateLimit
его не отключает; Redis backend квот не разделяет cooldown между клиентами/процессами.

## Область отказа вместо ожидания <a id="admission-scope"></a>

Интеграция может ограничить вызовы областью AdmissionScope: фактический отказ локального
допуска выходит управляющим AdmissionRefused до обычного FAILED и фабрики исключений.
Конфигурация клиента, реальные HTTP-квоты, auth и retry сохраняются; другие области
независимы. Это управляющий механизм, не диагностическое событие и не серверный HTTP 429.
[Middleware Laravel](https://github.com/apisutra/laravel/blob/master/docs/ru/reference/integrations/queue.md)
применяет его к повторяемой job. Без такой области send/sendAsync остаются result-first.
Ядро не требует worker или очереди.
