<!-- languages --> <a href="oauth2.md">English</a> · <a href="../../../ru/reference/auth/oauth2.md">Русский</a> <!-- /languages -->
# OAuth2 <a id="section-1"></a>

Built-in Client Credentials and Authorization Code with PKCE S256 use the ordinary
ApiSutra execution path: sync `send()`, awaited `sendAsync()`, pool/batch, deadlines,
rate limits, tracing and result error handling. No additional dependency, route,
session, database or cache is required. The application supplies provider endpoints
and credentials; it owns user sessions, storage and coordination between processes.

## Client Credentials <a id="client-credentials"></a>

Set `auth` when constructing your SDK client:

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

The token is obtained before the first resource request, reused and renewed when
needed. Client Credentials uses the existing scoped token cache and refresh locks;
without a store everything is local. Provider/base URL, auth scope and tenant cache
boundaries remain effective. A shared PSR-16 store alone does not make a distributed
lock. See [token storage](tokens.md).

`OAuth2Config` accepts `tokenUrl`, `clientId`, optional `clientSecret`, nullable
`scopes`, `clientAuthentication` and `tokenParameters`. Basic client authentication
is the default and requires a secret. `ClientAuthentication::Post` sends credentials
in the form; `ClientAuthentication::None` explicitly selects a public client without
a secret and is available for Authorization Code, not Client Credentials.
Basic form-encodes the ID and secret before Base64; credentials are not duplicated
in the body. Additional `tokenParameters` may include `audience` or `resource`.
Reserved OAuth fields cannot be overridden.
`tokenParameters` and `authorizationParameters` are string-to-string maps; numbers,
arrays and reserved names such as `scope`, `state`, `code` or `client_id` are rejected.
URLs require HTTPS without userinfo, fragments or reserved OAuth query parameters.

## Authorization Code <a id="authorization-code"></a>

Configure one flow per issuer and callback route. `$oauth` is your `OAuth2Config`;
`$client` is an existing SDK client. Store the attempt on the server, bind it to the
current user's session, and atomically consume it on callback. `export()` includes
`codeVerifier`: do not put it in a cookie, redirect URL, browser storage or logs.

```php
use ApiSutra\Auth\OAuth2\AuthorizationCodeFlow;

$flow = new AuthorizationCodeFlow(
    config: $oauth,
    authorizationUrl: 'https://identity.example.test/authorize',
    redirectUri: 'https://app.example.test/oauth/callback',
    expectedIssuer: 'https://identity.example.test',
);
$attempt = $flow->begin();
$serverSideSnapshot = $attempt->export(); // Save under this session's state; do not send to the browser.
$redirectUrl = $attempt->url;
```

On callback, load and consume that session's attempt before exchanging the code.
`$callbackParameters` contains parsed query or form-post parameters. `$actualCallbackUri`
is the actual callback handler URI **without OAuth response parameters**; it must
exactly match `redirectUri`, including any configured static query.

```php
use ApiSutra\Auth\OAuth2\AuthorizationAttempt;

$attempt = AuthorizationAttempt::restore($serverSideSnapshot);
$request = $flow->exchange($attempt, $callbackParameters, $actualCallbackUri);
$tokens = $client->sendAsync($request)->wait()->dataOrFail(); // OAuth2TokenSet
// Synchronous alternative: $tokens = $client->send($request)->dataOrFail();
```

Each `begin()` generates independent state and verifier. Attempts expire after
600 seconds by default (`attemptTtlSeconds`); optional `clock` supports controlled
time. `authorizationParameters` adds provider options such as `prompt`, while
reserved fields remain protected. SDK endpoints and callback URLs require HTTPS.
State, expiry, flow binding, code, actual callback URI and configured issuer are
checked before token HTTP. User-denied callbacks fail the same validation boundary;
untrusted `error_description` is never used as an exception message.

With `expectedIssuer`, the callback must include the exact RFC 9207 `iss`. Otherwise,
use a separate verified callback route for each issuer in a multi-issuer application.
Binding an attempt to `tokenUrl` alone is not mix-up protection. Array-valued callback
parameters are rejected; the application must reject duplicate parameters before a
framework collapses them into a single value. The SDK cannot recover lost duplicates.
New independent sends of the same exchange request are not an exactly-once guarantee.

## Credential and persistence <a id="credential"></a>

Create one credential for one user connection. Here `$tokens` is the token set from
the exchange, `$connectionId` is a stable application record ID, and `$saveTokens`
is a synchronous application callback that commits `OAuth2TokenSet::export()` or
throws on failure:

Persist the initial exchange result explicitly before creating the credential.
The constructor does not call `onTokensChanged`; the callback handles subsequent
refreshes and `replaceAuthorization()`.

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

