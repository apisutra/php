<!-- languages --> <a href="../../../en/reference/execution/cache.md">English</a> · <a href="cache.md">Русский</a> <!-- /languages -->
# Кеш HTTP-ответов <a id="section-1"></a>

ApiSutra кеширует успешные ответы как application cache с TTL. Хранилище — PSR-16;
правила HTTP revalidation, `Vary` и `Cache-Control` автоматически не применяются.
Разрешайте кеширование только там, где допустимо повторно использовать ответ.

## Настройка без prefix <a id="section-2"></a>

Для обычного клиента достаточно передать PSR-16 store. Пространство кеша SDK
определяет автоматически; `prefix` передавать не нужно.

```php
use ApiSutra\Auth\BearerAuthenticator;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;

// $store — настроенное PSR-16 хранилище; $token — credentials подключения.
$config = new ClientConfig(
    baseUrl: 'https://api.example',
    auth: new BearerAuthenticator($token),
    cacheConfig: new CacheConfig(store: $store, ttl: 60),
);
```

`cacheConfig: new CacheConfig(store: $store)` включает кеширование разрешённых
операций. Блок объединяет store и параметры: `ttl=3600`, `prefix=''`, `mode=Enabled`,
`identity=null`, `locks=null`, `store=null`. Без store кеш не подключается.
HTTP и общий auth-кеш используют store из одного блока, сохраняя отдельные ключи
и правила identity. Другого аргумента подключения кеша у ClientConfig нет.

## Копирование и отключение <a id="section-3"></a>

`ClientConfig::with(cacheConfig: ...)` заменяет блок **целиком**. Для изменения
отдельных полей используйте `CacheConfig::with()`:

```php
$cache = new CacheConfig(store: $store, ttl: 60);
$config = new ClientConfig(baseUrl: 'https://api.example', cacheConfig: $cache);
$short = $config->with(cacheConfig: $cache->with(ttl: 10));
$detached = $config->with(cacheConfig: $cache->with(store: null));
$removed = $config->with(cacheConfig: null);
```

| Изменение | Результат новой конфигурации |
| --- | --- |
| `$config->with()`, `$config->with(timeout: 7)` | Тот же блок и все зависимости сохранены |
| `$config->with(cacheConfig: $replacement)` | Блок заменён целиком; прежний store и параметры не наследуются |
| `$config->with(cacheConfig: null)` | Общий store и параметры, включая явный locks, убраны |
| `$cache->with(ttl: 10)` | Только TTL изменён; store и остальные поля сохранены |
| `$cache->with(store: $otherStore)` | Заменён только backend |
| `$cache->with(store: null)` | Общий backend отключён; параметры и явный locks сохранены |
| `$cache->with(identity: null, locks: null)` | Сброшены только identity и locks; store сохранён |

`CacheConfig::with()` возвращает новый блок даже без изменений. Непереданное поле
сохраняется, явный null устанавливается только для store/identity/locks. Для ttl,
prefix и mode null недопустим. Неизвестные имена дают PHP Error, неверные типы —
TypeError; передавайте overrides по имени. Для применения блока передайте его в
ClientConfig, исходные объекты остаются неизменными.

Копирование не обращается к backend, identity или locks, не очищает записи и не
клонирует зависимости. Новый TTL действует на новые записи. Существующий клиент
продолжает работать со своей конфигурацией. Без store даже `withCache()` не подключит
прежний backend. Замена на `new CacheConfig(ttl: 10)` без store тоже отключит его.

Для отключения только HTTP сохраняйте store и задавайте `CacheMode::Disabled`
через копию блока либо `withoutCache()` для одного выполнения. Auth продолжает
использовать store; явные HTTP overrides сохраняют прежний приоритет над Disabled.
`CacheConfig::with(store: null)` сохраняет явный locks, который может использовать
собственный backend. Полное удаление блока снимает и его; отдельные хранилища
rate-limit и пользовательских расширений не меняются.

## Пространство кеша <a id="section-4"></a>

Пространство включает класс SDK-клиента, `baseUrl`, фактически выбранную auth identity
и объявленный SDK контекст tenant. Одинаковые подключения разделяют кеш между
экземплярами клиента и процессами; разные credentials разделяются автоматически.
Имена auth scopes (`default`, `secondary`) сами по себе не являются identity.
Учитываются `AuthScope`, runtime auth options, `NoAuth` и auth policy. Анонимные
запросы имеют отдельное пространство и также работают без prefix.

Встроенные Bearer, Basic, API key, HMAC и Token authenticators предоставляют identity
автоматически. `AuthorizationSchemeAuthenticator` поддерживает token и статические
скалярные params со стандартным formatter. При динамическом params provider,
пользовательском formatter или `Stringable` params кеш пропускается: такие параметры
не дают достоверной identity без исполнения пользовательского кода.

Изменение credentials создаёт другое пространство. Фактическая смена Bearer-токена,
включая refresh, создаёт другой вариант ключа; сохранение cache hit между токенами
не гарантируется. HTTP-кеш не заменяет и не меняет хранилище auth-токенов.

