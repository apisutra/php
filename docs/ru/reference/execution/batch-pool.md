<!-- languages --> <a href="../../../en/reference/execution/batch-pool.md">English</a> · <a href="batch-pool.md">Русский</a> <!-- /languages -->
# Batch и pool <a id="section-1"></a>

Pool управляет конкурентностью набора независимых запросов. Фактический
параллелизм HTTP зависит от транспорта.

Для больших или неизвестных источников используйте [consume/consumeAsync](pool-consumption.md): обработка без накопления результатов. `send()` и `sendAsync()` сначала подготавливают весь источник и возвращают полную коллекцию.

## Когда использовать pool <a id="section-2"></a>
- массовые запросы по списку идентификаторов
- параллельная загрузка справочников и зависимых ресурсов
- фоновые обновления, где порядок выполнения не важен

## Когда не подходит <a id="section-3"></a>
- если запросы зависят друг от друга или нужен строгий порядок
- если провайдер требует последовательного доступа (лучше `batch()->sequential()`)

## Настройки <a id="section-4"></a>
- `concurrency` — withConcurrency → явный аргумент → PoolConfig::concurrency → 5; отсутствие/null аргумента означает default
- `stopOnFailure` — прекратить запуск новых запросов после первого FAILED-результата; переопределение `withStopOnFailure()` → `PoolConfig::stopOnFailure` → false

`withConcurrency(int|callable|ConcurrencyResolverInterface $concurrency)` возвращает
копию; последнее переопределение приоритетнее аргумента создания и конфига. Исходный
pool не меняется. Resolver вызывается один раз с `(total, 0)` и обязан вернуть int:
строки, float, bool и null отвергаются с ConfigurationException до HTTP. Целые меньше
1 нормализуются к 1. Это правило общее для send/consume и их async-пар.
Для consume источник с resolver должен иметь известный размер; см. [контракт](pool-consumption.md).
Копирование builder не копирует его iterable. Для Generator создавайте новый источник
на каждый запуск, в том числе через разные копии builder.

По умолчанию `concurrency = 5`, `stopOnFailure = false`.
Меняйте `concurrency`, если провайдер ограничивает параллелизм
или нужно ускорить массовые запросы.

`withStopOnFailure(bool $enabled = true)` возвращает копию строителя пула, не меняя
исходный builder или конфигурацию клиента. Передайте `false`, чтобы отключить
настройку клиента для этого пула. Переопределение действует для `send()`, `sendAsync()`,
`consume()` и `consumeAsync()`. Начатые запросы завершаются и поступают своим обработчикам.

Для существующего `$client` и iterable `$requests` включите остановку для одного импорта:

```php
$summary = $client->pool($requests)->withStopOnFailure()->consume();
```

Нюансы:
- pool принимает **только** `RequestInterface`
- `concurrency` фиксируется при старте пула

## Базовая настройка <a id="section-5"></a>
```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\PoolConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    pool: new PoolConfig(stopOnFailure: true),
);
```

## Использование <a id="section-6"></a>
```php
$result = $client->pool($requests, 5)->send();
```

## Promise для pool <a id="section-7"></a>
`sendAsync()` возвращает `ResultPromiseInterface<PoolResult>`.

## Дополнительные параметры <a id="section-8"></a>
```php
use ApiSutra\Contracts\Interfaces\Concurrency\ConcurrencyResolverInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Result\ExecutionResult;
use Throwable;

final class ExampleConcurrencyResolver implements ConcurrencyResolverInterface
{
    public function getConcurrency(int $pending, int $completed): int
    {
        return $pending > 50 ? 10 : 5;
    }
}

$pool = $client->pool(
    $requests,
    new ExampleConcurrencyResolver(),
);

$result = $pool
    ->withResponseHandler(function (ExecutionResult $result, RequestInterface $request): void {})
    ->withExceptionHandler(function (Throwable $exception, RequestInterface $request): void {})
    ->send();
```

`ConcurrencyResolverInterface` выбирает лимит при запуске pool;
по мере завершения запросов лимит не пересчитывается.

Batch — это выполнение набора запросов, собранных **в рантайме**.
В отличие от Composite, список запросов формируется на месте, а результат —
`BatchResult` с вложенными `ExecutionResult`.

## Когда использовать batch <a id="section-9"></a>
- массовые запросы по списку идентификаторов
- сбор данных из разных endpoint’ов без жёсткой схемы
- простая конкурентная обработка (sequential/parallel)

## Базовое использование <a id="section-10"></a>
```php
use ApiSutra\Enums\Execution\ExecutionMode;
use ApiSutra\Enums\Execution\FailStrategy;

$result = $client
    ->batch($requests)
    ->withMode(ExecutionMode::Parallel)
    ->withFailStrategy(FailStrategy::Partial)
    ->withConcurrency(10)
    ->send();
```

## Конфигурация через BatchConfig <a id="section-11"></a>
Методы batch `withConcurrency(int)`, `withFailStrategy(FailStrategy)`, `withMode()`,
`parallel()` и `sequential()` тоже возвращают копии. Сохраняйте возвращённый builder:
вызов модификатора без использования результата не меняет исходный объект.
Callable/resolver конкурентности принимает только pool; batch принимает целое число.

```php
use ApiSutra\Config\BatchConfig;

$config = new BatchConfig(
    mode: ExecutionMode::Parallel,
    failStrategy: FailStrategy::Partial,
    concurrency: 10,
);

$result = $client->batch($requests, $config)->send();
```

