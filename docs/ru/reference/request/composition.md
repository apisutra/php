<!-- languages --> <a href="../../../en/reference/request/composition.md">English</a> · <a href="composition.md">Русский</a> <!-- /languages -->
# Составные и зависимые запросы <a id="section-1"></a>

Этот гайд про запросы, которые объединяют несколько запросов в одну операцию.
Они выполняются в рамках одного пайплайна и возвращают единый `ExecutionResult`
с вложенными результатами.

## CompositeRequest <a id="section-2"></a>
**Задача:** выполнить набор независимых запросов и агрегировать результат.

Контракт:
- `requests(): RequestCollection`
- `aggregate(ResultCollection $results, PipelineContext $ctx): mixed`

### Пример <a id="section-3"></a>
```php
use ApiSutra\Attributes\Behavior\Execution;
use ApiSutra\Collections\RequestCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Execution\ExecutionMode;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\VO\Pipeline\PipelineContext;

#[Execution(mode: ExecutionMode::Parallel, failStrategy: FailStrategy::Partial)]
final class UserProfileComposite extends AbstractRequest implements CompositeRequestInterface
{
    public function __construct(
        public string $userId,
    ) {}

    public function requests(): RequestCollection
    {
        return RequestCollection::make([
            new GetUser($this->userId),
            new GetUserOrders($this->userId),
        ]);
    }

    public function aggregate(ResultCollection $results, PipelineContext $ctx): mixed
    {
        return [
            'user' => $results->get(GetUser::class)?->data,
            'orders' => $results->get(GetUserOrders::class)?->data,
        ];
    }
}
```

### Поведение <a id="section-4"></a>
- `ExecutionMode` задаёт **sequential** или **parallel** для дочерних запросов.
- `FailStrategy::FailAll` — при любой ошибке composite возвращает FAILED.
- `FailStrategy::Partial/IgnoreErrors` — composite продолжает и возвращает PARTIAL/ SUCCESS.
- Результаты дочерних запросов доступны в `ExecutionResult::$nested`,
  мета‑информация — в `ExecutionResult::$meta` (BatchMeta).

## DependsOnRequest <a id="section-5"></a>
**Задача:** выполнить зависимости, затем основной запрос.

Контракт:
- `dependencies(): RequestCollection`
- `processDependencies(ResultCollection $results, PipelineContext $ctx): void`

### Пример <a id="section-6"></a>
```php
use ApiSutra\Contracts\Interfaces\Core\DependsOnRequestInterface;

final class CreateOrder extends AbstractRequest implements DependsOnRequestInterface
{
    public function __construct(
        public string $userId,
        public ?string $token = null,
    ) {}

    public function dependencies(): RequestCollection
    {
        return RequestCollection::make([
            new FetchUserToken($this->userId),
        ]);
    }

    public function processDependencies(ResultCollection $results, PipelineContext $ctx): void
    {
        $this->token = (string) ($results->get(FetchUserToken::class)?->data ?? '');
    }
}
```

### Поведение <a id="section-7"></a>
- `DependsOn` **всегда выполняется sequential**, даже если задан Parallel.
- `FailStrategy::FailAll` завершит выполнение, если зависимость упала.
- После `processDependencies()` основной запрос отправляется как обычный.

## Роль RequestRole <a id="section-8"></a>
Внутри пайплайна роль запроса отражает контекст выполнения:
- `Root` — основной запрос
- `Nested` — дочерний запрос composite
- `Dependency` — запрос зависимости

Роль используется в audit‑логах, debug‑данных и хуках.

## Замена подготовленного HTTP-тела <a id="section-9"></a>

В hook до первой отправки присваивайте контексту новую копию:
`$context->preparedRequest = $context->preparedRequest->withBody($text)` либо
`->withStream($stream)`. Для удаления используйте `->withoutBody()`;
`with(body: null)` и `with(stream: null)` сохраняют прежнее значение.
При смене формата явно обновляйте Content-Type. Полный контракт, пример hook — в [руководстве транспорта](../execution/transport.md#section-3).

При включённом debug итоговый результат отражает фактически отправленный запрос
полученного ответа, включая последнюю retry-попытку. При сбое без ответа используется
актуальный prepared request контекста. Изменение контекста в `AfterResponse` не
подменяет отправленное тело в debug; redaction сохраняется. Замена тела после
первой попытки останавливает повтор с `body_changed`.

## Диагностика составного запроса <a id="section-10"></a>

Composite имеет собственные start/terminal и `nested` с дочерними результатами.
У DependsOn зависимости также сохраняются в `nested`; основной HTTP продолжает
тот же запуск после `processDependencies()`. Повторного started основного запроса
нет. Дети одного класса различаются по `trace.executionId` и связаны с родителем
через `trace.parentExecutionId`. [Общий контракт](../results/observability.md#section-5).
