<!-- languages --> <a href="../../../en/reference/auth/oauth2.md">English</a> · <a href="oauth2.md">Русский</a> <!-- /languages -->
# OAuth2 <a id="section-1"></a>

Встроенные Client Credentials и Authorization Code с PKCE S256 используют обычное
исполнение ApiSutra: синхронный `send()`, ожидаемый `sendAsync()`, pool/batch, дедлайны,
квоты, трассировку и обработку ошибок результата. Дополнительные зависимости, маршруты,
сессия, БД или кеш не обязательны. Приложение задаёт endpoints и credentials провайдера;
ему принадлежат пользовательские сессии, хранение и координация между процессами.

## Client Credentials <a id="client-credentials"></a>

Задайте `auth` при создании клиента своего SDK:

```php
use ApiSutra\Auth\OAuth2\OAuth2Authenticator;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\OAuth2Config;

$config = new ClientConfig(
    baseUrl: 'https://api.example.test',
    auth: OAuth2Authenticator::clientCredentials(new OAuth2Config(
        tokenUrl: 'https://identity.example.test/token',
        clientId: 'application-id',
        clientSecret: 'application-secret',
        scopes: ['reports.read'],
    )),
);
```

Токен получается перед первым ресурсным запросом, переиспользуется и обновляется
по необходимости. Client Credentials использует существующие изолированный кеш токенов
и refresh locks; без store всё работает локально. Сохраняются границы provider/base URL,
auth scope и tenant кеша. Сам PSR-16 store не обеспечивает распределённую блокировку.
См. [хранение токенов](tokens.md).

`OAuth2Config` принимает `tokenUrl`, `clientId`, необязательный `clientSecret`, nullable
`scopes`, `clientAuthentication` и `tokenParameters`. По умолчанию применяется Basic,
требующий secret. `ClientAuthentication::Post` передаёт credentials в форме;
`ClientAuthentication::None` явно выбирает public client без secret и доступен для
Authorization Code, но не для Client Credentials. Basic применяет form-encoding
к ID и secret до Base64; credentials не дублируются в теле. В `tokenParameters`
можно задать `audience` или `resource`. Зарезервированные OAuth-поля подменять нельзя.
`tokenParameters` и `authorizationParameters` — карты string → string; числа, массивы
и зарезервированные имена вроде `scope`, `state`, `code`, `client_id` отклоняются.
URL требуют HTTPS без userinfo, fragment и зарезервированных OAuth query-параметров.

## Authorization Code <a id="authorization-code"></a>

Настройте отдельный flow для issuer и callback-маршрута. `$oauth` — ваш `OAuth2Config`,
`$client` — существующий клиент SDK. Храните attempt на сервере, связывайте с сессией
пользователя и атомарно погашайте при callback. `export()` содержит `codeVerifier`:
не помещайте его в cookie, redirect URL, хранилище браузера или логи.

```php
use ApiSutra\Auth\OAuth2\AuthorizationCodeFlow;

$flow = new AuthorizationCodeFlow(
    config: $oauth,
    authorizationUrl: 'https://identity.example.test/authorize',
    redirectUri: 'https://app.example.test/oauth/callback',
    expectedIssuer: 'https://identity.example.test',
);
$attempt = $flow->begin();
$serverSideSnapshot = $attempt->export(); // Сохранить под state этой сессии; не отправлять браузеру.
$redirectUrl = $attempt->url;
```

При callback загрузите и погасите attempt этой сессии до обмена code.
`$callbackParameters` содержит разобранные query либо form-post параметры.
`$actualCallbackUri` — фактический адрес обработчика **без OAuth-параметров ответа**;
он должен точно совпадать с `redirectUri`, включая настроенный статический query.

```php
use ApiSutra\Auth\OAuth2\AuthorizationAttempt;

$attempt = AuthorizationAttempt::restore($serverSideSnapshot);
$request = $flow->exchange($attempt, $callbackParameters, $actualCallbackUri);
$tokens = $client->sendAsync($request)->wait()->dataOrFail(); // OAuth2TokenSet
// Синхронный вариант: $tokens = $client->send($request)->dataOrFail();
```

