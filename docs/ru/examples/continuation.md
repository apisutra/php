<!-- languages --> <a href="../../en/examples/continuation.md">English</a> · <a href="continuation.md">Русский</a> <!-- /languages -->
# Пример ожидания операции <a id="section-1"></a>

Из checkout после Composer install:

```bash
php docs/example/continuation/run.php
```

Для установленного пакета добавьте `vendor/apisutra/php/` перед путём.
Ожидается `{"id":7,"cached_same_object":true,"requests":3}`.

[run.php](../../example/continuation/run.php) использует клиент учебного SDK, fake и строгие правила FinalDto.
[StartRequest](../../example/continuation/src/StartRequest.php) объявляет финальный тип и poll-запрос.
[PollRequest](../../example/continuation/src/PollRequest.php) принимает один обязательный token.
[TokenExtractor](../../example/continuation/src/TokenExtractor.php) читает его из исходного HTTP JSON;
[OperationStateResolver](../../example/continuation/src/OperationStateResolver.php) явно выбирает Pending/Ready/Failed.
[FinalDto](../../example/continuation/src/FinalDto.php) создаётся только после Ready.

Старт возвращает Pending; первый poll тоже Pending, второй Ready. Поэтому число
HTTP-вызовов равно 3 при maxAttempts = 2. Повторный await возвращает тот же DTO
без нового HTTP. Интервал равен нулю только для локального примера.

[Практика](../guides/recipes/continuation.md) ·
[Готовность](../reference/execution/continuation-state.md) ·
[Ожидание и ошибки](../reference/execution/continuation-await.md).
