<!-- languages --> <a href="../../../en/reference/auth/credentials.md">English</a> · <a href="credentials.md">Русский</a> <!-- /languages -->
# Credentials, origin и обогащение запроса <a id="section-1"></a>

## Граница: Auth vs Credentials Enrichment <a id="section-2"></a>
`auth`/`authScopes` отвечают только за авторизацию (обычно `headers`/`query` через authenticator).
Если провайдер требует служебные креды в payload (`body`/`multipart form`), используйте
`credentialsConfig` (встроенный `CredentialsEnricher`), а не `AuthenticatorInterface`.

Типичный сценарий:
- `authScopes` выбирает способ авторизации
- `credentialsConfig.scopes` подмешивает provider‑поля в request parts
- scope обычно совпадает (`#[AuthScope(ProviderScope::System)]`), но при необходимости
  можно переопределить отдельно через `withCredentialsScope(...)`

```php
use ApiSutra\Auth\BearerAuthenticator;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\CredentialsEnrichmentConfig;
use ApiSutra\Config\CredentialsScopeConfig;
use ApiSutra\Enums\Request\CredentialsMergeMode;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    authScopes: [
        'system' => new BearerAuthenticator('system-token'),
    ],
    credentialsConfig: new CredentialsEnrichmentConfig(
        mergeMode: CredentialsMergeMode::FillMissing,
        scopes: [
            'system' => new CredentialsScopeConfig(
                body: ['payload.auth.client_id' => 'client-id'],
                query: ['api_key' => 'api-key'],
            ),
        ],
        secretKeys: ['api_key', 'client_secret'],
    ),
);
```

## OriginPolicy <a id="section-3"></a>

Необязательная настройка для исключений из изоляции credentials. Без неё автоматически
доверяется только origin `ClientConfig.baseUrl`; `withBaseUrl()` не меняет эту границу.

```php
use ApiSutra\Auth\BearerAuthenticator;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\OriginPolicy;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    auth: new BearerAuthenticator('token'),
    originPolicy: new OriginPolicy(allowedOrigins: ['https://storage.example']),
);
```

Список содержит точные origin: схема, host, необязательный порт. Без path, query,
userinfo, wildcard и завершающего slash. Разрешение само по себе не включает auth:
нужен `withAuth()`/`withAuthScope()` на соответствующем исполнении.
Для готового URL явный выбор auth нужен и при совпадении origin, но query auth запрещён.

`withCredentialsEnrichment(true)` и `withRequestEnrichers(true)` также проверяют origin.
Полные правила и ограничения описаны в [гайде внешних URL](../serialization/uri-query.md).

## Identity для HTTP-кеша <a id="section-4"></a>

Встроенные authenticators автоматически предоставляют identity: `prefix` для
изоляции учётных записей не требуется. Собственный authenticator может реализовать
`CacheIdentityProviderInterface`; без него HTTP-кеш пропускается. Метод получения
identity не выполняет авторизацию или refresh. Отдельный tenant провайдера объявляется
дополнительно. Контракт и ограничения описаны в [настройках кеша](../execution/cache.md).

## Авторизация и zero-config <a id="section-5"></a>

Для полного URL автоматически не применяются auth/auth scopes клиента,
`credentialsConfig` и общие `requestEnrichers`, даже на том же origin.
Явные поля запроса, файл и runtime headers считаются данными для данного назначения.

При относительном endpoint текущая авторизация сохраняется на origin исходного
`ClientConfig.baseUrl`. Если `withBaseUrl()` меняет origin, автоматическое наследование
auth/credentials/enrichers выключается. Origin — схема, host и effective port;
регистр host/default port не меняет origin, поддомен и другая схема меняют.

Исключения задаются через необязательную [OriginPolicy](credentials.md#section-3)
и явный выбор auth: `withAuth()`, `withAuthScope()`, `forceAuth()` или `forceAuthScope()`.
`forceAuth()` не отменяет origin policy. Разрешённый origin сам по себе не включает auth.
На том же origin отдельное разрешение не нужно, но полный URL требует явного выбора auth.

Для явного enrichment используйте `withCredentialsEnrichment(true)` и отдельно
`withRequestEnrichers(true)`. На чужом origin они также требуют разрешения.
`withRequestEnrichers(false)` отключает общие enrichers для любого исполнения.
Это не отменяет запрет изменения готового query. При выключенном auth ответ 401
не вызывает refresh исходного аккаунта. У разрешённого auth сохраняются scope и deadline.