Каждый `begin()` генерирует независимые state и verifier. По умолчанию attempt действует
600 секунд (`attemptTtlSeconds`); необязательный `clock` позволяет управлять временем.
`authorizationParameters` добавляет параметры провайдера, например `prompt`, с защитой
зарезервированных полей. Endpoints SDK и callback URL требуют HTTPS. До token HTTP
проверяются state, срок, привязка к flow, code, фактический callback URI и настроенный
issuer. Отказ пользователя также проходит проверку связи с attempt;
недоверенный `error_description` не становится сообщением исключения.

При `expectedIssuer` callback обязан содержать точный RFC 9207 `iss`. Иначе в приложении
с несколькими issuer используйте отдельный проверенный callback-маршрут для каждого.
Одна привязка attempt к `tokenUrl` не защищает от mix-up. Массивы вместо строк
отклоняются; приложение должно отклонять дублирующиеся параметры до того, как framework
оставит одно значение. SDK не может восстановить потерянные дубликаты. Независимые
повторные отправки одного exchange request не дают гарантии exactly-once.

## Credential и сохранение <a id="credential"></a>

Создавайте один credential для одного пользовательского подключения. Здесь `$tokens` —
результат обмена, `$connectionId` — стабильный ID записи приложения, `$saveTokens` —
синхронный callback приложения, который сохраняет `OAuth2TokenSet::export()` либо
бросает исключение при неудаче:

Первый результат обмена явно сохраните до создания credential. Конструктор не
вызывает `onTokensChanged`: callback обрабатывает последующие refresh и
`replaceAuthorization()`.

```php
use ApiSutra\Auth\OAuth2\OAuth2Authenticator;
use ApiSutra\Auth\OAuth2\OAuth2Credential;

$credential = new OAuth2Credential(
    tokens: $tokens,
    identity: $connectionId,
    onTokensChanged: $saveTokens,
);
$auth = OAuth2Authenticator::authorizationCode($oauth, $credential);
$config = $config->with(auth: $auth);
```

`identity` и `onTokensChanged` необязательны. Без identity она генерируется локально;
при восстановлении подключения используйте стабильный ID записи. `tokens()` возвращает
текущую пару. Один credential можно разделять между совместимыми клиентами в процессе,
даже при разных base URL ресурсного API. Несовместимая конфигурация OAuth отклоняется.
Порядок ключей дополнительных token/authorization parameters не меняет identity
credential или attempt; изменение значений параметров по-прежнему меняет её.

После refresh SDK принимает новую пару и вызывает callback **до ресурсного HTTP**.
Отсутствие refresh token в ответе сохраняет прежний. Если сохранение бросило исключение,
новая пара остаётся в памяти, а дальнейшие запросы блокируются. Исправьте хранилище
и вызовите `retryPersistence()`: повторится сохранение этой пары, без нового refresh.
Возвращённый Promise считается незавершённым сохранением; блокирующее I/O приложения
не становится асинхронным. Другие возвращаемые значения не определяют успех;
сообщайте о неуспешной записи исключением. Не запускайте вложенные операции с тем же
credential из этого callback.

Ручные `replaceAuthorization()` и `retryPersistence()` удерживают локальное владение
до завершения callback. Конкурирующий ручной вызов во время refresh или сохранения
бросает исключение конфигурации до изменения токенов. При ошибке владение тоже
освобождается; pending-пара остаётся для следующей попытки сохранения.

`failureReason()` сообщает о блокировке. `invalid_grant` при refresh либо отсутствие
refresh token при необходимом обновлении переводят credential в необходимость
авторизации. Неопределённый исход refresh, включая cancel/deadline после возможной
отправки, блокирует автоматическое повторение старого секрета. Достоверный `NotSent`
допускает следующую самостоятельную попытку без скрытого retry. Явно установите новое
разрешение через `replaceAuthorization($tokens)` либо загрузите новый credential после
восстановления приложением. Неверный начальный code exchange не меняет другой credential.

## Формат хранения и семантика токенов <a id="storage"></a>