По умолчанию: `Sequential`, `FailAll`, `concurrency = 5`.
Если не нужна кастомизация, достаточно базового `batch($requests)`.

## Какие элементы допустимы в batch <a id="section-12"></a>
Batch принимает **RequestInterface** или специальные формы:

1) **Готовый инстанс запроса**
```php
$requests = [
    new GetUser($id),
    new GetOrders($id),
];
```

2) **Callable** (получает parent‑request или null)
```php
$requests = [
    fn ($parent) => new GetUser($parent?->userId ?? '0'),
];
```

3) **class-string** (конструктор берёт значения из parent‑request)
```php
$requests = [
    GetUser::class,
];
```

Нюанс: если parent‑request отсутствует, параметры конструктора заполняются
дефолтами или `null`.

## Режимы выполнения <a id="section-13"></a>
- `ExecutionMode::Sequential` — строгий порядок
- `ExecutionMode::Parallel` — параллельное выполнение с ограничением concurrency

## FailStrategy <a id="section-14"></a>
- `FailAll`: прекратить запуск новых запросов после первого FAILED-ребёнка; уже выданные работы завершаются.
- `Partial` / `IgnoreErrors`: продолжить после FAILED-ребёнка. В batch обе стратегии
  сохраняют ошибочные дочерние результаты и ошибки; ни одна не делает агрегат успешным принудительно.

Стратегии управляют продолжением. Окончательный статус вычисляется по собранным
результатам: все успешны → SUCCESS, все ошибочны → FAILED, смесь или частичный ребёнок
→ PARTIAL. Пустой batch сейчас также имеет статус PARTIAL.
`throwOnErrors` применяется к этому итоговому статусу, а не к каждому внутреннему ребёнку:

| Пример при throwOnErrors true | Поведение |
| --- | --- |
| Partial, первый запрос ошибочен, два успешны | Выполнить все три; вернуть PARTIAL с данными/ошибками в дочерних результатах |
| Partial, все три запроса ошибочны | Выполнить все три; выдать исключение окончательного FAILED-агрегата |
| FailAll, первый запрос ошибочен, последовательный режим | Остальные не запускать; выдать исключение FAILED |

Агрегат PARTIAL возвращается нормально и при throwOnErrors true. Проверяйте его
`failed()`/`errors`, если частичный успех требует дополнительной обработки в приложении.

## Результат Batch <a id="section-15"></a>
`send()` возвращает `BatchResult`, где:
- `results()` — коллекция всех результатов
- `successful()` / `failed()` — отфильтрованные коллекции
- `meta()` — `BatchMeta` (total/success/failed/partial)

## Promise для batch <a id="section-16"></a>
`sendAsync()` возвращает `ResultPromiseInterface<BatchResult>`.

## Pool vs Batch <a id="section-17"></a>
- **Batch** — стратегия выполнения + fail‑policy (sequential/parallel).
- **Pool** — конкурентный запуск независимых запросов с обработчиками
  результатов и исключений.
Если важен порядок или нужна fail‑strategy — используйте batch.
Pool удобен для обработки результатов по мере их готовности.

Штатный Guzzle-адаптер выполняет HTTP конкурентно до заданного лимита. Callbacks идут
по готовности; коллекция сохраняет порядок входа. Неподдерживаемый async-транспорт
даёт configuration_error. Синхронные sequential batch и pool с concurrency 1
сохраняют поддержку синхронных транспортов. Async-точки требуют поддержки
конкурентности даже при лимите 1. Сохраняйте Promise и дождитесь его результата.

## Массовая загрузка (Batch) <a id="section-18"></a>
```php
$result = $client
    ->batch([GetUser::class, GetOrders::class])
    ->parallel()
    ->withConcurrency(5)
    ->send();
```

## Быстрый fan‑out (Pool) <a id="section-19"></a>
```php
$result = $client->pool($requests, 10)->send();
```

## Диагностика дочерних результатов <a id="section-20"></a>

В режиме throw/rejection элементы сохраняют trace, audit, debug, response и
исходную ошибку конкретной отправки. Callback-и сохраняют обычную доставку ошибок.
Самостоятельный batch/pool объединяет независимые корни: trace агрегата может быть
`null`. В Composite дети наследуют trace родителя и получают отдельные executionId.
[Корреляция и безопасные логи](../results/observability.md).

## Окончательная выдача и callbacks <a id="section-21"></a>

FailStrategy управляет запуском новых детей, throwOnErrors — выдачей окончательного
статуса. Partial/IgnoreErrors продолжают после failed-ребёнка в обоих режимах.
FailAll и stopOnFailure pool прекращают новые запуски, уже выданные Promise завершаются.
Окончательный FAILED бросает исключение/reject при throwOnErrors true;
SUCCESS/PARTIAL возвращаются нормально.

Выбор callback pool зависит от статуса: FAILED вызывает exception handler, если он
задан, иначе response handler. SUCCESS/PARTIAL вызывают response handler. Если callback
или его фабрика исключений падают, новые запросы и callbacks прекращаются; выданные
работы завершаются до выхода исходного сбоя. Автоматического throw агрегата после этого
нет. Канонические дети не меняются. [Выбор причины и число вызовов фабрики](../results/exceptions.md#section-4).
