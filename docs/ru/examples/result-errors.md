<!-- languages --> <a href="../../en/examples/result-errors.md">English</a> · <a href="result-errors.md">Русский</a> <!-- /languages -->
# Сообщения операций и исключения SDK <a id="section-1"></a>

Из корня пакета после установки зависимостей:

```bash
php docs/example/result-errors/run.php
```

[DemoClient](../../example/result-errors/src/DemoClient.php) содержит два метода с типом `AccountInfo` без
ручного `instanceof`: `Returns` обеспечивает проверку во время выполнения.
[Запрос аккаунта](../../example/result-errors/src/GetAccountRequest.php) и [запрос владельца](../../example/result-errors/src/GetOwnerRequest.php)
задают разные сообщения. [Фабрика](../../example/result-errors/src/ProviderExceptionFactory.php) подключается
один раз к клиенту и создаёт собственное исключение только для нарушения типа;
для HTTP-ошибки оставляет штатное исключение.

[Сценарий](../../example/result-errors/run.php) сначала выполняет обычные успешные запросы без конфигурации
исключений, затем подключает намеренно ошибочное расширение, возвращающее строку
вместо DTO. Показаны штатный отказ, собственные сообщения и HTTP fallback.
Ожидаемый вывод — в [фикстуре](../../example/result-errors/fixtures/expected.json).

Пример использует MockTransport: сеть, Laravel и credentials не нужны.
Классы провайдера принадлежат примеру, а не ядру ApiSutra.
[Контракт и ограничения](../reference/results/exceptions.md).
