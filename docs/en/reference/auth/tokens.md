<!-- languages --> <a href="tokens.md">English</a> · <a href="../../../ru/reference/auth/tokens.md">Русский</a> <!-- /languages -->
# Tokens, refresh, and caching <a id="section-1"></a>

## Refresh logic <a id="section-2"></a>
Parameters:
- `authRetryOn401` — retry on 401
- `authRetryAttempts` — number of attempts

## State isolation and waiting for refresh <a id="section-3"></a>

The built-in TokenAuthenticator automatically separates tokens by client
configuration, scope, and declared connection context. No additional prefix/account ID
is required. Without a store, tokens are stored locally, and local locking is selected
without extra parameters. Cross-process guarantees require a supported backend.

If the refresh wait expires, the SDK returns `timeout` without sending the main HTTP
request; an existing overall deadline retains priority. See [token isolation](tokens.md#section-6)
for the full contract.

## TokenAuthenticator + refresh <a id="section-4"></a>
```php
use ApiSutra\Auth\TokenAuthenticator;
use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\DataTransfer\AbstractResponseDto;

#[Post('/auth/token')]
#[Returns(AuthTokenResponseDto::class)]
final class AuthTokenRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public string $username,
        #[Body]
        public string $password,
    ) {}
}

final readonly class AuthTokenResponseDto extends AbstractResponseDto
{
    public function __construct(
        public string $accessToken,
        public int $expiresIn,
    ) {}
}

$config = $config->with(
    auth: new TokenAuthenticator(
        username: 'user',
        password: 'pass',
        tokenTtl: 600,
        refreshRequestClass: AuthTokenRequest::class,
    ),
);
```

For a shared token cache, supply a PSR-16 store through
`cacheConfig: new CacheConfig(store: $store)`. This is the backend also used by the HTTP
cache. `CacheConfig::with(store: null)` disables the shared store and preserves other
fields; `ClientConfig::with(cacheConfig: null)` removes the entire block, including
explicit locks. Entries are not cleared; without a shared store, tokens are stored
locally. See the [full semantics](../execution/cache.md#section-3).

## Refresh and 401 <a id="section-5"></a>
- `AuthenticatorInterface::shouldRefresh()` and `getRefreshRequest()` control token refresh.
- `authRetryOn401` controls automatic recovery after 401 (enabled by default); an explicit ordinary `retryOn: [401]` is independent.
- `authRetryAttempts` sets the number of refresh attempts.

### Token isolation without extra settings <a id="section-6"></a>

A normal `ClientConfig(baseUrl: ..., auth: new TokenAuthenticator(...))` needs no new
parameters. Without a cache store, the token is stored locally within the client.
With a PSR-16 store, the SDK automatically separates tokens by client class, configured
base URL (including its base path), authenticator identity, selected auth scope, and
declared `CacheConfig::identity`, if present. The built-in TokenAuthenticator's identity
includes username, password, and refreshRequestClass.

No prefix, account ID, or lock key is mandatory. The SDK does not introduce external
API account entities: it separates authentication configurations already supplied.
The specific resource URL and current access token value do not change the refresh
scope. Origin policy separately determines where credentials may be sent.

Physical token/lock keys are separate, versioned 64-character digests, without
plaintext usernames, credentials, or URLs. The same declared context produces the
same keys across processes. A digest does not encrypt secrets.

The built-in TokenAuthenticator receives separate token/expiresAt state for each
client and scope binding. Reusing the original auth object in another client does
not transfer its token. A direct `setCache()` call with a different store clears the
old local binding; a cache miss in the same store retains a valid local token.
The SDK uses `freshForContext()`, `loadFromCache()`, and `tokenVersion()` for binding
and reloading; ordinary client code does not need to call them.

### Refresh lock <a id="section-7"></a>

By default, the SDK uses a local lock service without backend configuration. It
operates within the current client/pipeline, not across independent clients or
processes. A PSR-16 cache can store tokens but does not itself guarantee atomic locks.
An additional `add()` method alone is insufficient.

The service is selected automatically in this order:

1. Optional `CacheConfig::locks`, if explicitly set.
2. The selected cache store, if it implements `AuthLockProviderInterface`.
3. The SDK's local service.

For custom backends, the contract is in `ApiSutra\Contracts\Interfaces\Auth`:
`AuthLockProviderInterface::acquire()` returns `AuthLockLeaseInterface`, or null if
the lock is held. The provider must atomically acquire an absent/expired record.
`lease->release()` atomically removes only its owner's record; it returns false if
ownership has been lost. A non-atomic `get → compare → delete` sequence does not satisfy this contract.

Connect a separate backend when coordination across processes is required:

```php
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;

// $lockProvider implements AuthLockProviderInterface for the selected store.
$config = new ClientConfig(
    baseUrl: 'https://api.example',
    cacheConfig: new CacheConfig(store: $tokenStore, locks: $lockProvider),
);
```

This extension is optional. The SDK does not automatically connect Redis or Laravel
and does not promise an adapter for every Laravel lock driver. An explicitly selected
backend's failure does not silently fall back to a local lock.

TTL and maximum wait are derived from `ClientConfig::timeout`, bounded to 5–30 seconds.
The local expiry uses a monotonic clock; waiting uses SleeperInterface and the overall
deadline. The lock protects only within its TTL: lease renewal, fencing, and a guarantee
of a single refresh after TTL expiry are not implemented.

The built-in TokenAuthenticator reloads the store while waiting and after acquiring
the lease. Another owner's valid token avoids an unnecessary refresh. After 401, the
previously rejected token does not count as an update: a different value is required.
If the lock is not acquired and no usable token appears, the result is `timeout` with
reason `auth_refresh_lock_timeout`, stage `auth_lock_wait`; the main HTTP request is
not sent. Exhausting the overall budget preserves `execution_deadline_exceeded` and
the original 401, if already received. General HTTP retry does not retry auth lock errors.

A backend exception produces `execution_error` with reason `auth_lock_backend_error`.
If release fails after a successful refresh, the main HTTP request is not sent. If
refresh/deadline has already failed, a release error does not replace the primary
reason; only a safe description of the secondary error is passed to the logger.

## Token caching <a id="section-8"></a>

CacheAwareInterface receives an isolated PSR-16 view, not the original shared store.
`getCacheKey()` defines a logical key; the view maps it to the physical scope,
including bulk operations. `clear()` is rejected to avoid clearing others' data;
use `delete()`/`deleteMultiple()` for your logical keys.

For a shared token store, a custom authenticator declares stable identity through
CacheIdentityProviderInterface. If identity is absent or connection identity is
undefined, the SDK gives this authenticator local storage within the client. DEBUG
logging exposes reason `auth_cache_identity_unavailable`. Do not configure a dummy
prefix in place of identity.

Arbitrary mutable state in a custom authenticator remains its author's responsibility.
The SDK does not implicitly clone these objects or assume that every `setCache()`
reloads a token. Automatic memory separation and avoiding a repeated refresh after
waiting are provided for the built-in TokenAuthenticator.

### Custom token storage <a id="section-9"></a>

Custom authenticators receive scoped cache access. Without a stable identity,
they receive local storage. Shared locking requires the atomic lock contract;
a backend's `add()` method alone does not provide this contract.

A token manually set on an unbound TokenAuthenticator is not copied into pipeline
bindings. Direct use without a client separates credentials, but cannot include
a base URL unknown to the authenticator.

## Authentication and refresh budget <a id="section-10"></a>

When `RetryConfig::totalTimeoutMs` is set, initial authentication, refresh lock waiting,
and refresh after 401 use the parent's deadline. A child request does not get a new
full budget. On exhaustion, `ExecutionDeadlineException` is preserved and another
refresh attempt does not start. See [Timeouts & Delay](../execution/deadlines.md).

## Recovery after 401 <a id="section-11"></a>

Automatic auth retry requires an available, successful authentication refresh.
Without auth, with a fixed Bearer/API key, or without a refresh request, the SDK
returns the first actual 401. No extra setting is needed. A successful refresh must
return `ResponseDtoInterface` and pass `processTokenResponse()`; an empty response
does not permit a retry. Returning the same token value is allowed.

If refresh fails after 401, the result contains the original 401 with all headers/body,
code `unauthorized`, and `reason=auth_refresh_failed`. `AuthRefreshFailedException`
extends `UnauthorizedException`, so existing catch blocks keep working. Its
`dependencyResult` contains the actual refresh result, such as HTTP 503 or a decoding
error; `recoveryException` contains the recovery failure. These objects are available
for explicit inspection and are not included in automatic safe context. If failure
occurs before refresh is sent, the dependency result may be absent.

For initial refresh before the main HTTP request, the dependency's own error is
returned without an artificial 401. The overall deadline retains `timeout` priority;
lock errors retain their reasons. The SDK does not repeat the main operation after
recovery fails. `client->send(new Request())` and `sendAsync()` use the client actually
executing the operation for refresh, without requiring an earlier `setClient()`.

For token rotation, a custom authenticator supplies `getRefreshRequest()` and
processes the typed response. To use ordinary retries on 401, configure retry policy
with operation and body safety in mind.

## Refresh execution <a id="canonical-refresh"></a>

Refresh requests use the client's executor with Single, inherited parent budget, and
the same outer executor decorator. Their canonical results and response remain in
nested; public exception factories run only when the final error is delivered.
A broken external executor propagates its failure without auth/HTTP retries.

Ready-made OAuth2 grants have their own [retry, persistence and worker contract](oauth2.md). The custom auth outer loop is unchanged: one-time exchanges must restrict both `authRetryAttempts` and the dependency request’s ordinary retry.
