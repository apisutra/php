<!-- languages --> <a href="../../../en/reference/execution/cooldown.md">English</a> · <a href="cooldown.md">Русский</a> <!-- /languages -->
# Общий запрет после HTTP 429 <a id="overview"></a>

Положительный корректный `Retry-After` фактического HTTP 429 откладывает следующие
HTTP-попытки той же группы. Механизм действует без включения retry или локальных квот.
По умолчанию группа — класс запроса с изоляцией по origin назначения и credentials,
внутри **одного экземпляра клиента по умолчанию**. Cache hit остаётся доступен; уже отправленный
HTTP продолжается. Разные классы независимы, пока их явно не объединят.

`send()` ждёт синхронно; `sendAsync()` — кооперативно. Запрос, получивший 429, повторяется
только по обычным правилам безопасности и числа попыток. Cooldown не делает безопасным
повтор POST, одноразового token exchange или неперематываемого тела.

## Настройка и переопределения <a id="configuration"></a>

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\CooldownConfig;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;

// Для штатного поведения настройка cooldown не нужна.
$config = new ClientConfig(baseUrl: 'https://api.example');

// Только если операции действительно входят в одну область квоты провайдера.
$config = $config->with(cooldown: new CooldownConfig(
    group: 'reports',
    behavior: RateLimitBehavior::Throw,
));
```

| Поле CooldownConfig | Default | Назначение |
| --- | --- | --- |
| enabled | true | Наблюдать и учитывать серверный запрет. |
| group | null | Класс запроса; явная группа объединяет классы внутри одного origin и identity. |
| behavior | Wait | Ждать, если разрешено, иначе локальный отказ; Throw не ждёт cooldown. |
| identity | null | Необязательный непрозрачный ID подключения/tenant для custom auth. Не передавать секрет. |
| maxAdditionalWaitMs | null | Автоматический предел; явно заданное неотрицательное число действует всегда. |

Пустые group/identity, отрицательный или непредставимый предел — ошибки конфигурации.
Полная runtime-конфигурация перекрывает атрибут запроса и defaults клиента:

```php
use ApiSutra\Attributes\Behavior\Cooldown;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;

#[Get('/reports')]
#[Cooldown(group: 'reports', maxAdditionalWaitMs: 5000)]
final class ReportsRequest extends AbstractRequest {}

