<!-- languages --> <a href="../../../en/reference/execution/retry.md">English</a> · <a href="retry.md">Русский</a> <!-- /languages -->
# Повторные попытки <a id="section-1"></a>

Настройки повторных попыток в `RetryConfig` и дефолтной политики.

## Базовая настройка <a id="section-2"></a>
```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\RateLimiting\BackoffStrategy;
use ApiSutra\Exceptions\Transport\ConnectionException;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    retry: new RetryConfig(
        attempts: 3,
        baseDelay: 200,
        maxDelay: 5000,
        backoff: BackoffStrategy::Exponential,
        jitter: true,
        retryOn: [408, 429, 500, 502, 503, 504],
        retryExceptions: [ConnectionException::class],
        totalTimeoutMs: 10_000,
    ),
);
```

## Параметры RetryConfig <a id="section-3"></a>
- `attempts` — максимум основных HTTP-попыток, включая первую
- `baseDelay` — базовая задержка (мс)
- `maxDelay` — максимум задержки (мс)
- `backoff` — стратегия (constant/linear/exponential)
- `jitter` — случайная прибавка к задержке
- `retryOn` — HTTP‑коды для повторов
- `retryExceptions` — исключения, при которых повторяем
- `totalTimeoutMs` — необязательный общий бюджет выполнения в мс, по умолчанию null;
  включает HTTP, auth и ожидания, сохраняется при отключении retry. Контракт и границы
  гарантии — в [Timeouts & Delay](deadlines.md)
- `safeMethods` — необязательный список `HttpMethod`, по умолчанию GET/PUT/DELETE;
  переданный список заменяет стандартный, пустой список запрещает повтор без разрешения
  на классе запроса

## Переопределение на запросе <a id="section-4"></a>
Используйте `#[Retry]` или runtime‑опции (`withRetry()`/`withoutRetry()`).

