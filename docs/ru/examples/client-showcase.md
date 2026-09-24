<!-- languages --> <a href="../../en/examples/client-showcase.md">English</a> · <a href="client-showcase.md">Русский</a> <!-- /languages -->
# Создание и настройка клиента <a id="section-1"></a>

Один вымышленный SDK показывает клиентские настройки через результат выполнения.
MockTransport возвращает локальные ответы; сеть, Laravel и настоящие credentials
не нужны. Все токены в примере вымышленные.

Из checkout пакета:

```bash
php docs/example/client-showcase/run.php
```

Из приложения, установившего пакет:

```bash
php vendor/apisutra/php/docs/example/client-showcase/run.php
```

[Обзор с пояснениями](../guides/client/showcase.md) разбирает фрагменты по задачам.
Скрипт печатает JSON; [ожидаемый результат](../../example/client-showcase/fixtures/expected.json) задан отдельно.

| Файлы | Назначение |
| --- | --- |
| [run.php](../../example/client-showcase/run.php) | Подключение, auth, retry, квота, кеш, DTO, диагностика и overrides |
| [DemoClient](../../example/client-showcase/src/DemoClient.php), [ресурс](../../example/client-showcase/src/Resources/Records/RecordsResource.php) | Точка сборки и `records()->get(7)` |
| [GetRecordRequest](../../example/client-showcase/src/Resources/Records/Get/GetRecordRequest.php) | GET с типизированным ответом; политики исполнения берутся из клиента |
| [RecordDto](../../example/client-showcase/src/Resources/Records/RecordDto.php), [SaveRecordRequest](../../example/client-showcase/src/Resources/Records/Save/SaveRecordRequest.php) | Общая модель чтения/сохранения и проверка исходящего JSON |
| [MemoryStore](../../example/client-showcase/src/Support/MemoryStore.php), [MemoryLogger](../../example/client-showcase/src/Support/MemoryLogger.php) | Учебные реализации в памяти; приложение передаёт свой PSR-16 store и PSR-3 logger |

Ожидаются две попытки для последовательности 503 → 200, локальный отказ третьего
запроса при квоте 2/min и один HTTP-вызов на два чтения с кешем. Неверный строковый
`id` отвергается строгими правилами; `_extra` доступен в DTO и исключён из запроса.
Диагностика маскирует Bearer, а разовые опции сохраняют исходный request и конфигурацию.
Проверяется передача таймаутов транспорту, без моделирования сетевого ожидания.

[Все примеры](README.md) · [Полная конфигурация](../reference/client/configuration.md).
