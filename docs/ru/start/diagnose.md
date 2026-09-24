<!-- languages --> <a href="../../en/start/diagnose.md">English</a> · <a href="diagnose.md">Русский</a> <!-- /languages -->
# Диагностировать проблему <a id="section-1"></a>

Цель — получить воспроизводимый вход, наблюдаемую ошибку и точный нарушенный контракт.
Начните с операции и её конфигурации, а не с внутренних стадий ядра.

## Собрать факты <a id="section-2"></a>

Зафиксируйте версии PHP, ApiSutra и SDK; класс запроса, вид транспорта, факт
подключения Laravel и внешнего набора DTO. Сохраните обезличенный исходный ответ,
его HTTP status/Content-Type и минимальный вызов.

Получите `ExecutionResult` через `send()->raw()` либо `resolved()->result()`.
Проверьте статус, ошибки и исходное исключение. Для передачи коллегам используйте
[безопасный debug и логи](../reference/results/observability.md), а не полный raw context.

## Выбрать проверку <a id="section-3"></a>

| Наблюдение | Проверить |
| --- | --- |
| HTTP ещё не было | [Валидация](../reference/client/validation.md), [сборка запроса](../reference/serialization/request-parts.md), конфигурация |
| Неверный адрес или параметры | [URI/query](../reference/serialization/uri-query.md) и привязку клиента |
| 401, refresh или не тот scope | [Auth](../reference/auth/README.md) |
| Неверный тип, missing, Nested | [DTO diagnostics](../reference/dto/diagnostics.md), strict и форму входа |
| Повторы, 429, ожидание | [Retry, квоты, deadline](../reference/execution/README.md) |
| Await не завершился | [Готовность](../reference/execution/continuation-state.md), token и лимит poll |
| Лишнее или исчезнувшее поле запроса | [DX/wire](../reference/serialization/dto-output.md), [receiver](../reference/serialization/receiver-output.md) |

## Подтвердить причину <a id="section-4"></a>

Замените HTTP [локальной фикстурой](../reference/testing/fixtures.md), сохранив
реальные config/rules/unwrap. Сравните обещанный контракт с результатом. Если
доступен обход через поддержанный API, запишите его ограничения. Если контракт
нарушен, подготовьте минимальный пример, ожидаемый и фактический результат.

[Частые симптомы](../guides/troubleshooting.md) дают короткие готовые проверки.
Не меняйте src ApiSutra в пользовательском SDK ради диагностики.
