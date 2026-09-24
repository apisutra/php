<!-- languages --> <a href="../../../en/reference/testing/mocking.md">English</a> · <a href="mocking.md">Русский</a> <!-- /languages -->
# Fake и проверки запросов <a id="section-1"></a>

ApiSutra предоставляет базовые testing primitives: `fake`, фикстуры, assert‑методы
и несколько framework-agnostic helper-классов.

Важно:
- этот гайд описывает именно возможности core-пакета;
- архитектура live-suite конкретного SDK (support-слой, структура папок, Makefile, дампы, preflight и т.д.) строится на стороне SDK-провайдера;
- live-стратегия зависит от провайдера и не является обязательной частью каждого SDK.

## Быстрый fake <a id="section-2"></a>
```php
use ApiSutra\Testing\MockResponse;

$client->fake([
    GetUser::class => MockResponse::success(['id' => 1, 'name' => 'Alice']),
]);

$client->preventStrayRequests();
```

`preventStrayRequests()` требует активный `MockTransport`: сначала вызовите `fake()`
или `playback()` либо передайте mock-транспорт при создании клиента.
Без него метод немедленно бросает `ConfigurationException` и не заменяет транспорт.
С mock незаданные запросы отклоняются вместо стандартного синтетического 404.
Чтобы отклонять все запросы до настройки ответов, вызовите `fake([])`, затем
`preventStrayRequests()`. Метод `record()` оборачивает текущий транспорт и не является fake.

Можно мокать по паттерну URL:
```php
$client->fake([
    'https://api.example/users/*' => MockResponse::success([]),
    '*' => MockResponse::notFound(),
]);
```

Приоритет поиска: сначала точное совпадение по классу запроса,
затем паттерны URL, затем `*` (fallback).

### Большие идентификаторы в fake и фикстурах <a id="section-3"></a>

Для проверки большого числового литерала передавайте raw JSON:

```php
use ApiSutra\Testing\MockResponse;

$response = MockResponse::make('{"id":9223372036854775808}');
```

PHP float в массиве мог потерять цифры ещё до вызова MockResponse. RecordingTransport,
playback и безопасная диагностика сохраняют точные цифры больших целых; в записанном
JSON они могут стать строками. Raw HTTP-ответ при записи не изменяется. Маскирование
секретов сохраняется, байтовое равенство фикстуры с исходным телом не гарантируется.

## Файловые ответы <a id="section-4"></a>

Для `#[Download]` используйте `MockResponse::file('/path/to/fixture.bin')`.
Каждый вызов открывает новую ручку, поэтому закрытие предыдущего `FileResponse`
не повреждает следующую попытку или тест. Поддерживаются status и headers,
а также включение файлового ответа в `MockResponse::sequence()`.

Recorder не читает upload/download поток ради fixture. Он записывает метаданные
и `bodyOmitted: true`; такая запись не содержит полного тела. Playback явно
отклоняет её и предлагает файловую fixture, вместо успешного пустого ответа.

## Последовательности ответов <a id="section-5"></a>
```php
$client->fake([
    GetUser::class => MockResponse::sequence([
        MockResponse::success(['id' => 1]),
        MockResponse::success(['id' => 2]),
    ]),
]);
```

## Динамические ответы <a id="section-6"></a>
Ответ может быть callable:
```php
$client->fake([
    GetUser::class => function (GetUser $request) {
        return MockResponse::success(['id' => $request->id]);
    },
]);
```

## Ассерты <a id="section-7"></a>
```php
$client->assertSent(GetUser::class);
$client->assertNotSent(DeleteUser::class);
$client->assertNothingSent();
```

Все три проверки требуют активный `MockTransport` и бросают `ConfigurationException`
при его отсутствии, даже если запросов ещё не было. Пустая история ради успешной
проверки не создаётся. Ассерты считают транспортные попытки, включая повторы и
отклонённые незаданные запросы; попадания в кеш не добавляют попыток.

Например, один `send()` запроса `GetUser`, получивший 503, а затем 200 при разрешённом
retry, создаёт две транспортные попытки: `assertSent(GetUser::class, times: 2)`
проходит, а `times: 1` — нет. Отдельный запрос авторизации считается под своим классом,
а не как ещё одна попытка GetUser. Эти проверки не доказывают число бизнес-действий
на стороне провайдера: они проверяют попытки отправки SDK.

## Глобальный MockClient <a id="section-8"></a>
```php
use ApiSutra\Testing\MockClient;

MockClient::global([GetUser::class => MockResponse::success()]);
// ...
MockClient::destroyGlobal();
```

## Helper для oneOf-контрактов <a id="section-9"></a>
Для тестов SDK-провайдеров доступен framework-agnostic helper:
`ApiSutra\Testing\RequestContractTestHelper`.

Пример:
```php
use ApiSutra\Testing\RequestContractTestHelper;

$result = $request->send()->raw();

$isContractError = RequestContractTestHelper::isRequestContractViolation($result);
$context = RequestContractTestHelper::context($result);
$codes = RequestContractTestHelper::violationCodes($result);
$hasMismatch = RequestContractTestHelper::hasViolationCode($result, 'discriminator_mismatch');
```

Helper не зависит от PHPUnit/Pest и подходит для обоих стилей тестирования.

Практическое правило:
- если SDK использует `RequestOneOf` / `RequestDiscriminator`, helper почти всегда стоит считать baseline;
- если взаимоисключающих payload-вариантов нет, этот слой не нужен.

## Обратимая тестовая сессия <a id="session"></a>

`$client->beginFakeSession()` возвращает ClientFakeSession с `fake`, `assertSent`,
`assertNotSent`, `assertNothingSent`, `verify` и идемпотентным `close`. Verify проверяет
пропущенные mock, close восстанавливает транспорт; вызывайте оба при завершении
своего тестового lifecycle, close — в finally. Сессия не зависит от PHPUnit.
В Laravel это автоматически делает [trait интеграции](https://github.com/apisutra/laravel/blob/master/docs/ru/reference/integrations/testing.md).
Fake-сессия не сбрасывает auth/cache/квоты/cooldown; повторный fake начинает новую историю,
но не забывает нарушения. Активные async/ленивые исполнения запрещают замену транспорта.
