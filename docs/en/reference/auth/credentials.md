<!-- languages --> <a href="credentials.md">English</a> · <a href="../../../ru/reference/auth/credentials.md">Русский</a> <!-- /languages -->
# Credentials, origin, and request enrichment <a id="section-1"></a>

## Boundary: authentication vs. credentials enrichment <a id="section-2"></a>
`auth`/`authScopes` handle only authentication (usually `headers`/`query` through an authenticator).
If a provider requires service credentials in the payload (`body`/`multipart form`), use
`credentialsConfig` (the built-in `CredentialsEnricher`), rather than `AuthenticatorInterface`.

A typical setup:
- `authScopes` selects the authentication method.
- `credentialsConfig.scopes` adds provider fields to request parts.
- The scope usually matches (`#[AuthScope(ProviderScope::System)]`), but you can
  override it separately with `withCredentialsScope(...)` when needed.

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

An optional setting for exceptions to credentials isolation. Without it, only the
origin of `ClientConfig.baseUrl` is trusted automatically; `withBaseUrl()` does not change this boundary.

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

The list contains exact origins: scheme, host, and optional port. Do not include a
path, query, userinfo, wildcard, or trailing slash. Permission alone does not enable
authentication: the execution must use `withAuth()`/`withAuthScope()`.
A complete URL requires explicit authentication selection even on the same origin;
query authentication is forbidden.

`withCredentialsEnrichment(true)` and `withRequestEnrichers(true)` also check the origin.
See the [external URL guide](../serialization/uri-query.md) for all rules and restrictions.

## Identity for the HTTP cache <a id="section-4"></a>

Built-in authenticators supply identity automatically: account isolation does not
require a `prefix`. A custom authenticator can implement `CacheIdentityProviderInterface`;
without it, the HTTP cache is skipped. Obtaining identity does not authenticate or
refresh credentials. A separate provider tenant must be declared additionally.
See [cache configuration](../execution/cache.md) for the contract and restrictions.

## Authentication without extra configuration <a id="section-5"></a>

For a complete URL, the client's `auth`/`auth` scopes, `credentialsConfig`, and shared
`requestEnrichers` are not applied automatically, even on the same origin.
Explicit request fields, files, and runtime `headers` are treated as data for that destination.

For a relative endpoint, existing authentication is preserved on the origin of the
original `ClientConfig.baseUrl`. If `withBaseUrl()` changes the origin, automatic
inheritance of `auth`/credentials/enrichers is disabled. Origin consists of the scheme,
host, and effective port; host case and an explicit default port do not change it,
but a subdomain or another scheme does.

Exceptions require the optional [OriginPolicy](credentials.md#section-3) and explicit
authentication selection: `withAuth()`, `withAuthScope()`, `forceAuth()`, or `forceAuthScope()`.
`forceAuth()` does not override origin policy. An allowed origin does not itself enable
authentication. The same origin needs no separate permission, but a complete URL still
requires explicit authentication selection.

For explicit enrichment, use `withCredentialsEnrichment(true)` and, separately,
`withRequestEnrichers(true)`. These also require permission on another origin.
`withRequestEnrichers(false)` disables shared enrichers for any execution.
This does not lift the prohibition on modifying a complete URL's query. When `auth` is
disabled, a 401 does not refresh the original account. Allowed `auth` retains its scope and deadline.
