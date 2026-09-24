<!-- languages --> <a href="../../../en/reference/results/exceptions.md">English</a> · <a href="exceptions.md">Русский</a> <!-- /languages -->
# Тип результата и собственные исключения <a id="section-1"></a>

`#[Returns(Dto::class)]` автоматически проверяет тип конечного успешного значения.
Дополнительная конфигурация не нужна: после обработки ответа и стадий преобразования
результат должен быть экземпляром объявленного DTO или его подкласса. Это позволяет
методу SDK возвращать `send($request)->dataOrFail()` без повторного `instanceof`.
Гарантия действует во время выполнения; IDE не выводит PHP-тип из атрибута.

## Сообщение нарушения типа <a id="section-2"></a>

Фрагмент запроса; `AccountInfo` — DTO вашего SDK:

```php
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use Example\ResultErrors\AccountInfo;

#[Get('/account')]
#[Returns(AccountInfo::class, mismatchMessage: 'Получен неожиданный результат чтения аккаунта.')]
final class GetAccountRequest extends AbstractRequest
{
}
```

Сообщение выбирается по приоритету:

1. `Returns::mismatchMessage` конкретного запроса.
2. `ClientConfig::resultExceptions->mismatchMessage`.
3. Сообщение ApiSutra с классом запроса, ожидаемым и фактическим типом.

Оба override необязательны; `null` означает переход к следующему уровню. Пустая
или состоящая из пробелов строка даёт `configuration_error` при выполнении до HTTP.
Каталог операций не выполняет эту проверку. Значения данных в стандартное сообщение
не включаются. Override — буквальная строка без шаблонизации; не помещайте в неё секреты.
Встроенные сообщения используют [локализацию клиента](../client/localization.md);
буквальные override и исключения, возвращённые вашей фабрикой, сохраняют свой текст.

Нарушение даёт `FAILED`, `hydration_error` и
`ResponseTypeMismatchException` (подкласс `HydrationException` из
`Exceptions\Serialization`). В исключении и контексте первой ошибки доступны
`reason=response_type_mismatch`, `path=$`, `expected`, `actual`. HTTP-ответ сохраняется;
ошибочный результат не записывается в кеш как успешный.

Сообщение относится только к **несовпадению итогового типа**. HTTP, transport,
JSON decoding и ошибки полей DTO сохраняют свои причины и тексты.

## Одна фабрика для клиента <a id="section-3"></a>

Для собственных классов исключений подключите необязательный
`ApiSutra\Contracts\Interfaces\Errors\ExecutionExceptionFactoryInterface`:

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\ResultExceptionConfig;
use Example\ResultErrors\ProviderExceptionFactory;

