<!-- languages --> <a href="../../../en/reference/results/promises.md">English</a> · <a href="promises.md">Русский</a> <!-- /languages -->
# Типизированные промисы <a id="section-1"></a>

`ApiSutra\Contracts\Interfaces\Execution\ResultPromiseInterface<T>` совместим
с Guzzle PromiseInterface. T — PHPDoc-тип значения разрешённого промиса: ResultHandle для
одиночной отправки, PoolResult/BatchResult для групп, PoolSummary для consumeAsync.
`wait()` возвращает T, `wait(false)` — null. Отклонённый промис бросает при обычном
wait; wait(false) дожидается завершения без выдачи значения или исключения отказа.

## Значения и композиция <a id="values"></a>

| Sync-операция | Async-операция | Значение после wait |
| --- | --- | --- |
| `send()` | `sendAsync()` | `ResultHandle` |
| `sendInContext()` | `sendInContextAsync()` | `ResultHandle` с родительским исполнением |
| `request->resolved()` | `request->resolvedAsync()` | `ResolvedResultInterface` |
| `batch->send()` | `batch->sendAsync()` | `BatchResult` |
| `pool->send()` | `pool->sendAsync()` | `PoolResult` |
| `pool->consume()` | `pool->consumeAsync()` | `PoolSummary` |

Независимые вызовы запускайте до ожидания. Каждый новый send выполняет запрос заново;
повторный wait и чтение готового handle используют тот же результат. Промис — не
ResultHandle: перед `dataOrFail()`, `raw()` или `resolved()` вызовите `wait()`.

Для настроенного `$client` и запросов `$request` / `$requests`:

```php
use ApiSutra\Result\ResultHandle;
use ApiSutra\Result\PoolSummary;

$promise = $client->sendAsync($request);
$handle = $promise->wait(); // ResultHandle.
$summaryPromise = $promise->then(
    fn (ResultHandle $handle) => $client->pool($requests)->consumeAsync(),
);
$total = $summaryPromise->then(static fn (PoolSummary $summary): int => $summary->total)->wait();
```

then/otherwise сохраняют тип обычного значения и разворачивают тип нашего промиса,
возвращённого обработчиком. otherwise добавляет тип восстановления к исходному T.
Нетипизированный сторонний Guzzle Promise даёт mixed. PHPStan также может вывести
mixed для callback, возвращающего расширяемый класс вроде ExecutionResult: его
потомок может реализовать PromiseInterface. Final-типы ResultHandle/PoolSummary точны. Utils::all корректно ждёт
наши промисы; точные типы элементов его результата дополнительно не описываются.

Проверено PHPStan 2.2.13 на уровне 8. Это проверенная версия, а не установленный
минимум; подсказки PhpStorm не проверены. Потребителю не требуется подключать
stub-файлы пакета. T проверяется анализатором, не PHP во время исполнения.
Тип ResultHandle не выводит конкретный DTO из dataOrFail(): этот метод сохраняет
mixed, а создание DTO и проверка Returns выполняются при исполнении.

## Ошибочный результат и отклонённый промис <a id="errors"></a>

Ошибки SDK при разрешении клиента, запуске и выдаче async переходят в reject.
FAILED при throwOnErrors=false остаётся готовым ошибочным результатом; при true
промис отклоняется выбранным исключением. Ошибки типов аргументов PHP до входа
в метод не превращаются в reject.

`otherwise()` обрабатывает **reject**, а не готовый handle со статусом FAILED.
При стандартном `throwOnErrors: false` можно проверить handle либо превратить
ошибку в reject, вызвав `dataOrFail()` внутри `then()`:

```php
use ApiSutra\Result\ResultHandle;

// $client настроен, $request — запрос SDK, $recordFailure — callback приложения.
$data = $client->sendAsync($request)
    ->then(static fn (ResultHandle $handle) => $handle->dataOrFail())
    ->otherwise(static function (Throwable $error) use ($recordFailure): null {
        $recordFailure($error); // Применить политику ошибок; null — явный fallback.
        return null;
    })
    ->wait();
```

При `throwOnErrors: true` FAILED уже отклоняет исходный промис. `wait(false)` не
превращает отказ в успех: последующий `wait()` всё ещё бросит это исключение.
PARTIAL проверяйте по статусу и счётчикам; dataOrFail не бросает при PARTIAL.
`Utils::all()` отклоняется при reject входящего промиса; это не означает отмену всех
остальных вызовов. Сохраняйте их промисы и явно дождитесь либо отмените их.
У отмены batch/pool SDK есть [собственный контракт](../execution/transport.md#section-2).

## Владение и отмена <a id="ownership"></a>

У промиса доступны resolve/reject из контракта Guzzle: это ручное завершение
значением/отказом. SDK управляет завершением своих операций; приложение использует
wait/then/otherwise/cancel. Ручное resolve/reject вмешивается в результат и не
является управлением HTTP; reject не заменяет cancel. Готовый ResultHandle
не содержит этих методов и не управляет незавершённой отправкой.

Продолжение провайдера начинается после выдачи: `sendAsync($request)->wait()->await()`.
Отмена уже разрешённого промиса не отменяет последующий polling провайдера.
Кооперативность такого ожидания зависит от контекста вызова await.

[Полный локальный пример](../../../example/async-results/run.php) запускается командой
`php docs/example/async-results/run.php`. Он сочетает промисы одиночного вызова,
pool/consume, разворачивает вложенный промис и различает FAILED и reject.
MockTransport показывает семантику API, а не сетевую конкурентность или быстродействие.