`identity` and `onTokensChanged` are optional. A missing identity is generated locally;
when restoring a connection, reuse its stable record ID. `tokens()` exposes the latest
pair. One credential can be shared by compatible clients in a process, even for
different resource base URLs. Incompatible OAuth configuration is rejected.
The order of keys in additional token/authorization parameters does not change the
credential or attempt identity; changed parameter values still do.

After refresh, the SDK accepts the new pair and invokes the callback **before resource
HTTP**. A missing refresh token in the response preserves the previous one. If saving
throws, the new pair stays in memory and further requests fail. Fix storage and call
`retryPersistence()` to save this pair again, without refreshing again. A returned
Promise is rejected as incomplete persistence; blocking application I/O does not
become asynchronous. Callback return values otherwise have no success semantics;
report failed writes by throwing. Do not re-enter the same credential from this callback.

Manual `replaceAuthorization()` and `retryPersistence()` hold local ownership until
the callback completes. A competing manual call during refresh or persistence throws
a configuration exception before changing tokens. Ownership is released on failure too;
the pending pair remains available for a later saving retry.

`failureReason()` reports a blocked state. A refresh `invalid_grant` or unavailable
refresh token when renewal is required leads to authorization required. An uncertain
refresh outcome (including cancellation/deadline after possible transmission) blocks
automatic reuse of the old secret. A proven `NotSent` permits a later independent
attempt, without a hidden retry. Restore a valid authorization explicitly through
`replaceAuthorization($tokens)` or a newly loaded credential after application recovery.
An invalid initial code exchange does not modify another credential.

## Storage format and token semantics <a id="storage"></a>

`AuthorizationAttempt::export()/restore()` and `OAuth2TokenSet::export()/restore()`
use schema `version: 1`, arrays and scalar values, suitable for JSON round trips.
They contain secrets: protect server-side storage and access to backups. No client
secret, callback, clock or client object is included. This format is distinct from
DTO mapping via `from()/toArray()`. Invalid schemas/types fail with a configuration
error without echoing stored values. Restore preserves absolute expiry and does not
revive an expired attempt or extend a token's lifetime.
Token snapshots do not contain the credential's identity, callback or blocked state.
Keep the stable connection ID and recovery state in application storage; restoring
an old token snapshot must not bypass a recorded uncertain refresh outcome.

Token fields: `accessToken`, nullable `refreshToken`, `expiresAt`, `refreshAt`, `scopes`.
The token response must be JSON with a nonempty access token and Bearer token type;
invalid JSON, null/empty documents and `error` in a 200 response fail. `expires_in`
must be a positive integer without overflow; zero produces an unusable-token error.
Numeric strings such as `"3600"` are rejected, not converted; providers returning
`expires_in` this way require a [custom authentication strategy](tokens.md).
Without expiry the lifetime remains unknown. The refresh margin is at most 30 seconds
and one tenth of the reported lifetime.

Scopes are case-sensitive sets; order and duplicates do not change cache identity.
An omitted response scope inherits the known requested set. Code exchange uses the
attempt's scopes. Refresh requests send known effective scopes; an omitted response
scope preserves them. Explicit new scopes replace the set. `null` means unknown,
while `[]` means known empty. Token rotation with unchanged permissions preserves
resource response cache identity; changed permissions change the cache key. HTTP
cache isolation between SDKs, origins and tenants remains intact.

## Retries, deadlines and diagnostics <a id="execution"></a>

Client Credentials uses configured ordinary retry, including backoff and Retry-After;
without retry configuration it makes one attempt. Its outer auth cycle runs once:
`authRetryAttempts: 3` with ordinary `attempts: 3` means at most **three**, not nine
HTTP attempts. `authRetryAttempts: 0` disables automatic acquisition and refresh;
it does not disable an explicit `send($flow->exchange(...))`.

Code exchange and refresh make at most one HTTP attempt. Global retry settings do
not enable repetition. Direct `$request->withRetry(3)` is a configuration error before
HTTP; `withRetry(1)` and `withoutRetry()` are allowed. Custom auth implementing only
`AuthenticatorInterface` uses `authRetryAttempts` to control its outer retry cycle. Authors exchanging a one-time secret through custom
auth must constrain **both** `authRetryAttempts` and the token request's ordinary retry.

Token requests use an isolated full URL, form POST, JSON response, no redirects,
API auth, response cache, credential enrichment, request enrichers or continuation
mapping. Client timeouts, quotas, delays, deadlines and dependency traces still apply.
A waiter cancelling or timing out does not cancel the refresh owner. Local ownership
lasts until completion, failure or cancellation; a TTL cannot replace a live owner.