`#[Retry(safe: true/false)]` переопределяет условную политику запроса и
safeMethods клиента. Отсутствующий safe и safe: null обращаются к необязательному
[RetrySafetyPolicyInterface](retry.md#section-8),
затем наследуют конфиг. Runtime
withRetry меняет число попыток, сохраняя остальные настройки; безопасность POST/PATCH
его вызовом не подтверждается. `#[Retry(enabled: false)]` отключает общие повторы,
runtime может явно переопределить это значение.

Полный контракт: [Retry и Rate Limit](retry.md).

Если `retry` не задан в `ClientConfig`, повторы отключены по умолчанию.

Retry повторяет запросы при ошибках, Rate Limit ограничивает скорость вызовов.
Rate Limit применяется независимо от идемпотентности операции.

### Задержка для одного исполнения <a id="runtime-delay"></a>

Настройка необязательна. Без `withRetryDelay()` задержки берутся из атрибута запроса
или настроек клиента; существующие вызовы менять не нужно.

Для существующего `$request` включите до трёх попыток с постоянной паузой в 1 секунду:

```php
use ApiSutra\Enums\RateLimiting\BackoffStrategy;

$result = $request->withRetry(3)->withRetryDelay(
    baseDelay: 1000,
    maxDelay: 1000,
    backoff: BackoffStrategy::Constant,
    jitter: false,
)->send()->raw();

// Наследовать задержки и стратегию, переопределив только jitter.
$execution = $request->withRetryDelay(jitter: false);
$inherited = $execution->withoutRetryDelay();
```

`withRetryDelay(?int $baseDelay = null, ?int $maxDelay = null,
?BackoffStrategy $backoff = null, ?bool $jitter = null)` доступен в RequestOptions
и fluent API запроса/исполнения. Задержки задаются в миллисекундах; null наследует
атрибут запроса или клиент, а 0 и false — явные значения.
Приоритет: runtime → `#[Retry]` → ClientConfig. Атрибут по-прежнему задаёт все свои
поля задержки. Частичная настройка доступна через
`RequestOptions::getRetryDelayOverride()` как `?RetryDelayOverride` из `VO\Retry`.

- Setter не включает retry и не разрешает повтор небезопасной операции.
- Каждый вызов заменяет override задержки целиком: пропущенные поля снова наследуются.
- `withRetry()` и `withoutRetry()` сохраняют его; `withoutRetryDelay()` снимает только его.
- Каждая операция возвращает новый снимок, не изменяя исходный запрос.
- Явные опции исполнения — единый runtime-источник enabled, attempts и задержки,
  даже если они пусты или очищены. Runtime-getters запроса используются только
  при отсутствии опций исполнения; fallback между полями этих источников отсутствует.

Пустой вызов/все null или отрицательные baseDelay/maxDelay бросают ConfigurationException
непосредственно из setter, независимо от throwOnErrors. Для очистки используется
withoutRetryDelay(). Итоговое `maxDelay >= baseDelay` проверяется только при разрешении
включённого retry. Некорректная комбинация, в том числе с унаследованными значениями,
даёт `configuration_error` (или исключение при throwOnErrors) до HTTP-отправки исходного
запроса; авторизация к этому моменту могла выполниться. MaxDelay не увеличивается
автоматически. Существующий `withRetry(0)` сохраняет проверку при исполнении.

Списки статусов/исключений retry, безопасность, auth retries и общий бюджет не меняются.
Пользовательский RetryDelayPolicyInterface получает итоговый RetryConfig. Retry-After
остаётся серверным минимумом: maxDelay ограничивает backoff, но не Retry-After.
Cooldown и бюджет исполнения по-прежнему ограничивают ожидание.
`withDelay()` — отдельная задержка запроса.

## Retry (уровень клиента) <a id="section-5"></a>
Пример конфигурации выше задаёт значения клиента по умолчанию.

Дополнительно:
- `retryExceptions` — повтор при исключениях (например, ConnectionException)
- `totalTimeoutMs` — необязательный общий бюджет pipeline, включая HTTP, авторизацию
  и ожидания. `withoutRetry()` его не отменяет. Исчерпание окончательно; контракт и
  границы гарантии — в [Timeouts & Delay](deadlines.md).

### Безопасность повторов без обязательной настройки <a id="section-6"></a>

Общие повторы по умолчанию выключены (`ClientConfig::retry = null`). При их включении
без дополнительных настроек безопасности разрешены GET, PUT и DELETE. POST/PATCH
требуют явного подтверждения безопасности операции. Настроенный статус, исключение
или число попыток сами по себе такого разрешения не дают.

Для отдельного API можно изменить список на уровне клиента:

```php
use ApiSutra\Enums\Http\HttpMethod;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    retry: new RetryConfig(safeMethods: [HttpMethod::GET]),
);
```

`safeMethods` необязателен; по умолчанию это GET/PUT/DELETE. Переданный список заменяет
стандартный, а пустой список запрещает повторы без разрешающего атрибута запроса.
Добавляйте POST/PATCH только при гарантии безопасности этих методов для данного API.

На классе запроса `#[Retry(safe: true)]` разрешает повтор, `safe: false` запрещает.
Неуказанный `safe` и явный `safe: null` равнозначны: применяется политика клиента.
Без атрибута безопасность также определяется конфигом. Разрешение операции не
обходит лимит попыток, отключение retry или невозможность восстановить тело.

## Retry для конкретного запроса <a id="section-7"></a>
```php
use ApiSutra\Attributes\Behavior\Retry;
use ApiSutra\Core\AbstractRequest;

#[Retry(attempts: 5, backoff: BackoffStrategy::Linear)]
final class GetUsers extends AbstractRequest {}
```

Runtime‑override: `withRetry()` и `withoutRetry()`.
Включайте retry, когда контракт API подтверждает безопасность повтора: операция
идемпотентна или провайдер поддерживает ключ идемпотентности для неё.
SDK не проверяет гарантии внешнего API автоматически. `#[Idempotent]` и наличие
заголовка Idempotency-Key не являются разрешением повтора; безопасность объявляется
через `safe`, условную политику запроса или `safeMethods` клиента.

### Условная безопасность запроса <a id="section-8"></a>

Необязательный RetrySafetyPolicyInterface позволяет разрешить POST только после
определённого ответа, сохраняя настроенный сетевой retry GET на том же клиенте:

```php
use ApiSutra\Attributes\Behavior\Retry;
use ApiSutra\Attributes\Http\Post;
use ApiSutra\Contracts\Interfaces\Concurrency\RetrySafetyPolicyInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\VO\Http\ProviderResponse;
use Throwable;

#[Post('/messages')]
#[Retry(attempts: 3)]
final class SendMessageRequest extends AbstractRequest implements RetrySafetyPolicyInterface
{
    public function isRetrySafe(HttpMethod $method, ?ProviderResponse $response, ?Throwable $exception): ?bool
    {
        return $exception === null && $response?->status === 429;
    }
}
```

Применяйте такую политику, только если контракт провайдера подтверждает безопасность
повтора после 429. Для иных случаев метод может проверить бизнес-код ответа.
При сетевом сбое передаётся exception и response=null, предыдущий ответ не подставляется.
После исключения hook, получившего ответ, могут присутствовать оба значения.

Приоритет: явный `#[Retry(safe: true/false)]` → результат `isRetrySafe()` →
`RetryConfig::safeMethods`. `null` из политики означает fallback. Поэтому в примере
нет `safe: true`: он перекрыл бы условную проверку. Без интерфейса safety определяют
явный атрибут и `safeMethods`. Политика не требует изменений ClientConfig или обязательного
RequestInterface. Общую политику
SDK провайдера можно реализовать в его базовом классе запросов.

Метод вычисляет safety без I/O и может вызываться повторно при подготовке auth retry.
Он не инициирует retry: сначала нужна причина повторить запрос, затем учитываются
safety, attempts, body replay и budget. `true` не обходит остальные ограничения.
Проверка распространяется и на auth retry. Исключение из политики завершает вызов
с `execution_error`, reason `retry_safety_check_failed`; исходное исключение
сохраняется в previous, его сообщение не подставляется в публичное сообщение ошибки.

`Retry-After` разбирается одинаково для встроенного ожидания и HTTP
`RateLimitException::retryAfter`: поддерживаются секунды и HTTP-date. В исключении
значение выражено в секундах; отсутствующее/невалидное/отрицательное/слишком большое
значение даёт null, корректный ноль или прошедшая дата — 0. Встроенное ожидание
использует ms для статусов 429/503 и учитывает общий бюджет.

`attempts` включает первую основную HTTP-попытку. `#[Retry(enabled: false)]` и
`withoutRetry()` отключают общие повторы; runtime имеет приоритет над атрибутом.
`withRetry(attempts)` сохраняет остальные настройки клиента/атрибута, включая
`safeMethods`, `retryExceptions` и `totalTimeoutMs`. Runtime-методы возвращают отдельный
execution: используйте возвращённое значение, исходный запрос не изменяется.

### RetryableException и Retry‑After <a id="section-9"></a>
- `RetryableException` запрашивает повтор, но не обходит выключенный retry, безопасность,
  воспроизводимость тела и лимит попыток. `maxAttempts` может дополнительно уменьшить
  лимит; `retryAfter` задаётся в секундах. При невозможном повторе исключение сохраняется.
- Встроенный обработчик не делает backoff перед первой попыткой. Первый повтор
  использует baseDelay, следующие — выбранную стратегию и jitter с ограничением maxDelay.
- Для 429 и 503 учитывается `Retry-After`: целые неотрицательные секунды или HTTP-date.
  Дата в прошлом даёт 0; отрицательные, дробные, некорректные и выходящие за безопасный
  числовой диапазон значения игнорируются. Встроенное ожидание равно
  `max(backoff, Retry-After)`. maxDelay ограничивает backoff, а не серверное ожидание.
- При исчерпании попыток или отказе в повторе retry-ожидания нет.

Перед дополнительной отправкой SDK автоматически восстанавливает тело. Если это
невозможно, сохраняются исходный ответ/исключение и диагностическая причина
`retryRefusalReason` в контексте ошибки и warning-логе: `operation_not_safe`,
`body_not_replayable`, `body_rewind_failed` или `body_changed`.
Тело, выбранное в `BeforeSend`, становится исходным для повторов. Последующая
замена через `withBody()`/`withStream()` или очистка через `withoutBody()` даёт
`body_changed`: сохраняется исходный ответ/сбой, второе тело не отправляется.
Контракт [замены тела](transport.md#section-3) не меняет
политику безопасности операции.
Подробности: [повторы файлов](../files/uploads.md#section-3).

## Идемпотентность <a id="section-10"></a>
```php
use ApiSutra\Attributes\Behavior\Idempotent;

#[Idempotent]
final class CreateOrder extends AbstractRequest {}
```

Нюансы:
- заголовок по умолчанию берётся из `ClientConfig::idempotencyHeader`
- можно переопределить через `#[Idempotent(header: 'X-Idempotency')]`
- runtime‑ключ: `withIdempotencyKey('...')`
- если ключ не задан, SDK генерирует его автоматически один раз на выполнение;
  все попытки используют один ключ, независимые выполнения получают разные ключи
- пользовательский ключ, включая уже переданный заголовок, сохраняется

Меняйте заголовок, если провайдер ожидает другое имя или уже использует свой стандарт.

### и refresh <a id="section-11"></a>
Если `authRetryOn401 = true`, SDK после 401 пробует повторную аутентификацию и отправку
в пределах отдельного authRetryAttempts. `withoutRetry()` самостоятельно не отключает
этот механизм. Проверки безопасности операции и тела действуют и здесь: POST/PATCH
требуют явного разрешения. Auth retry не добавляет обычный backoff.
Повтор разрешается только после успешного refresh с типизированным DTO и
`processTokenResponse()` либо после обнаружения обновлённого TokenAuthenticator в кеше.
Без auth или при `getRefreshRequest() === null` сохраняется исходный 401. Явная обычная
политика `retryOn: [401]` продолжает работать в пределах обычных attempts и backoff.
Отказ refresh не запускает повтор основной операции, даже при `retryExceptions: [Throwable::class]`.
[Ошибки](../auth/tokens.md#section-11).

## Файловые операции <a id="section-12"></a>

Binary/multipart upload сохраняют начальную позицию при разрешённом повторе.
Non-seekable upload допускает одну отправку; после отказа от строковой копии
это правило действует и для binary. Каждая попытка download получает отдельный
временный файл; пользовательский sink заполняется только окончательным результатом.
Локальные ошибки чтения/записи не запускают HTTP повтор даже при широком
`retryExceptions`. Подробный контракт — [файлы](../../guides/recipes/files.md).

## Отказ ожидания auth refresh <a id="section-13"></a>

`auth_refresh_lock_timeout` и `auth_lock_backend_error` не запускают общий HTTP retry,
даже если `retryExceptions` включает их базовые классы. Основной запрос после такого
отказа не отправляется повторно. Для 401 сохраняется полученный HTTP-контекст;
подробности — [refresh lock](../auth/tokens.md#section-7).

## Политика задержки и транспортные декораторы <a id="delay-policy"></a>

RetryDelayCalculator реализует RetryDelayPolicyInterface::delayMs(RetryConfig, int): int.
Номер повтора начинается с 1. Политика возвращает неотрицательные представимые миллисекунды;
она не выполняет I/O, sleep и отправку HTTP. Исключение или недопустимая задержка дают
configuration_error. Политику можно внедрить в низкоуровневый Pipeline/RetrySender.
Второго отправляющего handler или отдельного async retry нет.

```php
use ApiSutra\Config\RetryConfig;
use ApiSutra\Contracts\Interfaces\Retry\RetryDelayPolicyInterface;

final class FixedRetryDelay implements RetryDelayPolicyInterface
{
    public function delayMs(RetryConfig $config, int $retryNumber): int
    {
        return min(250, $config->maxDelay);
    }
}
```

Транспортный декоратор — попыточное расширение для метрик или синтетических ответов.
Реализуйте send и sendAsync с сохранением capabilities таймаутов, назначения, файлов и
конкурентности. Он вызывается после scope/body/replay, cooldown и допуска квоты; локальный
отказ не входит в декоратор. BeforeSend работает один раз на исполнение, а не на попытку.
Credentials/origin/метод/body подготавливайте до допуска: их замена внутри декоратора
делает прежние проверки недействительными. SDK не изолирует произвольный транспортный код.

Для расчёта backoff используйте delay policy, для попыточного поведения отправки —
транспортный декоратор, для однократной подготовки — BeforeSend. Pipeline ждёт до
получения квоты и отделяет [общий cooldown](cooldown.md) от безопасности retry.
