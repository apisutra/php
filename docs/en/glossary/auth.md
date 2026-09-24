<!-- languages --> <a href="auth.md">English</a> · <a href="../../ru/glossary/auth.md">Русский</a> <!-- /languages -->
# Authentication <a id="section-1"></a>

| Term | Meaning | Details |
| --- | --- | --- |
| <a id="authenticatorinterface"></a> AuthenticatorInterface | The authentication strategy interface. | [Contract](../reference/auth/strategies.md) |
| <a id="apikeyauthenticator"></a> ApiKeyAuthenticator | A built-in API key implementation. | [Contract](../reference/auth/strategies.md) |
| <a id="basicauthenticator"></a> BasicAuthenticator | A built-in Basic Auth implementation. | [Contract](../reference/auth/strategies.md) |
| <a id="bearerauthenticator"></a> BearerAuthenticator | A built-in implementation for a static Bearer token. | [Contract](../reference/auth/strategies.md) |
| <a id="authorizationschemeauthenticator"></a> AuthorizationSchemeAuthenticator | A built-in implementation for `Authorization: Scheme key="value", ...` schemes. | [Contract](../reference/auth/strategies.md) |
| <a id="authorizationparamsproviderinterface"></a> AuthorizationParamsProviderInterface | A contract for generating `Authorization` parameters from PreparedRequest. | [Contract](../reference/auth/strategies.md) |
| <a id="authorizationparamsformatterinterface"></a> AuthorizationParamsFormatterInterface | A contract for formatting `Authorization` parameters (quoted/comma, query-like, etc.). | [Contract](../reference/auth/strategies.md) |
| <a id="tokenauthenticator"></a> TokenAuthenticator | An implementation for dynamic tokens (refresh based on lifetime). | [Contract](../reference/auth/tokens.md) |
| <a id="hmacauthenticator"></a> HmacAuthenticator | An implementation of HMAC request signing. | [Contract](../reference/auth/strategies.md) |
| <a id="noauth-attribute"></a> NoAuth (attribute) | An attribute for public endpoints. | [Contract](../reference/auth/strategies.md) |
| <a id="withoutauth"></a> withoutAuth() | An AbstractRequest method. | [Contract](../reference/auth/strategies.md) |
| <a id="cacheawareinterface"></a> CacheAwareInterface | A marker interface for authenticators that need a cache. | [Contract](../reference/auth/tokens.md) |

[All terms](README.md).
