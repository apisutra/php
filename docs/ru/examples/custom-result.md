<!-- languages --> <a href="../../en/examples/custom-result.md">English</a> · <a href="custom-result.md">Русский</a> <!-- /languages -->
# Собственное представление результата <a id="section-1"></a>

`SdkResult` добавляет методы учебного SDK: `recordOrFail()` возвращает `RecordDto`,
`requiresReauthorization()` распознаёт HTTP 401. Стандартный интерфейс делегируется
готовому `ResolvedResult`; исходный `ExecutionResult` и ошибки сохраняются.

Из checkout пакета:

```bash
php docs/example/custom-result/run.php
```

Из приложения с установленным пакетом:

```bash
php vendor/apisutra/php/docs/example/custom-result/run.php
```

MockTransport возвращает локальные ответы; сеть, Laravel и credentials не нужны.
Скрипт печатает JSON с [фиксированными ожиданиями](../../example/custom-result/fixtures/expected.json).

| Файл | Назначение |
| --- | --- |
| [SdkResult](../../example/custom-result/src/SdkResult.php) | Два метода SDK и полное делегирование ResolvedResultInterface |
| [SdkResultFactory](../../example/custom-result/src/SdkResultFactory.php) | Оборачивает результат стандартной фабрики |
| [run.php](../../example/custom-result/run.php) | Настройка клиента, уточнение типа, успех, HTTP-ошибка и другая операция |

Используются готовые классы из [обзора клиента](client-showcase.md)
и [TokenExtractor](../../example/continuation/src/TokenExtractor.php) из примера ожидания.
Проверяется сохранение настройки extractor; polling здесь не запускается.
Три операции дают три HTTP-вызова: чтение DTO, результата и диагностики не отправляет запрос заново.

[Рецепт с пояснениями](../guides/recipes/custom-result.md) ·
[Контракт результатов](../reference/results/handles.md#section-10) ·
[Все примеры](README.md).
