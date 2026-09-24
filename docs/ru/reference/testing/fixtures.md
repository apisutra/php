<!-- languages --> <a href="../../../en/reference/testing/fixtures.md">English</a> · <a href="fixtures.md">Русский</a> <!-- /languages -->
# Запись и воспроизведение ответов <a id="section-1"></a>

## Фикстуры (record/playback) <a id="section-2"></a>

Recorder маскирует стандартные credentials по умолчанию, даже без Fixture.
`$client->record()` использует `ClientConfig::redaction`; правила пользовательского
Fixture дополняют базовые. Настройка и границы маскирования — в
[логировании](../results/observability.md#section-8).

```php
use ApiSutra\Testing\Fixture;

final class UserFixture extends Fixture
{
    protected function defineSensitiveHeaders(): array
    {
        return ['Authorization' => '***'];
    }
}

$client->record(__DIR__ . '/fixtures', [
    GetUser::class => new UserFixture(),
]);

$client->playback(__DIR__ . '/fixtures');
```

В фикстурах можно скрывать:
- заголовки
- JSON‑поля
- regex‑паттерны

По умолчанию, если фикстуры отсутствуют, запрос считается не смоканным
и вернётся `MockResponse::notFound()` (если не включён `preventStrayRequests()`).

Если фикстуры отсутствуют, можно включить строгий режим:
```php
use ApiSutra\Testing\MockConfig;

MockConfig::throwOnMissingFixtures();
```

В строгом режиме отсутствие фикстуры приводит к исключению.

## Ошибки recording <a id="section-3"></a>

Recorder включается явно. Неудачная запись возвращает `execution_error` с
`reason=recording_failed` и `RecordingException`; в `response` сохраняется фактический
HTTP-ответ, а в `previous` — техническая причина. Автоматическое сообщение не содержит
пути каталога или тела. В result-first режиме проверяйте ошибку; при throwOnErrors
обрабатывайте исключение.

HTTP к этому моменту уже выполнен: например, POST создал объект, но записать fixture
не удалось. SDK не повторяет HTTP из-за этой ошибки даже при разрешённом POST retry
и `retryExceptions: [Throwable::class]`. Приложение также должно отличать её от
сетевой ошибки, чтобы не создать объект повторно.

Fixture публикуется целиком под свободным именем, без перезаписи существующего файла.
Конкурирующие recorder получают разные имена. Прерванный процесс может оставить
скрытый временный файл `.recording-*`, но не частично опубликованную JSON fixture.
Корректные большие JSON и текст не обрезаются лимитом safe debug/log. Невалидный UTF-8
вызывает явную ошибку записи вместо пустого файла; нового бинарного формата нет.
Потоки сохраняют bodyOmitted/size и не читаются ради fixture.