`exchange()` validates the callback immediately and may throw `OAuth2Exception`
with `reason: OAuth2FailureReason::InvalidCallback` before `send()` is called.
Catch it at the callback boundary; no result or promise exists yet, and
`throwOnErrors: false` cannot suppress it. Invalid construction/restore arguments
throw `ConfigurationException` directly. Manual credential mutation and saving
retries also throw directly on failure.

Inside `send()`/`sendAsync()`, errors use ordinary results, `throwOnErrors` and
exception factories. Credential failures use `execution_error`; inspect
`$handle->raw()->errors->first()?->context['reason'] ?? null` or `failureReason()`:

| Reason | Application action |
| --- | --- |
| `oauth2_authorization_required` | Obtain new authorization; repeating the resource call does not obtain it. |
| `oauth2_refresh_outcome_unknown` | Recover the connection or authorize again; do not blindly reuse the old refresh token. |
| `oauth2_token_persistence_failed` | Fix storage, call `retryPersistence()` on the same credential, then retry the resource operation. |

Successful `retryPersistence()` only saves the retained new pair; it does not resume
the resource request that failed. In async, a fulfilled FAILED handle is distinct
from a rejected promise; see [error delivery](../results/promises.md#errors).
HTTP, decoding, hydration and deadline errors retain their usual categories.
For successful token HTTP responses, missing/non-JSON Content-Type or invalid JSON
produces `response_decoding_error`; invalid token fields produce `hydration_error`.

Token requests declare sensitive fields for logs, request snapshots and recordings;
normal request fields named `code` are unaffected. `SensitiveFieldsProviderInterface`
lets custom requests declare [additional local fields](../results/observability.md#section-8). `#[SkipContinuation]` excludes
a service request from a client's continuation mapping. Raw response bodies,
`tokens()->export()`, DTO output and `requestDebug(false)` deliberately contain original
data; they are not safe diagnostic exports. Do not log raw objects or storage snapshots.

## Multiple processes sharing one OAuth authorization <a id="workers"></a>

For example, two CLI scripts or two HTTP requests in separate PHP-FPM processes may load
tokens for the same connection. Each process creates its own `OAuth2Credential` object,
and both may attempt to refresh the tokens at the same time.

The SDK coordinates refresh when callers share one credential object within a process.
Coordination across processes belongs to the application: it must arrange one writer
for a connection:

1. Acquire application ownership of the credential record.
2. Load its current tokens **after** acquiring ownership.
3. Execute the SDK operation and synchronously save rotations before proceeding.
4. Release ownership after the operation and persistence finish.

This simple recipe serializes resource operations for that connection, reducing
throughput. Different connections remain independent. An expiring lock with a long
TTL is not proof of ownership: lease loss and a crash after remote rotation but before
local persistence need application recovery or durable coordination. The SDK does not
supply a distributed token vault or promise crash-safe rotation. See the
[Laravel recipe](https://github.com/apisutra/laravel/blob/master/docs/en/guides/integration/oauth2.md).

Unsupported protocols (DPoP, mTLS, private_key_jwt, nonstandard token documents or
providers without PKCE) require custom auth. No grant plugin framework or PKCE bypass
is introduced. [Authentication overview](README.md).

Run the [local executable example](../../../example/oauth2/run.php) with `php docs/example/oauth2/run.php`
from the package checkout. It covers Client Credentials reuse, async code exchange,
callback rejection, refresh after 401 and saving the rotated pair without network access.

## Managed authentication <a id="managed-auth"></a>

`ManagedTokenAuthenticatorInterface` extends the capabilities of `AuthenticatorInterface`
and is optional for custom authentication. `bind(AuthBindingContext)` creates scoped state using
`clock`, `cache` and opaque `identity`; `bindingIdentity()` remains stable across token
rotation. `reloadToken()` and `tokenVersion()` allow the coordinator to observe another
owner's update. `canRefresh()` checks availability without constructing a request;
`getRefreshRequest()` is called after acquiring ownership and reloading. `refreshAttempts()`
limits the outer auth cycle, `refreshLockProvider()` optionally supplies credential-owned
coordination, and `refreshFailed()` receives the dependency's transmission state and response.
TokenAuthenticator and OAuth2Authenticator use these capabilities; custom auth can
implement only `AuthenticatorInterface` with the [basic token lifecycle](tokens.md). Token sets and attempts have no execution or storage I/O.

A refresh dependency runs through the selected client executor without namespace
registration for its request class. Requests and execution wrappers preserve explicit
auth options equally; auth is disabled when no override is supplied, and pagination
is always single. Request hooks see the executing client through `getClient()` without
changing the request's permanent binding. Errors while accepting tokens preserve the
dependency trace and client localization; a successful token HTTP stays successful
in the nested history even if subsequent persistence fails.
