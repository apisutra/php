<!-- languages --> <a href="../../../en/guides/testing/unit.md">English</a> · <a href="unit.md">Русский</a> <!-- /languages -->
# Проверить операцию SDK <a id="section-1"></a>

Основной тест должен пройти без внешнего API: с реальной конфигурацией SDK,
транспортом fake, исходным HTTP-ответом и публичным вызовом операции.

## Подготовить сценарий <a id="section-2"></a>

1. Создайте клиента тем же способом, что и потребитель SDK.
2. Настройте MockTransport по классу запроса и включите preventStrayRequests().
3. Используйте обезличенные JSON-фикстуры; не вычисляйте expected из actual.
4. Вызовите метод ресурса и проверьте подготовленные method, URL, параметры и body.
5. Проверьте DTO или данные результата, статус, errors и доступный контекст.

[Учебный run.php](../../../example/sdk/run.php) показывает success и HTTP 404.
[Mock API](../../reference/testing/mocking.md) описывает fake, sequence, callback и assertions;
[фикстуры](../../reference/testing/fixtures.md) — запись и воспроизведение.

## Минимальная матрица <a id="section-3"></a>

| Сценарий | Проверка |
| --- | --- |
| Обычный запрос | Точное исходящее представление, DTO и успешный статус |
| Неверный вход | Ошибка валидации/конфигурации до HTTP |
| Ошибка API | provider/client код, сообщение и HTTP status |
| Missing/null/неверный тип DTO | Документированная реакция и путь поля |
| Неизвестные данные | Остаток receiver, если он включён, и отсутствие receiver на проводе |
| Повреждённый JSON | Ошибка декодирования с доступным исходным ответом |

При использовании механизма добавьте отдельные случаи: oneOf/discriminator,
пагинация/meta, batch/composite, await, файлы, auth refresh и Laravel binding.
[Карта покрытия](../sdk/coverage.md) помогает выбрать необходимый набор.

## Валидация и диагностика <a id="section-4"></a>

Для `#[Validate]` предоставьте рабочую фабрику либо явно проверяйте configuration_error
при её отсутствии. Для нескольких клиентов проверьте порядок A→B→A; одна фабрика
не должна заменять другую. `Validator::resetFactory()` очищает глобальную фабрику;
сброс container registry сам по себе её не очищает.
[Приоритет фабрики](../../reference/client/validation.md#section-6).

Для ошибок DTO проверяйте `reason/path/expected/actual`, а не текст PHP TypeError.
[HydrationException](../../reference/dto/diagnostics.md) в standalone выходит прямо;
raw/resolved сохраняют ошибку, dataOrFail/throwOnErrors доставляют исключение.
Проверьте доступ к исходному ответу при debug=false и отсутствие искусственного
секрета в автоматическом ERROR log. Для JsonCast различайте повреждённую строку и JSON null.

Контракт mock не доказывает поведение провайдера: при необходимости добавьте
[отдельную live-проверку](live.md) с явным разрешением и контролем стоимости.