### Дополнительное разделение <a id="section-5"></a>

`prefix` — необязательная несекретная метка для дополнительного разделения уже
изолированного кеша. Например, `prefix: 'preview'`. Одинаковый prefix у разных
identity не объединяет их данные. Токены и пароли в него не передавайте.

Для одного исполнения можно заменить эту дополнительную метку:

```php
$execution = $request->withCacheScope('preview');
$result = $execution->send();
$execution->clearCache();
```

`withCacheScope()` возвращает новую execution-копию; исходный request не меняется.
Пустой runtime scope вызывает ошибку конфигурации. Override заменяет только prefix,
а автоматические границы provider/auth/tenant сохраняются.

### Контекст tenant и собственная авторизация: для разработчика SDK <a id="section-6"></a>

Ядро не может угадать, что произвольное поле URL, body или header означает tenant.
Если один credential обслуживает несколько организаций, SDK провайдера описывает
этот контекст через `CacheIdentityProviderInterface`. Пользователь готового SDK
продолжает передавать обычные credentials и tenant, без ручных cache prefixes.

Контракт содержит `getCacheIdentity(?PreparedRequest $request = null): ?string`. Метод не выполняет HTTP, refresh,
запись в store или иные побочные эффекты. Он возвращает стабильный несекретный
идентификатор либо непрозрачный отпечаток. Разным tenant и правам доступа должны
соответствовать разные значения. `null` или пустая строка означают, что identity
не определена и кеширование нужно пропустить. Без `$request` возвращается стабильный
контекст подключения/группы для очистки. При переданном `$request` метод учитывает
фактические tenant/credentials после auth и hooks для custom key; обычные поля
операции включать не нужно. `CacheCredentialIdentity::forRequest()` помогает
учесть выбранные headers без учёта регистра и повторяющийся query-параметр.

Контракт можно применить в трёх местах:

- Собственный authenticator реализует его вместе с `AuthenticatorInterface`.
  Без контракта HTTP-кеш пропускается даже при явном prefix.
- `CacheConfig::identity` принимает объект контекста подключения, например DTO
  с tenant ID. Он дополняет auth identity и разделяет также очистку клиента.
- Request реализует контракт, если tenant выбирается для конкретного запроса.
  Его identity разделяет группы ответов внутри пространства подключения.

Пример запроса SDK провайдера, где организация передаётся заголовком:

```php
use ApiSutra\Attributes\Behavior\Cache;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Header;
use ApiSutra\Auth\CacheCredentialIdentity;
use ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\VO\Http\PreparedRequest;

#[Get('/summary')]
#[Cache(key: 'summary')]
final class TenantSummaryRequest extends AbstractRequest implements CacheIdentityProviderInterface
{
    public function __construct(
        #[Header('X-Tenant-Id')] public readonly string $tenantId,
    ) {}

    public function getCacheIdentity(?PreparedRequest $request = null): ?string
    {
        return CacheCredentialIdentity::forRequest($this->tenantId, $request, ['X-Tenant-Id']);
    }
}
```

Для контекста подключения SDK аналогично передаёт объект контракта в
`new CacheConfig(store: $store, identity: $tenantContext)`. Конфигурационная identity
не заменяет обязательную identity собственного authenticator. Если выбранный
контракт возвращает неопределённое значение, запрос выполняется без кеширования;
при DEBUG-логировании доступна причина `cache_reason=unknown_identity`.

Credentials, добавляемые транспортом (например, mTLS или cookie jar), и tenant
из внешнего изменяемого состояния должны быть представлены SDK в этом контракте.
Если контекст пока неизвестен, возвращайте `null` или отключайте кеширование.

## Разрешение кеширования и режимы <a id="section-7"></a>

Глобальная конфигурация допускает кеширование GET. Для POST/PUT/PATCH/DELETE
нужен явный opt-in на операции: `#[Cache]`, `withCache()`, `withCacheReadOnly()`
или `withCacheWriteOnly()`. `withCacheScope()` задаёт только пространство и сам
по себе не разрешает кеширование POST.

Приоритет режима: runtime → атрибут → конфигурация.

| Режим | Чтение ответа | Запись свежего ответа |
| --- | --- | --- |
| Enabled | Да | Да |
| ReadOnly | Да | Нет |
| WriteOnly | Нет | Да |
| Disabled | Нет | Нет |

ReadOnly не создаёт даже служебные поколения кеша. WriteOnly читает служебные
поколения для корректной очистки, но не читает сохранённый HTTP-ответ.
`withoutCache()` выключает чтение и запись; TTL можно задавать через `withCache(60)`.
Вызов `withCache()` без нового TTL сохраняет ранее установленный runtime TTL.