$config = new ClientConfig(
    baseUrl: 'https://api.example.test',
    resultExceptions: new ResultExceptionConfig(
        mismatchMessage: 'Провайдер вернул результат неожиданного типа.',
        exceptionFactory: new ProviderExceptionFactory(),
    ),
);
```

Сигнатура контракта: `make(ExecutionResult $result, string $message): ?Throwable`.
Фабрика получает полный законченный результат: response, errors, exception,
meta, nested, traceId и trace. Она может обработать любую конечную ошибку, а для остальных
вернуть `null`. Тогда выбрасывается исходное исключение; если его нет — `SdkException`.
Возвращайте собственный Throwable с переданным сообщением. Для причинной цепочки
можно передать `$result->exception` в `previous` собственного исключения.

Без `resultExceptions`, с пустым `new ResultExceptionConfig()` или без
`exceptionFactory` используются штатные исключения. Общий текст можно настроить
без фабрики, фабрику — без общего текста. `with(resultExceptions: null)` удаляет
блок в копии; обычный `with()` сохраняет его. Необязательная Laravel `ClientConfigFactory::make()` принимает блок в overrides.
Объекты создавайте в provider/factory приложения, не в кешируемом Laravel config.
Новые зависимости и контейнер не требуются.

`resultExceptions` независим от [HydrationConfig](../dto/configuration.md):
гидратация создаёт DTO по атрибутам и внешним правилам, затем Returns проверяет
конечный класс. Ошибки RequiredInput, ForbidExplicitNull, Shape и ConstructorValue
сохраняют исходные reason, пути и HTTP-ответ; фабрика получает их без подмены
ошибкой итогового типа. Атрибуты DTO работают и без обоих конфигурационных блоков.

При [pool consume](../execution/pool-consumption.md#section-3) итоговый FAILED передаёт фабрике один настоящий failed-результат элемента, не агрегат PoolResult. Фабрика, ветвящаяся по агрегату, может выбрать другой тип. Выбранное исключение выдаётся напрямую; аварии источника/handler/фабрики/executor дают PoolConsumptionException со сводкой и цепочкой причин.

## Когда вызывается фабрика <a id="section-4"></a>

Фабрика вызывается при выдаче исключения из `FAILED`: явные
`ResultHandle::dataOrFail()` / `ExecutionResult::throw()`, публичная выдача send/агрегата
с `throwOnErrors: true`, выдача окончательного failed-результата из await и error callback
pool. SUCCESS/PARTIAL фабрику не вызывают. Чтение raw/resolved не вызывает её, если
публичный send вернул результат нормально.

Каждый внутренний запрос идёт через [исполнитель](../extensions/execution.md), который
возвращает канонический результат независимо от throwOnErrors и наличия фабрики.
Auth, retry, FailStrategy и resolver готовности принимают решения по этому результату.
Переопределения публичных `send()`/`sendAsync()` работают только для прямых вызовов
пользователя; общую логику размещайте в хуках или декораторе исполнителя.

| Выдача | Число вызовов фабрики |
| --- | --- |
| Внутренний failed-запрос; чтение raw/resolved | 0 |
| Публичный FAILED с throwOnErrors true | 1, с полным окончательным агрегатом, если он есть |
| Error callback pool для FAILED | 1 для ребёнка; автоматическая выдача агрегата отдельная |
| Два явных вызова `throw()` | 2; результат фабрики не кешируется |

Собственная ошибка агрегата приоритетна. Иначе его exception — исключение первого
FAILED ребёнка **в порядке входа**, независимо от порядка завершения. Если у этого
ребёнка нет exception, сообщение по умолчанию берётся из его первой ошибки;
исключение следующего ребёнка не заимствуется. Фабрика всегда получает весь агрегат
с каноническими детьми. Продолжение Partial/IgnoreErrors не зависит от публичного throwOnErrors.

Фабрика должна только выбирать/создавать исключение, без HTTP или рекурсивного `throw()`.
Если она бросает исключение (включая TypeError), выдача даёт `ExceptionFactoryException`
из `Exceptions\Configuration` с `reason=exception_factory_failed`, исходным результатом
в `result` и сбоем фабрики в `previous`. Повторных mapping, retry и вызова фабрики нет.
Сбой callback pool прекращает новые запуски и callbacks, завершает выданные Promise
и выходит без автоматического throw агрегата. В собственных логах применяйте
[редакцию](observability.md); raw-тела фабрики автоматически не логируются.

## Границы проверки Returns <a id="section-5"></a>

- [Проверка декларации](../attributes/response.md#declaration-validation) предшествует HTTP:
  классы DTO и гидратора проверяются даже при обходе гидратации. DI остаётся отложенным.
- Ненулевой результат response handler проверяется без повторной гидратации.
  Возвращённый handler `null` включает стандартный разбор ответа.
- Проверяется фактически возвращаемое значение после стадий, включая EarlyReturn
  и успешный composite. Уже полученный `FAILED` не заменяется ошибкой типа.
- При unwrap действует `Returns::type`, если он задан; иначе `response`.
  У composite сохраняется его отдельный контракт сборки.
- У пагинации проверяются DTO страниц. Итоговый массив страниц/элементов не
  проверяется как DTO одной страницы. У continuation финал имеет свой контракт.
- `Download` сохраняет приоритет для итогового значения, но не отменяет проверку декларации. RawResponse с DTO несовместим. Проверка не добавляет чтение потоков или буферизацию файлов.
- Без активного DTO сохраняются null/скаляры/массивы. Пустой ответ или JSON null
  для DTO без unwrap может гидратироваться через defaults; строгая
  проверка JSON-object этим механизмом не вводится.

[Полный исполняемый пример](../../examples/result-errors.md) показывает два
запроса, одну фабрику и fallback.
