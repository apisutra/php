<!-- languages --> <a href="../../../en/reference/execution/pool-consumption.md">English</a> · <a href="pool-consumption.md">Русский</a> <!-- /languages -->
# Обработка pool без накопления результатов <a id="section-1"></a>

Используйте `consume()` для большого или заранее неизвестного числа независимых
запросов. Метод читает iterable по мере освобождения мест, передаёт результаты
обработчикам и возвращает небольшую `ApiSutra\Result\PoolSummary`. `consumeAsync()`
возвращает `ResultPromiseInterface<PoolSummary>` с такой же сводкой. Дождитесь его: fire-and-forget эти методы не дают.

`consume()` ожидает завершения, хотя HTTP внутри может быть конкурентным. При
конкурентности 1 вне async-задачи SDK используется синхронное исполнение, в том числе
с синхронным транспортом. `consumeAsync()` по-прежнему требует
[async-транспорт](transport.md#section-5). Новых блоков конфигурации и зависимостей нет.

## Источник и обработчики <a id="section-2"></a>

Здесь `$client` — клиент вашего SDK, `$ids` — iterable приложения,
`$makeRequest($id)` возвращает `RequestInterface`, а `$save` / `$recordFailure` —
обработчики приложения. Они получают request для сопоставления результата с записью.

```php
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Result\ExecutionResult;

$requests = (static function () use ($ids, $makeRequest): Generator {
    foreach ($ids as $id) {
        yield $makeRequest($id);
    }
})();

$summary = $client->pool($requests)
    ->withResponseHandler(function (ExecutionResult $result, RequestInterface $request) use ($save): void {
        $save($result->data, $request);
    })
    ->withExceptionHandler(function (Throwable $error, RequestInterface $request) use ($recordFailure): void {
        $recordFailure($error, $request);
    })
    ->consume();
```

Обработчики вызываются по готовности. FAILED поступает exception handler, если он
задан, иначе — response handler. SUCCESS/PARTIAL поступают response handler.
Возвращаемые значения, включая `false`, не управляют исполнением. Оба обработчика
необязательны: `$client->pool($requests)->consume()` подходит для запросов, побочные
эффекты которых требуют только итоговой сводки.

Для async передайте новый источник и дождитесь завершения:

```php
$promise = $client->pool($freshRequests, concurrency: 8)
    ->withResponseHandler($onResponse)
    ->consumeAsync();
$summary = $promise->wait();
```

Источник обходится один раз без материализации. Допустимы произвольные ключи;
индексы диагностики — входные позиции от нуля, не ключи. SDK не перезапускает
исчерпанный Generator. Для нового запуска создайте новый источник.

Приоритет конкурентности: последний `withConcurrency()` → явный аргумент создания →
`PoolConfig::concurrency` → 5. `withConcurrency()` требует явное значение и возвращает копию.
Пропущенный/null аргумент создания означает default. То же правило действует у прямого `new PoolExecutor(...)`;
явно переданный PoolConfig приоритетнее конфигурации pool клиента.
Числовой лимит приводится к минимуму 1, как при обычном исполнении pool.

Resolver конкурентности требует массив или `Countable` iterable. Он вызывается один
раз с `(total, 0)` и должен вернуть целое число. Неизвестный размер, неверный тип
результата или исключение resolver дают `ConfigurationException` до обхода и HTTP.
Для генераторов используйте число. Числовая конкурентность не вызывает `count()`;
падение пользовательского `count()` — авария источника с нулевой сводкой.

## Сводка и выдача ошибок <a id="section-3"></a>

`PoolSummary` расширяет неизменяемые счётчики `ResultSummary` данными обхода.
Она не содержит response, request, исключений и коллекции результатов.

| Поле | Значение |
| --- | --- |
| `started` | Элементы, переданные executor, включая cache hit и ошибки до HTTP. Не число HTTP-попыток. |
| `total` | Корректные завершённые ExecutionResult, включая начатые работы, завершённые после аварии. |
| `successful`, `failed`, `partial` | Счётчики результатов; сумма равна `total`. |
| `status` | Непустой набор только SUCCESS → SUCCESS; только FAILED → FAILED; иначе PARTIAL, включая пустой источник. |
| `firstFailedIndex` | Минимальная входная позиция с FAILED независимо от порядка готовности; null, если FAILED нет. |
| `terminationReason` | `ApiSutra\Enums\Execution\PoolTerminationReason`: source_exhausted, stop_on_failure, source_failed, handler_failed, factory_failed или executor_failed. |

`started` может превышать `total`, если executor не вернул корректный результат.
Статусы запросов и авария обхода независимы: источник может упасть после успешного
выполнения всех завершённых запросов. `isAllSuccess()`, `isAllFailed()` и
`isAllPartial()` имеют тот же смысл, что у ResultSummary.

При `throwOnErrors: true` итоговый throw происходит только при общем FAILED.
Exception handler его не подавляет. Один успех среди множества отказов даёт PARTIAL
и обычный возврат: отдельные ошибки отслеживайте через handlers и счётчики, а не
только try/catch. При `throwOnErrors: false` все отказы провайдера возвращают сводку.

При итоговом FAILED [фабрика исключений](../results/exceptions.md) получает
**один настоящий ExecutionResult отказавшего элемента**, выбранный по минимальной
входной позиции. Она не получает агрегат PoolResult, используемый у `send()`.
Фабрика, ветвящаяся по агрегату, может выбрать другой тип исключения. Выбранное
исключение бросается напрямую, без окончательной сводки. `null` от фабрики сохраняет
штатный fallback. Выбор для exception handler и итоговая выдача — отдельные вызовы;
выбор фабрики не кешируется.

## Прерванная обработка <a id="section-4"></a>

При включённом `stopOnFailure` обработка прекращает чтение и запуск после обнаружения
FAILED. Задайте его [для одного пула или в PoolConfig](batch-pool.md#section-4).
Начатые работы завершаются, поступают handlers и учитываются в сводке. Причина —
`stop_on_failure`, даже если элемент оказался последним: SDK не читает дальше,
чтобы это выяснить.

Авария источника, невалидного элемента, handler, фабрики или executor прекращает
новую работу и дальнейшие callbacks. Начатое завершается и учитывается. Затем
`ApiSutra\Exceptions\Execution\PoolConsumptionException` предоставляет `summary`
и причину через `getPrevious()`. Первая авария приоритетнее поздних ошибок.
Авария фабрики сохраняет существующий ExceptionFactoryException и исходную причину
в его previous. `throwOnErrors: false` такие аварии не подавляет.

```php
use ApiSutra\Exceptions\Execution\PoolConsumptionException;

try {
    $summary = $pool->consume();
} catch (PoolConsumptionException $error) {
    $summary = $error->summary;
    $cause = $error->getPrevious();
    // Зафиксируйте прерванную операцию в приложении.
}
```

Для обычного итогового FAILED отдельно ловите свой тип исключения провайдера.
Брошенное самой фабрикой исключение приводит к PoolConsumptionException; возвращённое
ею исключение выдаётся напрямую. Дополнительного HTTP-повтора или рекурсивного
вызова фабрики ни в одном случае нет.

Штатного сигнала «ответов достаточно» из handler в этом API нет. Ограничивайте
источник, если размер можно определить заранее. Исключение из handler — авария:
часть уже выполненных, возможно оплаченных ответов не попадёт в обработчики
приложения. HTTP-эффекты и списанные квоты не откатываются. Без deadline элементов
завершение начатых работ не ограничено фиксированным временем.

Явная отмена Promise и потеря handles следуют [async-контракту](transport.md#section-2):
попытка отмены и очистка без гарантии финальной сводки. SDK не продвигает удерживаемый
Generator ради принудительного finally; ссылками и ресурсами владеет приложение.

## Память и ответственность приложения <a id="section-5"></a>

Координатор хранит окно активных/готовых элементов пропорционально конкурентности,
счётчики и максимум один failed-результат для возможного итогового throw. Обработанные
входы и выходы не собираются. Ограничено число удерживаемых элементов, не байты:
один ответ/composite может быть большим. Массив источника, handlers приложения,
logger, записывающий transport и сохранённые исключения могут накапливать память.
Снятие SDK-ссылок не закрывает файловый stream, сохранённый вашим handler.

Сводка не является checkpoint или числом записей в БД. Приложение учитывает
подтверждённые ID либо непрерывный сохранённый префикс; максимальная завершённая
позиция недостаточна при ответах вне входного порядка. `firstFailedIndex` — только
диагностика. Callbacks могут ожидать другие SDK-вызовы, но блокирующий драйвер БД
или другой синхронный код приложения по-прежнему блокирует поток PHP.

Для полной коллекции используйте [send/sendAsync](batch-pool.md): они подготавливают
весь вход до исполнения. При consume ошибка источника возможна после предыдущих
HTTP-эффектов. Потоковый Batch и pull-обход `stream()` не предоставляются:
остановка и освобождение активной работы потребовали бы отдельного контракта.


Для импорта с ожиданием после HTTP 429 см. [cooldown и бюджет импорта](cooldown.md#imports).
Длинный запрет без общего бюджета может дать серию локальных отказов; pool не ставит их на повтор.