`AuthorizationAttempt::export()/restore()` и `OAuth2TokenSet::export()/restore()` используют
схему `version: 1`, массивы и скаляры, пригодные для JSON round-trip. Они содержат секреты:
защитите серверное хранилище и доступ к резервным копиям. Client secret, callback, clock
и объект клиента в снимок не входят. Это отдельный формат, не DTO mapping через
`from()/toArray()`. Неверная схема или типы дают configuration error без исходных значений.
Восстановление сохраняет абсолютный срок, не оживляет attempt и не продлевает токен.
Снимок токенов не содержит identity, callback и состояние блокировки credential.
Стабильный ID подключения и состояние восстановления хранит приложение; загрузка
старого снимка не должна обходить записанный неопределённый исход refresh.

Поля токенов: `accessToken`, nullable `refreshToken`, `expiresAt`, `refreshAt`, `scopes`.
Ответ должен быть JSON с непустым access token и типом Bearer. Неверный JSON,
null/пустой документ и `error` при 200 завершаются отказом. `expires_in` должен быть
положительным целым без переполнения; ноль даёт ошибку непригодного токена.
Числовые строки вроде `"3600"` отклоняются без преобразования; провайдеру с таким
`expires_in` нужна [собственная стратегия аутентификации](tokens.md).
Без expiry срок остаётся неизвестным. Запас обновления — не более 30 секунд и десятой части срока.

Scopes — множества с учётом регистра; порядок и дубликаты не меняют cache identity.
Отсутствующий scope в ответе наследует известный запрошенный набор. Обмен code использует
scopes attempt. Refresh передаёт известные effective scopes; отсутствие поля в ответе
сохраняет их. Явный новый набор заменяет прежний. `null` означает неизвестность,
`[]` — известный пустой набор. Ротация при прежних правах сохраняет identity кеша
ресурсных ответов; изменение прав меняет ключ. Изоляция HTTP cache между SDK, origin
и tenant сохраняется.

## Повторы, дедлайны и диагностика <a id="execution"></a>

Client Credentials использует настроенный ordinary retry, включая backoff и Retry-After;
без настройки выполняется одна попытка. Внешний auth-цикл запускается один раз:
`authRetryAttempts: 3` и ordinary `attempts: 3` дают максимум **три**, а не девять HTTP-попыток.
`authRetryAttempts: 0` отключает автоматическое получение и refresh, но не явный
`send($flow->exchange(...))`.

Code exchange и refresh делают не более одной HTTP-попытки. Общая настройка retry не
разрешает повторение. Прямой `$request->withRetry(3)` даёт configuration error до HTTP;
`withRetry(1)` и `withoutRetry()` допустимы. Custom auth только с `AuthenticatorInterface`
использует `authRetryAttempts` для управления внешним retry-циклом. Авторы custom auth с одноразовыми секретами должны ограничивать **и**
`authRetryAttempts`, **и** ordinary retry token request.

Token requests используют изолированный полный URL, form POST и JSON response, без
redirects, API auth, response cache, credentials enrichment, request enrichers и
continuation mapping. Таймауты, квоты, задержки, дедлайны и dependency trace сохраняются.
Cancel/deadline ожидающего не отменяет владельца refresh. Локальное владение длится
до завершения, ошибки или отмены; TTL не заменяет живого владельца.

`exchange()` проверяет callback сразу и может бросить `OAuth2Exception` с
`reason: OAuth2FailureReason::InvalidCallback` до вызова `send()`. Перехватывайте
его в обработчике callback: результата или промиса ещё нет, а `throwOnErrors: false`
не подавляет это исключение. Неверные аргументы создания/restore дают прямой
`ConfigurationException`. Ручная замена credential и повтор сохранения при ошибке
также бросают исключения напрямую.

Внутри `send()`/`sendAsync()` действуют обычные результаты, `throwOnErrors` и
exception factories. Ошибки credential используют `execution_error`; проверяйте
`$handle->raw()->errors->first()?->context['reason'] ?? null` или `failureReason()`:

| Причина | Действие приложения |
| --- | --- |
| `oauth2_authorization_required` | Получить новое разрешение; повтор ресурсного запроса его не получит. |
| `oauth2_refresh_outcome_unknown` | Восстановить подключение либо авторизоваться заново; не повторять вслепую старый refresh token. |
| `oauth2_token_persistence_failed` | Исправить хранилище, вызвать `retryPersistence()` на том же credential, затем повторить ресурсный запрос. |

