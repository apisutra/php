<!-- languages --> <a href="../../../en/guides/recipes/continuation.md">English</a> · <a href="continuation.md">Русский</a> <!-- /languages -->
# Добавить ожидание операции <a id="section-1"></a>

[Исполняемый пример](../../examples/continuation.md) показывает старт операции,
два poll-запроса и финальный DTO. Он работает с локальными ответами.

## Подготовить контракт провайдера <a id="section-2"></a>

1. Установите, какое поле различает ожидание, успех и терминальную ошибку операции.
   Успешный HTTP-ответ сам по себе не означает готовности результата.
2. Опишите запрос запуска и запрос проверки состояния. Свяжите финальный DTO,
   poll-класс и resolver через [ContinuationResult](../../reference/attributes/response.md#section-6).
3. Реализуйте [критерий готовности](../../reference/execution/continuation-state.md).
   Учебный [OperationStateResolver](../../../example/continuation/src/OperationStateResolver.php)
   различает Pending, Ready и Failed по полю `status`.
4. Подключите extractor токена к клиенту. В учебном SDK это
   [TokenExtractor](../../../example/continuation/src/TokenExtractor.php).
5. Выберите пределы ожидания и проверьте pending, готовый результат, ошибку,
   исчерпание попыток и неверный тип финального DTO.

## Дождаться результата <a id="section-3"></a>

В [полном примере](../../../example/continuation/run.php) `$client` уже собран с
конфигурацией и транспортом:

```php
use ApiSutra\Continuation\ContinuationAwaitOptions;
use Example\Continuation\StartRequest;

$handle = $client->send(new StartRequest());
$final = $handle->await(new ContinuationAwaitOptions(maxAttempts: 2, intervalMs: 0));
```

Нулевой интервал используется для локального примера. Для настоящего API выберите
интервал по его контракту. `maxAttempts` ограничивает число poll-запросов.

Режимы Auto/Sync/Async, ожидание по сохранённому токену, повторный await и доставка
ошибок описаны в [контракте ожидания](../../reference/execution/continuation-await.md).
