<!-- languages --> <a href="../../en/development/execution.md">English</a> · <a href="execution.md">Русский</a> <!-- /languages -->
# Выполнение запросов <a id="section-1"></a>

Этот документ описывает выбор исполнителя и связи между отдельными отправками.
Внутренние стадии одной отправки находятся в [пайплайне](pipeline.md), общая карта —
в [архитектуре](architecture.md).

## Вход и runtime-опции <a id="section-2"></a>

[AbstractRequest](../../../src/Core/AbstractRequest.php) хранит декларацию.
[RequestExecution](../../../src/Request/RequestExecution.php) передаёт неизменяемый снимок
RequestOptions и PaginationOptions; до исполнителя доходит вся обёртка.

[AbstractClient](../../../src/Core/AbstractClient.php) — публичный фасад выдачи. Он
вызывает `execution()`, затем применяет throwOnErrors и создаёт ResultHandle с исходным
запросом и клиентом. [ClientExecutor](../../../src/Execution/ClientExecutor.php) создаёт
локальный ExecutionEnvelope и контекст, открывает область, вычисляет результат,
извлекает недостающую meta, локализует, проверяет конечный бюджет и завершает ровно один раз.
Публичный выбор исключения находится вне catch вычисления.

## Выбор потока <a id="section-3"></a>

| Сценарий | Координатор | Следующий шаг |
| --- | --- | --- |
| Одиночный запрос | ClientExecutor | Pipeline.run(открытый контекст) |
| Пагинация клиента | ClientExecutor → PaginationTraversal | Одиночные исполнения страниц |
| Прямой сбор / iterator | Paginator → ClientExecutor / PaginationTraversal | Канонический бюджет / ленивая область |
| Composite / DependsOn | PipelineCompositeHandler → CompositeFlow | Канонические дети, агрегация / основные стадии |
| Batch | BatchExecutor → BatchContext | Sequential/parallel канонические дети |
| Pool | PoolExecutor → ConcurrentExecution | Ограниченная очередь и отдельные callbacks |
| Continuation | ContinuationService | Решение о готовности, Single poll, конечная гидратация |

Все дочерние запросы идут через ClientExecutorInterface. Контекст хранит внешний
`executor` dispatcher для конкретного вызова, включая вложенный auth. Выбор пути не
зависит от класса клиента, фабрики исключений, override публичного send или восстановления
результата по Throwable.

## Одиночное выполнение и пагинация <a id="section-4"></a>

ClientExecutor создаёт бюджет до выбора Single или пагинации. Оба собирающих входа
используют его; Paginator только доставляет итоговый агрегат. В Pagination/Traversal
PaginationProgress владеет границами/guards; ConcurrentPageLoader после async-bootstrap
подаёт страницы в существующий ConcurrentExecution; PaginationResultAccumulator сортирует
страницы и собирает items одним проходом. PaginationFailureFactory добавляет ошибкам обхода
и guards его trace и политики клиента; классификацией владеет ExecutionErrorFactory,
публичная выдача остаётся за пределами обхода. Страницы задают Single и получают настоящий
parent context, наследуя бюджет и trace. ResponseHydrator преобразует одну страницу.
Ленивый iterator открывает свою область, сохраняет отдельный клиентский бюджет на страницу
и не собирает результаты. [Контракт](../reference/execution/pagination.md).

## Composite и зависимости <a id="section-5"></a>

[PipelineCompositeHandler](../../../src/Pipeline/Flow/PipelineCompositeHandler.php)
выбирает ветку после валидации, до подготовки основного HTTP-запроса.
[CompositeFlow](../../../src/Pipeline/Execution/CompositeFlow.php) связывает её с batch-исполнителями:

- `CompositeExecutor` отправляет `requests()` с ролью `Nested`. Затем
  `aggregate()` собирает значение из `ResultCollection`; при объявленном типе
  ответа оно гидратируется гидратором клиента. Результат содержит дочерние исполнения
  в `nested`. Отдельной HTTP-отправки агрегирующего запроса нет.
- `DependsOnExecutor` отправляет `dependencies()` с ролью `Dependency`.
  `processDependencies()` применяет полученные данные. Затем `CompositeFlow`
  возвращает управление в текущий Pipeline для основной отправки. Контекст,
  бюджет и executionId сохраняются; зависимости доступны в nested.

Повторного запуска основной операции и повторной валидации нет. `FailStrategy` определяет, можно ли продолжить после ошибок детей;
публичный контракт — в [композиции запросов](../reference/request/composition.md).

## Batch и pool <a id="section-6"></a>

BatchExecutor нормализует экземпляры, callable и имена классов; обе стратегии используют
канонический порт BatchContext. FailStrategy останавливает новые работы или продолжает
независимо от публичного throwOnErrors. PoolExecutor выбирает callbacks по статусу ребёнка.

ConcurrentExecution завершает выданные Promise до возврата или передачи сбоя callback/
стороннего порта. После FailAll/stopOnFailure новые работы прекращаются; сбой callback
также отключает последующие callbacks. Результаты восстанавливаются в порядке входа
до выбора причины агрегата. Публичный `send()` выдаёт окончательный агрегат вне вычисления.
[Контракт batch/pool](../reference/execution/batch-pool.md).