// $client — настроенный клиент вашего SDK. Исходный запрос можно переиспользовать.
$request = new ReportsRequest();
$result = $client->send($request->withCooldown(new CooldownConfig(enabled: false)));
```

Атрибут поддерживает enabled, group, behavior и maxAdditionalWaitMs. Неуказанное/null
поле атрибута наследует значение клиента. В CooldownConfig неуказанный/null
maxAdditionalWaitMs означает **автоматический выбор**. Для возврата к auto поверх
атрибута или клиентского предела передайте полный runtime CooldownConfig; остальные его
поля тоже становятся правилом. RequestOptions::withCooldown() даёт ту же опцию.
Копирующие with-методы её сохраняют.

`withoutRetry()` не отключает cooldown. `withoutRateLimit()` отключает только квоты.
Отключение cooldown не отменяет собственный Retry-After или backoff текущего повтора.

## Сколько длится ожидание <a id="waiting"></a>

Серверный запрет сохраняется целиком. SDK не сокращает его под локальный предел.

| Настройка | Ограничитель |
| --- | --- |
| Auto, есть конечный бюджет исполнения | Остаток бюджета; добавочный default-порог не применяется. |
| Auto, общего дедлайна нет | До 1000 ms дополнительного ожидания cooldown суммарно на исполнение. |
| Явный maxAdditionalWaitMs | Этот совокупный предел **и** бюджет исполнения, если задан. |

Бюджет приходит из RetryConfig::totalTimeoutMs, внешнего deadline или родительского
исполнения. `withTimeout()` ограничивает одну HTTP-попытку и не заменяет общий бюджет.
Истёкший deadline остаётся ошибкой и не включает запасной предел.

Новый вызов с cooldown на 30 секунд в auto ждёт, если осталось 60 секунд бюджета.
Без бюджета он отказывает до сна. Явный maxAdditionalWaitMs=1000 также отказывает
при этом бюджете 60 секунд. Ровно 1000 ms помещается в запасной предел.
Ноль запрещает дополнительное ожидание cooldown.

Для уже разрешённого повтора R — остаток его собственного backoff/Retry-After,
C — остаток общего cooldown. SDK ждёт max(R, C); только max(0, C − R) расходует
дополнительный лимит. Повторные ожидания и продления не обнуляют счётчик.
Собственный Retry-After=2 секунды продолжает работать с добавочным лимитом 1 секунда.
Уже разрешённый часовой Retry-After без бюджета всё ещё может ждать час:
для ограничения всего времени исполнения нужен общий бюджет.

Если полное ожидание не помещается в бюджет, результат — прежний timeout с
reason `execution_deadline_exceeded` и stage `cooldown_wait`, когда ожидание определяет
cooldown (`retry_wait` для обычного повтора). Если не хватает только дополнительного
лимита — локальный отказ cooldown. Оба решения принимаются до sleep. Throw проверяет
отмену/истёкший бюджет и сразу отказывает. Издержки планирования означают, что предел
не является точной границей реального времени всего вызова.

## Ошибки и наблюдение <a id="errors"></a>

Локальный отказ использует прежний контракт result-first / throwOnErrors / dataOrFail:
ErrorCode::RateLimited, reason `server_cooldown_active`, stage `cooldown` и
CooldownException с retryAfterMs и округлённым вверх до секунд retryAfter. Его response
равен null; lastResponse может содержать прежнюю попытку **этого исполнения**, но не
чужой ответ. Это отличается от `local_rate_limit_exceeded` и фактического HTTP 429.
Локальный отказ и ожидание не создают HTTP-попытку/span. Идентификаторы трассировки
сохраняются. Широкий retryExceptions не повторяет локальный отказ.

Только фактический 429 с положительными секундами или поддержанной HTTP-date публикует
cooldown до AfterResponse. Локальный backend записывает его сразу; внешний — только при
живом бюджете и без отмены, поскольку публикация требует I/O. Retry и cooldown используют
одно значение задержки и момент ответа. Ошибка записи сохраняет первичную причину даже
при сбое публикации; отмена сохраняет приоритет. Отсутствующий/некорректный/нулевой/прошедший Retry-After
не создаёт и не стирает запрет. Более короткий 429 и успех не сокращают его.
HTTP 503 влияет лишь на текущий retry. Диагностика ожиданий и продлений содержит время
и trace-контекст без исходных групп, credentials или подписанных URL.

## Identity и срок жизни <a id="identity"></a>

Штатные authenticators используют свою существующую стабильную identity, в том числе
при обычной OAuth2-ротации с прежними effective scopes. Также учитываются известные
credential headers/query и изменения от hooks; body и upload streams не читаются
ради угадывания tenant. Явная группа не снимает изоляцию origin/credential.
Custom authenticator может реализовать CacheIdentityProviderInterface (без I/O)
либо получить явную cooldown identity. Если стабильная идентичность неизвестна,
автоматическая координация пропускается с debug reason `cooldown_identity_unavailable`.
Исключение identity provider становится ошибкой конфигурации. Для нестандартных полей
tenant/credential identity задаёт приложение: SDK не обнаруживает произвольные секреты.

Состояние принадлежит клиенту и переживает смену транспорта fake/record/playback.
Без явного cooldownBackend новые клиенты и процессы независимы даже с общим Redis backend
квот. Общий cooldown backend связывает только совпадающие области.
Обязательного хранилища, таймера на группу, очереди или новой зависимости нет.
Истёкшие записи удаляются лениво. Отмена вызова не удаляет запрет группы.

Допуск выполняется после ожиданий, перед получением квоты и ещё раз после возможного
ожидания квоты. Поздний запрет может оставить permit использованным без HTTP; гарантий
возврата или резервирования нет. Между последним допуском и входом в транспорт SDK
не приостанавливается. Собственный транспорт владеет дальнейшим I/O и обязан сохранять
гарантии безопасности подготовленного запроса и таймаутов.

## Импорт через pool/consume <a id="imports"></a>

Для импорта, который должен пережидать серверные паузы, используйте auto и конечный
бюджет. RetryConfig::totalTimeoutMs начинается отдельно для каждого элемента;
это не дедлайн всего импорта. Для общего срока создайте один
[внешний deadline](deadlines.md#section-4) и передайте каждому запросу, в том числе ещё
не начавшемуся. Элементы с истёкшим сроком не отправляют HTTP.

Без бюджета длинный cooldown может дать серию локальных отказов pool/consume,
пока источник продолжает читаться. withStopOnFailure() прекращает новые старты после
отказа; не ждёт снятия запрета и не возвращает элементы в очередь. Начатая работа
подчиняется [контракту pool](pool-consumption.md). Первый 429 остаётся под обычными
правилами retry. Повторная постановка, хранение результатов и checkpoint — у приложения.

Локальный пример без сети с виртуальным временем:
`php docs/example/cooldown/run.php`. Он показывает defaults, ожидание с бюджетом и
consume с общим deadline. Независимые политики описаны в разделах
[квот](rate-limit.md) и [повторов](retry.md).

## Общий backend <a id="shared-backend"></a>

ClientConfig::cooldownBackend по умолчанию null: клиент владеет локальным backend,
без инфраструктуры и дополнительных настроек. Для общности клиентов одного процесса:

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\RateLimiting\Cooldown\Backends\LocalCooldownBackend;

$backend = new LocalCooldownBackend();
$config = new ClientConfig(baseUrl: 'https://api.example', cooldownBackend: $backend);
// Передайте конфигурацию клиентам, которым нужна общность совпадающих областей.
```