Успешный `retryPersistence()` только сохраняет новую пару, оставшуюся в памяти;
он не возобновляет упавший ресурсный запрос. В async готовый FAILED handle отличается
от отклонённого промиса; см. [выдачу ошибок](../results/promises.md#errors).
HTTP, decoding, hydration и deadline сохраняют обычные категории.
Для успешных HTTP-ответов token endpoint отсутствующий/не-JSON Content-Type или
невалидный JSON дают `response_decoding_error`, неверные поля токена — `hydration_error`.

Token requests объявляют чувствительные поля для логов, request snapshots и recordings;
обычное поле `code` другого запроса не затрагивается. `SensitiveFieldsProviderInterface`
позволяет объявлять [локальные секретные поля](../results/observability.md#section-8). `#[SkipContinuation]`
исключает служебный запрос из continuation mapping клиента. Сырые response bodies,
`tokens()->export()`, DTO output и `requestDebug(false)` намеренно содержат исходные данные;
это не безопасные диагностические экспорты. Не логируйте raw objects и снимки хранения.

## Несколько процессов и одно OAuth-разрешение <a id="workers"></a>

Например, два CLI-скрипта или два HTTP-запроса в разных PHP-FPM-процессах могут загрузить
токены одного подключения. Каждый процесс создаёт собственный объект `OAuth2Credential`,
и оба могут одновременно попытаться обновить токены.

SDK координирует обновление при использовании общего объекта credential внутри одного
процесса. Координация между процессами принадлежит приложению: оно должно обеспечить
одного писателя для подключения:

1. Захватить право владения записью credential на стороне приложения.
2. Загрузить текущие токены **после** захвата.
3. Выполнить SDK-операцию, синхронно сохраняя ротации до продолжения.
4. Освободить владение после завершения операции и сохранения.

Этот простой рецепт последовательно исполняет ресурсные операции одного подключения,
снижая пропускную способность. Разные подключения независимы. Истекающая блокировка
с большим TTL не доказывает владение: потеря lease и падение после удалённой ротации,
но до локального сохранения требуют восстановления приложения или durable coordination.
SDK не предоставляет распределённый token vault и не обещает crash-safe rotation.
См. [рецепт Laravel](https://github.com/apisutra/laravel/blob/master/docs/ru/guides/integration/oauth2.md).

Неподдерживаемые протоколы — DPoP, mTLS, private_key_jwt, нестандартные token documents,
провайдеры без PKCE — требуют custom auth. Grant plugins и отключение PKCE не добавлены.
[Обзор аутентификации](README.md).

Запустите [локальный пример](../../../example/oauth2/run.php) командой `php docs/example/oauth2/run.php`
из корня пакета. Он показывает переиспользование Client Credentials, async-обмен code,
отказ callback, refresh после 401 и сохранение ротированной пары без сети.

## Managed authentication <a id="managed-auth"></a>

`ManagedTokenAuthenticatorInterface` расширяет возможности `AuthenticatorInterface`
и необязателен для пользовательской авторизации. `bind(AuthBindingContext)` создаёт scoped state
с `clock`, `cache` и непрозрачной `identity`; `bindingIdentity()` стабильна при ротации.
`reloadToken()` и `tokenVersion()` позволяют coordinator увидеть обновление другого
владельца. `canRefresh()` проверяет доступность без создания запроса;
`getRefreshRequest()` вызывается после захвата владения и reload. `refreshAttempts()`
ограничивает внешний auth-цикл, `refreshLockProvider()` позволяет координировать владение
самим credential, `refreshFailed()` получает transmission state и ответ зависимости.
Эти возможности используют TokenAuthenticator и OAuth2Authenticator; custom auth может
реализовать только `AuthenticatorInterface` с [базовым lifecycle токенов](tokens.md). Token sets и attempts не выполняют HTTP или I/O хранилища.

Refresh-зависимость исполняется выбранным клиентом без регистрации namespace её
класса запроса. Request и execution-обёртка одинаково сохраняют явные auth-опции;
без override auth отключён, пагинация всегда single. Хуки видят исполняющий клиент
через `getClient()`, постоянная привязка запроса не меняется. Ошибки принятия токенов
сохраняют trace зависимости и локализацию клиента; успешный token HTTP остаётся
успешным во вложенной истории, даже если последующее сохранение не удалось.
