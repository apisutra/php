<!-- languages --> <a href="cache.md">English</a> · <a href="../../../ru/reference/execution/cache.md">Русский</a> <!-- /languages -->
# HTTP response cache <a id="section-1"></a>

ApiSutra caches successful responses as an application cache with TTL. Storage uses
PSR-16; HTTP revalidation, `Vary`, and `Cache-Control` rules are not applied automatically.
Enable caching only where reusing a response is acceptable.

## Setup without a prefix <a id="section-2"></a>

For an ordinary client, supplying a PSR-16 store is sufficient. The SDK determines
the cache namespace automatically; no `prefix` is required.

```php
use ApiSutra\Auth\BearerAuthenticator;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;

// $store is a configured PSR-16 store; $token holds connection credentials.
$config = new ClientConfig(
    baseUrl: 'https://api.example',
    auth: new BearerAuthenticator($token),
    cacheConfig: new CacheConfig(store: $store, ttl: 60),
);
```

`cacheConfig: new CacheConfig(store: $store)` enables caching for permitted operations.
The block combines the store and settings: `ttl=3600`, `prefix=''`, `mode=Enabled`,
`identity=null`, `locks=null`, `store=null`. Without a store, caching is not attached.
HTTP and shared auth caching use the store from the same block, with separate keys
and identity rules. ClientConfig has no other cache-connection argument.

## Copying and disabling <a id="section-3"></a>

`ClientConfig::with(cacheConfig: ...)` replaces the **entire** block.
Use `CacheConfig::with()` to change individual fields:

```php
$cache = new CacheConfig(store: $store, ttl: 60);
$config = new ClientConfig(baseUrl: 'https://api.example', cacheConfig: $cache);
$short = $config->with(cacheConfig: $cache->with(ttl: 10));
$detached = $config->with(cacheConfig: $cache->with(store: null));
$removed = $config->with(cacheConfig: null);
```

| Change | Resulting configuration |
| --- | --- |
| `$config->with()`, `$config->with(timeout: 7)` | Preserves the same block and all dependencies |
| `$config->with(cacheConfig: $replacement)` | Replaces the entire block; the old store and settings are not inherited |
| `$config->with(cacheConfig: null)` | Removes the shared store and settings, including explicit locks |
| `$cache->with(ttl: 10)` | Changes only TTL; preserves the store and other fields |
| `$cache->with(store: $otherStore)` | Replaces only the backend |
| `$cache->with(store: null)` | Disconnects the shared backend; preserves settings and explicit locks |
| `$cache->with(identity: null, locks: null)` | Resets only identity and locks; preserves the store |

`CacheConfig::with()` returns a new block even without changes. Omitted fields are
preserved; explicit null is accepted only for store/identity/locks. Null is invalid
for ttl, prefix, and mode. Unknown names produce PHP Error; invalid types produce
TypeError. Pass overrides by name. Apply the block by passing it to ClientConfig;
the original objects remain unchanged.

Copying does not access the backend, identity, or locks, clear entries, or clone
dependencies. A new TTL applies to new entries. An existing client keeps its own
configuration. Without a store, even `withCache()` cannot reconnect the previous
backend. Replacing the block with `new CacheConfig(ttl: 10)` without a store also disconnects it.

To disable only HTTP caching, retain the store and set `CacheMode::Disabled` on a
block copy, or use `withoutCache()` for one execution. Auth keeps using the store;
explicit HTTP overrides retain their precedence over Disabled.
`CacheConfig::with(store: null)` preserves explicit locks, which may use their own
backend. Removing the entire block removes locks too; separate rate-limit and custom
extension stores are unchanged.

## Cache namespace <a id="section-4"></a>

The namespace includes the SDK client class, `baseUrl`, the actually selected auth
identity, and the SDK-declared tenant context. Identical connections share cache
across client instances and processes; different credentials are isolated automatically.
Auth scope names (`default`, `secondary`) are not identities by themselves.
`AuthScope`, runtime auth options, `NoAuth`, and auth policy are considered. Anonymous
requests have a separate namespace and also work without a prefix.