Для разных процессов явно подключите [Redis backend](../integrations/redis.md#cooldown).
Backend — зависимость клиента, отдельно от CooldownConfig и request overrides.
with() и fake/record/playback сохраняют переданный объект. В тестах подставляйте локальный
backend, если эффекты Redis нежелательны: тестовый 429 также публикуется в выбранное хранилище.

Для общности должны совпасть хранилище и вся область: origin, класс/identity авторизации,
фактические известные поля credentials, необязательная cooldown identity и group
(по умолчанию класс запроса). Явная group не объединяет разные аккаунты или hosts.
Один 429 от /reports автоматически не запрещает /users. Одного общего Redis недостаточно.
В OAuth Authorization Code сохраняйте identity подключения вместе с токенами:

```php
use ApiSutra\Auth\OAuth2\OAuth2Credential;
use ApiSutra\Auth\OAuth2\OAuth2TokenSet;

// Оба значения загружаются из серверного хранилища credentials приложения.
$credential = new OAuth2Credential(OAuth2TokenSet::restore($snapshot), identity: $connectionId);
```

Восстановление токенов с новой случайной identity создаёт другую область. Обычная ротация
сохраняет identity; изменение effective scopes сохраняет прежние правила изоляции.

Сторонний backend реализует CooldownBackendInterface: remainingMs(key, timeoutMs) возвращает
неотрицательный остаток в ms (0 при отсутствии/истечении); extend(key, delayMs, timeoutMs)
атомарно оставляет больший остаток и возвращает CooldownUpdate(extended, remainingMs).
Все длительности, включая timeoutMs, — миллисекунды. Extend принимает положительный срок;
extended=true требует положительного остатка. Ключи непрозрачны. Backend соблюдает I/O timeout;
SDK не может прервать произвольный PHP-код. Backend не ждёт cooldown, не отправляет HTTP,
не повторяет запись и не владеет trace. Абсолютные часы между процессами не передаются.

Сбой backend прекращает вызов: execution_error, reason cooldown_backend_error,
stage cooldown_read или cooldown_publish. При отказе чтения HTTP нет. При отказе публикации
сохраняется фактический 429 этого исполнения; HTTP и неопределённая запись не повторяются.
Автоматического fallback в память и опции fail-open нет. Истечение общего срока —
timeout / execution_deadline_exceeded с тем же stage; ожидание использует cooldown_wait.
Вторичная ошибка публикации не заменяет recording_failed или отмену.

Локальный backend запоминает полученный 429 сразу; удалённый публикует только при живом
бюджете и без отмены, поскольку публикация требует I/O. Оба используют один разбор задержки
и момент получения ответа; публикация предшествует AfterResponse. Это не гарантия доставки
срока после истечения budget.

Безопасные DEBUG-события rate_limit.cooldown_storage содержат operation, duration_ms, outcome
и trace. Лог финального чтения откладывается до выхода из допуска. Terminal-лог отказа
содержит reason/stage (и retryAfterMs для активного запрета); исходные ключи, identity,
сообщения Redis exception и секреты соединения не экспортируются. Debug response до HTTP
равен null, после сбоя публикации — фактический ответ этого исполнения, а не другого клиента.
I/O расходует общий budget, но не начисляется как добавочный cooldown sleep.

Допуск и HTTP провайдеру не являются распределённой транзакцией. Другой процесс может
опубликовать запрет после последнего read, пока уже допущенная попытка начинает HTTP.
Нет обещания refund, rollback, надёжной доставки 429 или отмены отправленного HTTP.

Для осознанного возврата к локальной координации создайте **новый клиент** с
$config->with(cooldownBackend: null). Его состояние пустое: сроки Redis не импортируются.
Существующие клиенты/операции и ключи Redis остаются прежними. Redis-зависимости квот/auth
не отключаются. Применение настройки организует приложение; это не live switch и не fallback.

`php docs/example/cooldown/shared.php` показывает два клиента, независимые области и ожидание
по бюджету без сети. Команды для разных процессов приведены в справочнике Redis.