Ошибочные ответы не записываются. Попадание в кеш не продлевает TTL ответа.
Файловые upload/download автоматически пропускают глобальный кеш. Явный активный
`withCache()`/`#[Cache]` на файловой операции даёт `configuration_error` до auth/HTTP,
даже если cache store не задан; runtime `withoutCache()` снимает конфликт.
Это относится и к `FileInput` в Base64. Подробнее — [файлы](../../guides/recipes/files.md).

## Идентичность ответа <a id="section-8"></a>

Автоматический ключ учитывает HTTP-метод, фактический URI после auth/hooks,
body и все headers. Порядок query и списков, повторяющиеся параметры сохраняются;
имена headers сравниваются без учёта регистра. Изменение токена, языка, tenant header
или подписи создаёт другой вариант ответа. Учёт всех headers может снизить число
cache hits при изменчивых служебных заголовках.

`#[Cache(key: 'name')]` задаёт явный логический ключ **внутри автоматического
пространства identity/tenant**. Он намеренно объединяет обычные варианты URI, тела
и headers. Разные origin, авторизация и объявленные tenant не объединяются.
Дополнительный защитный отпечаток учитывает userinfo и известные credential headers
и query-параметры фактически подготовленного запроса. Это не универсальный детектор
tenant: специфичные поля провайдер объявляет через контракт выше.

Встроенные authenticators дополнительно учитывают свои фактические credential-поля
после hooks, включая нестандартные header/query API key. Изменение таких данных
разделяет custom-кеш; обычные изменения URI, body и headers его не разделяют.
Собственный authenticator и tenant-контракт должны аналогично учитывать итоговый
`PreparedRequest`. Если итоговый контекст нельзя достоверно определить, метод
возвращает `null`, и кеш пропускается (`cache_reason=unknown_identity`).

Провайдер отвечает за эквивалентность объединяемых запросов. Поле `key` не включает
кеширование вопреки итоговому режиму Disabled.

Ключи, передаваемые в store, — версионированные хеши фиксированной длины;
сырые URI, credentials, prefix и пользовательский key в них не записываются.
Если запрос изменился при auth retry, ответ не записывается под прежним ключом;
доступна причина `cache_reason=request_changed`.

## Очистка <a id="section-9"></a>

- `$request->clearCache()` и `$execution->clearCache()` инвалидируют варианты
  исходного запроса в выбранном пространстве. Учитываются начальные HTTP-данные
  и pagination options; traceId, TTL и режим доступа не создают отдельную группу.
- Для custom key очищается его общая группа внутри пространства.
- `$client->clearCache()` инвалидирует автоматические пространства default auth,
  настроенных auth scopes и анонимных запросов этого подключения, включая tenant-группы
  requests. Учитываются класс SDK-клиента, baseUrl, конфигурационный tenant и prefix;
  очистка работает и без prefix. Неизвестные auth identities пропускаются.
- Клиенты с одинаковыми подключениями разделяют также очистку. Чтобы разделять
  очистку tenant на уровне клиента, SDK задаёт `CacheConfig::identity`.
  Runtime-пространства очищайте через соответствующий execution.

Очистка не вызывает auth refresh или BeforeSend hooks. Она меняет непрозрачное
поколение группы/пространства. Уже начатый запрос не может записать ответ в новое
поколение; физически старые ответы остаются в backend до своего TTL.
Полная очистка backend выполняется владельцем store явно, вне API клиента.

Служебное поколение пространства хранится без TTL, поколения групп — с TTL
`max(60, TTL запроса)` секунд. Истечение или вытеснение поколения может привести
к дополнительному cache miss, но не возвращает ранее инвалидированные ответы.
PSR-16 не гарантирует атомарную инициализацию: при гонке допустим лишний miss.
ReadOnly не восстанавливает отсутствующие поколения. Неудача записи поколения
или ответа возвращается через штатный механизм ошибок; очистка при неудаче бросает
ошибку конфигурации.

## Изоляция в общем backend <a id="section-10"></a>

Пространство определяется автоматически. Для поддерживаемых изменяющих операций
требуется явный opt-in. Custom authenticators и SDK с отдельным tenant задают identity.
Необязательный prefix добавляет метку внутри identity, но не объединяет подключения.

Явный `#[Cache(key: ...)]` объединяет запросы внутри пространства. Физический ключ
хранилища хешируется. Чтение не продлевает TTL; очистка клиента не удаляет
несвязанные записи из общего backend.

Справочник атрибутов: [атрибуты поведения](../attributes/behavior.md).

## Auth-токены и необязательная служба блокировок <a id="section-11"></a>

Переданный store также используется для токенов, отдельно от HTTP response cache.
Для встроенного TokenAuthenticator область определяется автоматически; prefix не нужен.
HTTP-настройки `withoutCache()`/`CacheMode::Disabled` не выключают хранение токена.

`CacheConfig::locks` — необязательный AuthLockProviderInterface для координации refresh
между процессами. Если он не передан, SDK использует capability выбранного store
либо локальную службу. CacheConfig поддерживает описанные выше параметры.
Контракт, ограничения, обработка ошибок — в
[руководстве auth](../auth/tokens.md#section-7).
