<!-- languages --> <a href="../../../en/reference/execution/continuation-state.md">English</a> · <a href="continuation-state.md">Русский</a> <!-- /languages -->
# Определение готовности операции <a id="section-1"></a>

## Базовые элементы <a id="section-2"></a>

### Контракт финального результата <a id="section-3"></a>

Используйте class-level атрибут `#[ContinuationResult]`:

```php
use ApiSutra\Attributes\Response\ContinuationResult;
use ApiSutra\Attributes\Response\Returns;

#[Returns(StartEnvelopeDto::class)]
#[ContinuationResult(
    finalType: FinalBusinessDto::class,
    unwrap: 'data',
    pollRequest: GetAsyncResultRequest::class,
)]
final class CheckRequest extends BaseRequest
{
    // ...
}
```

- `finalType` — обязательный финальный DTO для `await()`
- `unwrap` — путь к финальным данным внутри JSON-объекта poll/start ответа и критерий их присутствия
- `pollRequest` — optional poll-request; если не указан, берётся `ClientConfig::defaultPollRequest`
- `stateResolver` — класс `ContinuationStateResolverInterface`, создаваемый без аргументов
- `defaultMode` — режим запроса; runtime override имеет приоритет, далее используется режим клиента

Готовность задаётся явно. В порядке приоритета выбирается `stateResolver` атрибута,
встроенный `FinalPathStateResolver` при непустом `unwrap`, затем
`ClientConfig::continuationStateResolver`. Если ничего не задано, ожидание завершается
`ContinuationConfigurationException` до первого poll-запроса.

Встроенный resolver читает `$result->response?->json()`. Значение по `unwrap`,
отличное от null, означает Ready; отсутствие пути, null, отсутствие ответа или
тело, которое не является JSON-объектом, означают Pending. Значения `false`, `0`
и `[]` присутствуют и считаются Ready. Корень ответа не подставляется вместо пути.
Гидратация выполняется только после Ready: возможность создать DTO с defaults
не является признаком готовности.

`finalType` также автоматически попадает в `$client->responseDtoCatalog()` как
запись с `kind = ResponseDtoKind::AsyncFinal`, рядом со start-DTO из `Returns(...)`
(`kind = Sync`). См. [Operation Inventory → ResponseDtoCatalog](../client/response-dto-catalog.md#section-9).

### Режим provider-выполнения <a id="section-4"></a>

Используйте `ContinuationMode`:
- `Auto` — оценить старт: Ready возвращает финал, Pending начинает polling по token
- `Sync` — оценить старт: Ready возвращает финал, Pending даёт `final_not_ready`
- `Async` — не оценивать готовность старта, сразу начать polling по token

Failed прекращает ожидание в любом оцениваемом ответе, даже при наличии token.

Runtime sugar на запросе:
- `asProviderSync()`
- `asProviderAsync()`
- `asProviderAuto()`
- `withContinuationMode(...)`

### Маппинг режима в provider-протокол <a id="section-5"></a>

Ядро не хардкодит `async`/`mode`/`header` поля провайдера.
Это делает `ContinuationModeApplicatorInterface`:

```php
use ApiSutra\Contracts\Interfaces\Continuation\ContinuationModeApplicatorInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Enums\Continuation\ContinuationMode;
use ApiSutra\Serialization\VO\RequestPartsBag;
use ApiSutra\VO\Pipeline\PipelineContext;

final class ProviderContinuationModeApplicator implements ContinuationModeApplicatorInterface
{
    public function apply(RequestInterface $request, RequestPartsBag $parts, ContinuationMode $mode, ?PipelineContext $context = null): RequestPartsBag
    {
        $parts->query['async'] = [
            'value' => $mode === ContinuationMode::Async,
            'format' => null,
        ];

        return $parts;
    }
}
```

## Собственный критерий готовности <a id="section-6"></a>

Для протокола со статусом операции реализуйте resolver в SDK. Он получает один
`ContinuationContext` на всё ожидание: `finalType` (null для нетипизированного
финала), `unwrap`, `sourceRequestClass` и `mode`. Resolver не выполняет HTTP
и не гидратирует DTO. Его исключения выходят без обёртки.

Полный [OperationStateResolver](../../../example/continuation/src/OperationStateResolver.php)
сопоставляет status: `done` → Ready(data, 'data'), `failed` → Failed, остальные → Pending.
[Исполняемый пример](../../examples/continuation.md) проверяет старт, два poll
и повторное получение кешированного финала.

Укажите `stateResolver: OperationStateResolver::class` в `ContinuationResult`
или `continuationStateResolver: new OperationStateResolver()` в конфигурации клиента.
`ClientConfig::with()` переносит экземпляр; явный null снимает настройку.
Без `ContinuationResult` вызов `await()` с resolver клиента возвращает Ready-payload
без преобразования, в том числе null. `awaitAs()` задаёт тип явно.

## Инварианты и ошибки конфигурации <a id="section-7"></a>

- poll-request должен иметь **ровно один обязательный scalar-параметр** конструктора (token)
- если `pollRequest` не задан в атрибуте, должен быть `defaultPollRequest` в `ClientConfig`
- если token extractor не настроен, `await()`/`awaitByToken()` не смогут продолжить async-сценарий
- failed poll/start ответ продолжает polling только если resolver вернул Pending и есть token
- ошибки continuation-конфига бросаются как `ContinuationConfigurationException`

Resolver получает канонические результаты poll и при throwOnErrors true. Ошибка HTTP
сама по себе не обходит оценку готовности: Pending с token может продолжить ожидание.
Failed или Pending без token выдаёт ошибку failed-результата только после завершения области await.
