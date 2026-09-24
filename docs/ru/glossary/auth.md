<!-- languages --> <a href="../../en/glossary/auth.md">English</a> · <a href="auth.md">Русский</a> <!-- /languages -->
# Аутентификация <a id="section-1"></a>

| Термин | Значение | Подробнее |
| --- | --- | --- |
| <a id="authenticatorinterface"></a> AuthenticatorInterface | Интерфейс стратегии аутентификации. | [Контракт](../reference/auth/strategies.md) |
| <a id="apikeyauthenticator"></a> ApiKeyAuthenticator | Готовая реализация для API Key. | [Контракт](../reference/auth/strategies.md) |
| <a id="basicauthenticator"></a> BasicAuthenticator | Готовая реализация для Basic Auth. | [Контракт](../reference/auth/strategies.md) |
| <a id="bearerauthenticator"></a> BearerAuthenticator | Готовая реализация для статичного Bearer токена. | [Контракт](../reference/auth/strategies.md) |
| <a id="authorizationschemeauthenticator"></a> AuthorizationSchemeAuthenticator | Готовая реализация для схем `Authorization: Scheme key="value", ...`. | [Контракт](../reference/auth/strategies.md) |
| <a id="authorizationparamsproviderinterface"></a> AuthorizationParamsProviderInterface | Контракт для генерации параметров `Authorization` на основе PreparedRequest. | [Контракт](../reference/auth/strategies.md) |
| <a id="authorizationparamsformatterinterface"></a> AuthorizationParamsFormatterInterface | Контракт форматтера параметров `Authorization` (quoted/comma, query-like и т.п.). | [Контракт](../reference/auth/strategies.md) |
| <a id="tokenauthenticator"></a> TokenAuthenticator | Реализация для динамических токенов (refresh по сроку жизни). | [Контракт](../reference/auth/tokens.md) |
| <a id="hmacauthenticator"></a> HmacAuthenticator | Реализация HMAC‑подписи запросов. | [Контракт](../reference/auth/strategies.md) |
| <a id="noauth-attribute"></a> NoAuth (атрибут) | Атрибут для публичных endpoints. | [Контракт](../reference/auth/strategies.md) |
| <a id="withoutauth"></a> withoutAuth() | Метод AbstractRequest. | [Контракт](../reference/auth/strategies.md) |
| <a id="cacheawareinterface"></a> CacheAwareInterface | Интерфейс-маркер для authenticator'ов, которым нужен кеш. | [Контракт](../reference/auth/tokens.md) |

[Все термины](README.md).
