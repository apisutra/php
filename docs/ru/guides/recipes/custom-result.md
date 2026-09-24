<!-- languages --> <a href="../../../en/guides/recipes/custom-result.md">English</a> · <a href="custom-result.md">Русский</a> <!-- /languages -->
# Собственное представление результата <a id="section-1"></a>

Подключите свою фабрику, чтобы `resolved()` возвращал объект с методами вашего SDK.
В примере `SdkResult::recordOrFail()` отдаёт конкретный `RecordDto`, а
`requiresReauthorization()` сообщает об HTTP 401. Данные создаются
по `#[Returns]`; общий контракт — [ResolvedResultInterface](../../reference/results/handles.md#section-6).

## Запустить пример <a id="section-2"></a>

```bash
php docs/example/custom-result/run.php
```

В установленном пакете добавьте `vendor/apisutra/php/` перед путём.
[Полный пример](../../examples/custom-result.md) использует готовый учебный
SDK и MockTransport: успех, HTTP 401 и сохранение записи проверяются без сети.

## 1. Добавить методы SDK <a id="section-3"></a>

Стандартный `ResolvedResult` объявлен `final`. Поэтому
[SdkResult](../../../example/custom-result/src/SdkResult.php) реализует
`ResolvedResultInterface` и хранит готовое представление в `$inner`.
Все методы интерфейса явно делегируются ему: статусы, данные, ошибки, token
и доступ к исходному `ExecutionResult`. Полный класс находится по ссылке;
ниже — только два дополнительных метода:

```php
use Example\ClientShowcase\Resources\Records\RecordDto;
use UnexpectedValueException;

    public function recordOrFail(): RecordDto
    {
        $this->inner->result()->throw();
        $data = $this->inner->data();
        if (!$data instanceof RecordDto) {
            throw new UnexpectedValueException('Эта операция не вернула RecordDto');
        }

        return $data;
    }

    public function requiresReauthorization(): bool
    {
        return $this->inner->errorStatus() === 401;
    }
```

При `FAILED` первый метод сохраняет стандартное исключение. При успешной операции
с другим типом данных он выдаёт `UnexpectedValueException`; обычный `data()` остаётся
универсальным. `PARTIAL` не превращается в успех: `isPartial()` и ошибки сохраняются,
а `recordOrFail()` может вернуть DTO, если он есть. Признак HTTP 401 сам не запускает авторизацию.

## 2. Создать фабрику <a id="section-4"></a>

[SdkResultFactory](../../../example/custom-result/src/SdkResultFactory.php) получает
стандартную фабрику и оборачивает её результат:

```php
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResolvedResultFactoryInterface;
use Override;

final readonly class SdkResultFactory implements ResolvedResultFactoryInterface
{
    public function __construct(private ResolvedResultFactoryInterface $defaults)
    {
    }

    #[Override]
    public function make(ExecutionResult $result): SdkResult
    {
        return new SdkResult($this->defaults->make($result));
    }
}
```

Оба класса находятся в namespace `Example\CustomResult`. Конкретный возвращаемый
тип `SdkResult` совместим с интерфейсом фабрики. Обёртка сохраняет исходный результат,
не гидратирует данные повторно и не отправляет HTTP-запрос.

## 3. Подключить к клиенту <a id="section-5"></a>

Фрагмент [run.php](../../../example/custom-result/run.php), где `$transport` уже настроен
на локальные ответы. `DemoClient` взят из [обзора клиента](../client/showcase.md),
`TokenExtractor` — из [примера ожидания](../../../example/continuation/src/TokenExtractor.php).
Extractor читает вымышленное поле `operationToken`; polling здесь не нужен.

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Result\ResolvedResultFactory;
use Example\ClientShowcase\DemoClient;
use Example\Continuation\TokenExtractor;
use Example\CustomResult\SdkResultFactory;

$base = new ClientConfig(
    baseUrl: 'https://api.example.test',
    authRetryOn401: false,
    continuationTokenExtractor: new TokenExtractor(),
);

// Собственная фабрика явно получает настройки стандартного представления.
$defaults = new ResolvedResultFactory(
    mapper: $base->errorMapper,
    errorContextFactory: $base->errorContextFactory,
    continuationTokenExtractor: $base->continuationTokenExtractor,
);
$client = new DemoClient(
    $base->with(resolvedResultFactory: new SdkResultFactory($defaults)),
    $transport,
);
```

При собственной `resolvedResultFactory` клиент использует её напрямую.
Параметры `errorMapper`, `errorContextFactory` и `continuationTokenExtractor`
не внедряются в неё автоматически; выше они переданы стандартной фабрике явно.
При изменении этих стратегий пересоберите и фабрику.

Настройка действует на все операции этого клиента. Поэтому метод для конкретного
DTO проверяет тип; другая операция может возвращать массив, коллекцию или `null`.
Если нужны только свои коды ошибок, достаточно `errorMapper` без нового класса результата.

## 4. Уточнить тип для IDE <a id="section-6"></a>

Объявленный тип `ResultHandle::resolved()` остаётся `ResolvedResultInterface`.
Настройка фабрики не меняет сигнатуру метода. Проверка `instanceof` одновременно
проверяет подключение и открывает дополнительные методы для IDE:

```php
use Example\CustomResult\SdkResult;

$handle = $client->records()->get(7)->send();
$resolved = $handle->resolved(); // Объявленный тип — ResolvedResultInterface.
if (!$resolved instanceof SdkResult) {
    throw new LogicException('Клиент должен использовать SdkResultFactory');
}
// После проверки IDE видит методы SdkResult и конкретный тип RecordDto.
$record = $resolved->recordOrFail();
$needsLogin = $resolved->requiresReauthorization();
```

В собственном SDK такую проверку можно вынести в метод, возвращающий `SdkResult`.
Один PHPDoc `@var` лишь подсказывает тип IDE и не проверяет объект во время выполнения.

`send()` возвращает `ResultHandle`, `raw()` — `ExecutionResult`.
`$handle->dataOrFail()` читает данные исходного результата и не вызывает метод
`recordOrFail()`. `request->resolvedAsync()` использует ту же фабрику. Повторное чтение
`resolved()` не отправляет HTTP, но может создавать новую обёртку: не полагайтесь
на её объектную идентичность. В этом примере `result()` всегда возвращает тот же
`ExecutionResult` с сохранёнными `trace`, `audit`, `debug` и дочерними результатами.

[Контракт фабрик](../../reference/results/handles.md#section-10) ·
[Маппинг ошибок](../../reference/results/errors.md) · [Другие рецепты](../README.md).