PoolRequestSource отвечает за позиционную проверку и подготовку. Eager-путь send материализует источник; consume продвигает его только при свободном месте. ConcurrentExecution.run хранит окно активных/готовых элементов и возвращает внутренний ExecutionOutcome. Учёт корректных результатов продолжается при завершении начатого после аварии; пользовательская доставка прекращается. collect подставляет накопитель массива, PoolConsumptionState — счётчики и максимум один failed-результат. PoolSummary расширяет ResultSummary данными обхода. Синхронный consume с одним местом остаётся вне AsyncRuntime. [Контракт обработки](../reference/execution/pool-consumption.md).

## Promise API и режим провайдера <a id="section-7"></a>

ClientExecutor.executeAsync выполняет канонический пайплайн в Fiber, fulfilled даже
при FAILED. Публичный sendAsync применяет throwOnErrors после него. Внутренний
SchedulerInterface изолирует Revolt; GuzzlePromiseBridge продвигает очередь Promise
и ожидает через suspension. GuzzleAsyncDriver опрашивает активные передачи cURL с
select_timeout 0 и снимает таймер в простое. SDK не запускает и не заменяет цикл приложения.

Конкретный планировщик по умолчанию выбран в конструкторах AsyncRuntime и
GuzzlePromiseBridge. Замена backend требует изменить эти внутренние defaults и
проверить контракт планировщика; клиентский API не предлагает выбор планировщика.
Передача планировщика одному внутреннему компоненту не перенастраивает весь SDK.
Продвижение очереди Promise объединяется в пределах моста и только при наличии работы.

Контекст request хранится стеком по Fiber; состояние обхода DTO также изолировано.
Правила retry/auth/budget остаются у пайплайна. ContinuationMode описывает готовность
провайдера независимо от конкурентности транспорта. [Контракт](../reference/execution/transport.md).

## Ожидание continuation <a id="section-8"></a>

[ContinuationService](../../../src/Continuation/ContinuationService.php) получает клиент
и его гидратор. `ResultHandle::await()` передаёт сервису стартовый `ExecutionResult`,
исходный запрос и опции ожидания; также возможен вход по уже известному token.

Сервис строит `ContinuationContext` и выбирает resolver готовности. По режиму он
оценивает стартовый результат или сразу переходит к polling. Resolver возвращает
Pending, Ready или Failed; успешное создание DTO не используется как проверка готовности.
Poll-запросы отправляются через клиент и проходят обычный пайплайн.

После Ready сервис гидратирует выбранный payload, если задан финальный тип.
`ContinuationOutcome` сохраняет payload до гидратации, значение, последний результат
и число оценённых ответов, trace и audit ожидания. Poll получает дочерний
executionId в trace старта, без наследования его завершённого HTTP-бюджета. Handle сохраняет outcome: повторный `await()` возвращает
значение, а `awaitAs()` с другим типом использует тот же payload без нового polling.

Ошибка финальной гидратации завершает ожидание; она не превращается в Pending.
При изменении этой ветки сверяйте [критерий готовности](../reference/execution/continuation-state.md)
и [доставку ошибок и лимиты ожидания](../reference/execution/continuation-await.md).

## Контекст дочернего выполнения <a id="section-9"></a>

`parent` передаёт зависимому запросу контекст, trace и бюджет. Отдельный `parentTrace`
даёт корреляцию без общего HTTP-deadline. Противоречивые parent/parentTrace — ошибка
конфигурации. Страницы и poll наследуют только trace, auth — остаток бюджета родителя.
Логические области клиента получают те же часы и logger через
ClientExecutorInterface.createScope, включая декораторы клиента.

`sendInContext()` остаётся публичным фасадом, внутренние потребители используют исполнитель
и явный RequestRole. [Контракт порта](../reference/extensions/execution.md) определяет
наследование dispatcher, [бюджеты](../reference/execution/deadlines.md) — время.

## Где проверять изменения <a id="section-10"></a>

| Связь механизмов | Сценарии |
| --- | --- |
| Обёртки и выбор пагинации | [RequestExecutionChainTest](../../../tests/Unit/Request/RequestExecutionChainTest.php), [RequestResolverTest](../../../tests/Unit/Request/RequestResolverTest.php) |
| Агрегация и зависимости | [CompositeFlowTest](../../../tests/Unit/Execution/CompositeFlowTest.php), [ClientExecutionEntryTest](../../../tests/Unit/Pipeline/ClientExecutionEntryTest.php) |
| Массовое выполнение | [BatchExecutorTest](../../../tests/Unit/Execution/BatchExecutorTest.php), [PoolExecutorTest](../../../tests/Unit/Execution/PoolExecutorTest.php) |
| Готовность и повторный await | [ContinuationReadinessTest](../../../tests/Unit/Result/ContinuationReadinessTest.php), [ResultHandleContinuationAwaitTest](../../../tests/Unit/Result/ResultHandleContinuationAwaitTest.php) |
| Общий бюджет | [ExternalDeadlineTest](../../../tests/Unit/Timing/ExternalDeadlineTest.php) |

Канонические границы и отложенные Promise: [CanonicalExecutionTest](../../../tests/Unit/Execution/CanonicalExecutionTest.php), [ExecutionLifecycleTest](../../../tests/Unit/Execution/ExecutionLifecycleTest.php).

Публичный async возвращает `ResultPromiseInterface<T>`. AwaitablePromise сохраняет
механику ожидания и владение AsyncTask; PHPDoc в публичном интерфейсе описывает
разворачивание типов. AbstractClient создаёт готовый ResultHandle после исполнения
и локализует отказ выдачи; ResultHandle не хранит Promise. RequestExecution и request
превращают ошибки разрешения клиента в reject. Внутренний executor сохраняет
ExecutionResult и Guzzle Promise; [публичный контракт](../reference/results/promises.md).
