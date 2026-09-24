<!-- languages --> <a href="../../en/glossary/testing.md">English</a> · <a href="testing.md">Русский</a> <!-- /languages -->
# Тестирование <a id="section-1"></a>

| Термин | Значение | Подробнее |
| --- | --- | --- |
| <a id="transportinterface"></a> TransportInterface | Контракт синхронной и promise-отправки PreparedRequest, который клиент получает явно. | [Контракт](../reference/execution/transport.md) |
| <a id="httptransport"></a> HttpTransport | Production-реализация TransportInterface. | [Контракт](../reference/execution/transport.md) |
| <a id="mocktransport"></a> MockTransport | Testing-реализация TransportInterface. | [Контракт](../reference/testing/mocking.md) |
| <a id="mockclient"></a> MockClient | Фасад для настройки тестирования. | [Контракт](../reference/testing/mocking.md) |
| <a id="mockconfig"></a> MockConfig | Конфигурация тестирования. | [Контракт](../reference/testing/mocking.md) |
| <a id="fake"></a> fake() | Метод AbstractClient для регистрации mock-ответов. | [Контракт](../reference/testing/mocking.md) |
| <a id="mockresponse"></a> MockResponse | Value Object для mock-ответа. | [Контракт](../reference/testing/mocking.md) |
| <a id="assertsent"></a> assertSent() | Метод для проверки вызова запроса в тестах. | [Контракт](../reference/testing/mocking.md) |
| <a id="assertnothingsent"></a> assertNothingSent() | Метод для проверки отсутствия вызовов. | [Контракт](../reference/testing/mocking.md) |
| <a id="preventstrayrequests"></a> preventStrayRequests() | Метод AbstractClient. | [Контракт](../reference/testing/mocking.md) |
| <a id="record--playback"></a> record() / playback() | Методы для записи и воспроизведения fixtures. | [Контракт](../reference/testing/fixtures.md) |
| <a id="fixture"></a> Fixture | Базовый класс для кастомных fixtures с redacting. | [Контракт](../reference/testing/fixtures.md) |
| <a id="liveenvloader"></a> LiveEnvLoader | Загружает env-файл для live-тестов, сохраняя приоритет уже заданных переменных окружения. | [Контракт](../reference/testing/live.md) |
| <a id="livepolling"></a> LivePolling | Повторяет callback до выполнения критерия готовности или таймаута. | [Контракт](../reference/testing/live.md) |
| <a id="liveresultassertions"></a> LiveResultAssertions | Проверяет успешность ResolvedResult и ожидаемый класс его данных. | [Контракт](../reference/testing/live.md) |
| <a id="request-pool"></a> Pool (пул запросов) | Поток запросов с ограничением активных задач; promise API не гарантирует параллельный HTTP. | [Контракт](../reference/execution/batch-pool.md) |

[Все термины](README.md).