Built-in Bearer, Basic, API key, HMAC, and Token authenticators supply identity
automatically. `AuthorizationSchemeAuthenticator` supports a token and static scalar
params with the standard formatter. With a dynamic params provider, custom formatter,
or `Stringable` params, caching is skipped: these cannot provide reliable identity
without executing user code.

Changing credentials creates another namespace. An actual Bearer token change,
including refresh, creates a different key variant; cache hits across tokens are
not guaranteed. The HTTP cache neither replaces nor changes auth token storage.

### Additional separation <a id="section-5"></a>

`prefix` is an optional nonsecret label for further separation of an already isolated
cache, for example `prefix: 'preview'`. The same prefix across different identities
does not combine their data. Do not put tokens or passwords in it.

Override this extra label for one execution:

```php
$execution = $request->withCacheScope('preview');
$result = $execution->send();
$execution->clearCache();
```

`withCacheScope()` returns a new execution copy; the original request is unchanged.
An empty runtime scope is a configuration error. The override replaces only the prefix;
automatic provider/auth/tenant boundaries remain intact.

### Tenant context and custom authentication: SDK authors <a id="section-6"></a>

The core cannot infer that an arbitrary URL, body, or header field represents a tenant.
When one credential serves multiple organizations, the provider SDK declares this
context through `CacheIdentityProviderInterface`. Users of an existing SDK keep
passing ordinary credentials and tenant values, without manual cache prefixes.

The contract defines `getCacheIdentity(?PreparedRequest $request = null): ?string`.
The method performs no HTTP, refresh, store writes, or other side effects. It returns
a stable nonsecret identifier or opaque fingerprint. Different tenants and access
rights must have different values. `null` or an empty string means identity is
unknown and caching must be skipped. Without `$request`, it returns a stable
connection/group context for clearing. With `$request`, it considers actual
tenants/credentials after auth and hooks for a custom key; ordinary operation fields
need not be included. `CacheCredentialIdentity::forRequest()` helps account for
selected headers case-insensitively and a repeated query parameter.

The contract can be used in three places:

- A custom authenticator implements it alongside `AuthenticatorInterface`.
  Without the contract, HTTP caching is skipped even with an explicit prefix.
- `CacheConfig::identity` accepts a connection-context object, such as a DTO with
  a tenant ID. It augments auth identity and also separates client-wide clearing.
- A request implements the contract when its tenant is selected per request.
  Its identity separates response groups within the connection namespace.

Provider SDK request example, with the organization supplied in a header:

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

For connection context, the SDK similarly passes a contract object through
`new CacheConfig(store: $store, identity: $tenantContext)`. Configured identity does
not replace the mandatory identity of a custom authenticator. If a selected contract
returns an unknown value, the request runs without caching; DEBUG logging reports
`cache_reason=unknown_identity`.

The SDK must represent credentials added by the transport, such as mTLS or a cookie
jar, and tenants from external mutable state through this contract. If the context
is not yet known, return `null` or disable caching.

## Cache permission and modes <a id="section-7"></a>

Global configuration allows caching GET. POST/PUT/PATCH/DELETE require explicit
operation opt-in: `#[Cache]`, `withCache()`, `withCacheReadOnly()`, or
`withCacheWriteOnly()`. `withCacheScope()` only sets the namespace; it does not itself
permit POST caching.

Mode priority: runtime → attribute → configuration.

| Mode | Read response | Write fresh response |
| --- | --- | --- |
| Enabled | Yes | Yes |
| ReadOnly | Yes | No |
| WriteOnly | No | Yes |
| Disabled | No | No |

ReadOnly does not even create internal cache generations. WriteOnly reads internal
generations for correct clearing, but never reads the stored HTTP response.
`withoutCache()` disables reads and writes; `withCache(60)` sets TTL.
Calling `withCache()` without a new TTL preserves the previously set runtime TTL.

Error responses are not stored. A cache hit does not extend response TTL.
File uploads/downloads automatically skip global caching. Explicit active
`withCache()`/`#[Cache]` on a file operation causes `configuration_error` before
auth/HTTP, even without a cache store; runtime `withoutCache()` removes the conflict.
This includes Base64 `FileInput`. See [files](../../guides/recipes/files.md).

## Response identity <a id="section-8"></a>

The automatic key includes the HTTP method, actual URI after auth/hooks, body, and
all headers. Query/list order and repeated parameters are preserved; header names
are compared case-insensitively. A token, language, tenant-header, or signature change
creates another response variant. Including all headers may reduce cache hits when
service headers vary.

`#[Cache(key: 'name')]` defines an explicit logical key **inside the automatic
identity/tenant namespace**. It intentionally combines ordinary URI, body, and header
variants. Different origins, authentication, and declared tenants are not combined.
An additional protective fingerprint covers userinfo and known credential headers
and query parameters in the actual prepared request. This is not a universal tenant
detector: the provider declares specific fields through the contract above.

Built-in authenticators also account for their actual credential fields after hooks,
including nonstandard header/query API keys. Changing those values separates custom
cache entries; ordinary URI, body, and header changes do not. A custom authenticator
and tenant contract must similarly inspect the final `PreparedRequest`. If the final
context cannot be determined reliably, the method returns `null` and caching is
skipped (`cache_reason=unknown_identity`).

The provider is responsible for equivalence of grouped requests. The `key` field
does not enable caching against the effective Disabled mode.

Store keys are versioned fixed-length hashes; raw URIs, credentials, prefixes, and
user keys are not included. If auth retry changes the request, the response is not
stored under the old key; diagnostics report `cache_reason=request_changed`.

## Clearing <a id="section-9"></a>

- `$request->clearCache()` and `$execution->clearCache()` invalidate original-request
  variants in the selected namespace. Initial HTTP data and pagination options count;
  traceId, TTL, and access mode do not create a separate group.
- For a custom key, its shared group within the namespace is cleared.
- `$client->clearCache()` invalidates automatic namespaces for default auth, configured
  auth scopes, and anonymous requests on this connection, including request tenant
  groups. It considers the SDK client class, baseUrl, configured tenant, and prefix;
  clearing also works without a prefix. Unknown auth identities are skipped.
- Clients with identical connections also share clearing. To separate tenant clearing
  at client level, the SDK sets `CacheConfig::identity`. Clear runtime namespaces
  through the corresponding execution.

Clearing does not invoke auth refresh or BeforeSend hooks. It changes an opaque
group/namespace generation. An already running request cannot write into the new
generation; old responses physically remain in the backend until their TTL expires.
The store owner explicitly clears the entire backend outside the client API.

The internal namespace generation is stored without TTL; group generations use
`max(60, request TTL)` seconds. Generation expiry or eviction may cause an extra cache
miss but does not restore invalidated responses. PSR-16 does not guarantee atomic
initialization; an extra miss is acceptable during a race. ReadOnly does not recreate
missing generations. Failed generation/response writes use normal error handling;
failed clearing throws a configuration error.

## Shared backend isolation <a id="section-10"></a>

The namespace is determined automatically. Eligible mutating operations require
explicit opt-in. Custom authenticators and SDKs with a separate tenant declare identity.
An optional prefix adds a label within that identity; it does not combine connections.

Explicit `#[Cache(key: ...)]` groups requests within the namespace. The physical
store key is hashed. Reading does not extend TTL, and client clearing does not
remove unrelated entries from a shared backend.

Attribute reference: [behavior attributes](../attributes/behavior.md).

## Auth tokens and optional locking <a id="section-11"></a>

The supplied store also holds tokens, separately from the HTTP response cache.
For built-in TokenAuthenticator, scope is automatic; no prefix is needed.
HTTP settings `withoutCache()`/`CacheMode::Disabled` do not disable token storage.

`CacheConfig::locks` is an optional AuthLockProviderInterface that coordinates refresh
across processes. If omitted, the SDK uses a capability of the selected store or a
local service. All existing CacheConfig arguments remain available.
See the [auth guide](../auth/tokens.md#section-7) for the contract, limitations,
and error handling.
