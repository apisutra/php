<!-- languages --> <a href="cooldown.md">English</a> · <a href="../../../ru/reference/execution/cooldown.md">Русский</a> <!-- /languages -->
# Server cooldown after HTTP 429 <a id="overview"></a>

A positive, valid `Retry-After` in an actual HTTP 429 response postpones subsequent
HTTP attempts in the same group. This works without enabling retry or local quotas.
The default group is the request class, isolated by the destination origin and
credentials, within **one client instance by default**. Cache hits remain available; HTTP
already in flight continues. Different request classes are independent unless grouped.

`send()` waits synchronously; `sendAsync()` waits cooperatively. The request that
received 429 is retried only if the ordinary safety and attempt policy allows it.
Cooldown does not make POST, a one-time token exchange, or a non-replayable body safe.

## Configuration and overrides <a id="configuration"></a>

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\CooldownConfig;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;

// No cooldown configuration is required for the default behavior.
$config = new ClientConfig(baseUrl: 'https://api.example');

// Use only if these operations actually share the provider's quota scope.
$config = $config->with(cooldown: new CooldownConfig(
    group: 'reports',
    behavior: RateLimitBehavior::Throw,
));
```

| CooldownConfig field | Default | Meaning |
| --- | --- | --- |
| enabled | true | Observe and respect the server prohibition. |
| group | null | Request class; an explicit group joins classes within the same origin and identity. |
| behavior | Wait | Wait when permitted, otherwise return a local failure; Throw never waits for cooldown. |
| identity | null | Optional opaque connection/tenant ID for custom authentication. Never pass a secret. |
| maxAdditionalWaitMs | null | Automatic limit; an explicit nonnegative integer always applies. |

Empty group/identity and negative or unrepresentable limits are configuration errors.
A full runtime configuration overrides the request attribute and client defaults:

```php
use ApiSutra\Attributes\Behavior\Cooldown;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;

#[Get('/reports')]
#[Cooldown(group: 'reports', maxAdditionalWaitMs: 5000)]
final class ReportsRequest extends AbstractRequest {}

// $client is your configured SDK client. The original request is reusable.
$request = new ReportsRequest();
$result = $client->send($request->withCooldown(new CooldownConfig(enabled: false)));
```

The attribute supports enabled, group, behavior and maxAdditionalWaitMs. Omitted/null
attribute fields inherit the client's value. In CooldownConfig, omitted/null
maxAdditionalWaitMs means **automatic**. To restore auto over an attribute or client
limit, pass a full runtime CooldownConfig; its other fields also become the rule.
RequestOptions::withCooldown() provides the same option. Copying with-methods preserve it.

`withoutRetry()` does not disable cooldown. `withoutRateLimit()` disables quotas only.
Disabling cooldown does not disable the current retry's own Retry-After or backoff.

## How long will it wait? <a id="waiting"></a>

The full server prohibition is retained. The SDK never shortens it to fit a local limit.

| Configuration | Limit |
| --- | --- |
| Automatic, finite execution budget | The remaining budget; no extra default cap. |
| Automatic, no execution deadline | At most 1000 ms of additional cooldown waiting per execution. |
| Explicit maxAdditionalWaitMs | That cumulative cap **and** the execution budget, if present. |

A budget can come from RetryConfig::totalTimeoutMs, an external deadline or a parent
execution. `withTimeout()` limits one HTTP attempt and does not replace this budget.
An expired deadline stays an error; it does not activate the fallback limit.

For a new call with a 30-second cooldown, automatic mode waits if 60 seconds remain
in its budget. Without a budget it fails before sleeping. Explicit maxAdditionalWaitMs=1000
also fails with that 60-second budget. Exactly 1000 ms fits the fallback cap.
Zero prohibits additional cooldown waiting.

For an already authorized retry, let R be its remaining own backoff/Retry-After delay
and C the shared cooldown remainder. The SDK waits max(R, C); only max(0, C − R)
counts against the additional cap. Repeated waits and extensions do not reset the
counter. A retry's own Retry-After=2 seconds still works with a 1-second additional cap.
An already authorized hour-long Retry-After without a budget can still wait an hour:
use a total budget to bound all execution time.

If the whole wait cannot fit the budget, the result is the existing timeout with
reason `execution_deadline_exceeded` and stage `cooldown_wait` when cooldown determines
the wait (`retry_wait` for an ordinary retry). If only the additional cap is insufficient,
it is a local cooldown failure. Both decisions happen before sleeping. Throw checks
cancellation/expired budget, then refuses immediately. Scheduling overhead means the cap
is not an exact wall-clock bound on the complete call.

## Errors and observation <a id="errors"></a>

Local denial follows the existing result-first / throwOnErrors / dataOrFail contract:
ErrorCode::RateLimited, reason `server_cooldown_active`, stage `cooldown`, and
CooldownException with retryAfterMs and retryAfter rounded up to seconds. Its response
is null; lastResponse may contain a previous attempt of **this execution**, never another
call's response. This differs from `local_rate_limit_exceeded` and an actual HTTP 429.
No HTTP attempt or span is created for a local denial or wait. Trace identifiers remain
available. Broad retryExceptions settings do not retry the local denial.

Only actual 429 responses with positive seconds or a supported HTTP date publish a
cooldown before AfterResponse hooks. Local storage records it immediately; external storage
publishes only while the budget is live and execution is not cancelled, because publication
requires I/O. Retry and cooldown use the same delay and response timestamp. A recording
failure preserves its primary error even if publication fails; cancellation retains priority. Missing/invalid/zero/past
Retry-After does not create or erase a prohibition. A shorter 429 or a success cannot
shorten it. HTTP 503 still affects the current retry only. Wait and extension diagnostics
contain timing and trace context, without raw groups, credentials or signed URLs.

## Identity and lifetime <a id="identity"></a>

Built-in authenticators use their existing stable identity, including ordinary OAuth2
rotation with unchanged effective scopes. Known credential headers/query fields and
changes made by hooks are also accounted for; request bodies and upload streams are
not read to guess a tenant. Explicit groups never remove origin/credential isolation.
A custom authenticator can provide CacheIdentityProviderInterface (without I/O), or
use an explicit cooldown identity. If neither gives a stable identity, automatic
coordination is skipped with debug reason `cooldown_identity_unavailable`. An identity
provider that throws produces a configuration error. For custom tenant/credential fields,
the application supplies identity; the SDK cannot discover arbitrary secrets.

State belongs to the client and survives fake/record/playback transport replacement.
Without an explicit cooldownBackend, new clients and other processes are independent,
even with a shared Redis quota backend. A shared cooldown backend joins only matching scopes.
There is no mandatory store, timer per group, queue, or new dependency. Expired records
are removed lazily. Cancellation does not remove the group's prohibition.

Admission runs after waits, before quota acquisition, and again after acquisition may
have waited. A late prohibition can leave a permit consumed without HTTP; there is no
refund or reservation guarantee. There is no SDK suspension between the final admission
and entry into the transport. A custom transport owns its subsequent I/O and must
preserve the prepared request's security and timeout guarantees.

## Imports with pool/consume <a id="imports"></a>

Use automatic cooldown with a finite budget for an import that should wait through
server pauses. RetryConfig::totalTimeoutMs starts separately for each element; it is
not the whole import's deadline. To bound the entire import, create one
[external deadline](deadlines.md#section-4) and pass it to every request, including those
not started yet. Expired elements do not send HTTP.

Without a budget, a long cooldown can make successive pool/consume elements fail locally
while the source continues to be consumed. withStopOnFailure() stops new starts after a
failure; it neither waits for the prohibition nor requeues elements. Already started
work follows the [pool contract](pool-consumption.md). The first 429 retains ordinary
retry rules. The application owns requeueing, persistence and checkpoints.

Run the local, network-free example with virtual time:
`php docs/example/cooldown/run.php`. It demonstrates defaults, budgeted waiting and
consume with a shared deadline. See [quotas](rate-limit.md) and [retries](retry.md)
for their independent policies.

## Shared backend <a id="shared-backend"></a>

ClientConfig::cooldownBackend defaults to null. Each client then owns a local backend,
without infrastructure or extra setup. To share state among clients in one process:

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\RateLimiting\Cooldown\Backends\LocalCooldownBackend;

$backend = new LocalCooldownBackend();
$config = new ClientConfig(baseUrl: 'https://api.example', cooldownBackend: $backend);
// Pass this configuration to each client that should share matching cooldown scopes.
```

For separate processes, explicitly connect the [Redis backend](../integrations/redis.md#cooldown).
The backend is a client dependency, separate from CooldownConfig and request overrides.
with() and fake/record/playback preserve the chosen object. Use a local backend in tests
if Redis effects are unwanted; a mocked 429 still publishes to the configured backend.

Sharing requires the same backend storage and the complete scope: origin, authentication
class/identity, actual known credential fields, optional cooldown identity, and group
(or request class). An explicit group does not join unrelated accounts or hosts. One 429
from /reports does not automatically prohibit /users. A common Redis server alone is
insufficient. For OAuth Authorization Code, persist the connection identity alongside tokens:

```php
use ApiSutra\Auth\OAuth2\OAuth2Credential;
use ApiSutra\Auth\OAuth2\OAuth2TokenSet;

// Both values come from the application's server-side credential storage.
$credential = new OAuth2Credential(OAuth2TokenSet::restore($snapshot), identity: $connectionId);
```

Restoring tokens with a newly generated identity creates another scope. Ordinary token
rotation retains the identity; changes to effective scopes retain the existing isolation rules.

Custom backends implement CooldownBackendInterface: remainingMs(key, timeoutMs) returns
nonnegative milliseconds (0 if absent/expired); extend(key, delayMs, timeoutMs) atomically
keeps the larger remaining duration and returns CooldownUpdate(extended, remainingMs).
All durations, including timeoutMs, are milliseconds. Extend accepts a positive duration;
returned extended=true requires a positive remainder. Keys are opaque. Backends must obey
I/O timeouts; the SDK cannot interrupt arbitrary PHP code. They neither sleep for cooldown
nor send HTTP, retry writes, or own traces. Absolute clock values are not exchanged.

Backend failures are fail-closed: execution_error, reason cooldown_backend_error,
stage cooldown_read or cooldown_publish. Read failure performs no HTTP. Publication failure
retains this execution's actual 429 and does not retry HTTP or the uncertain write.
There is no automatic memory fallback or fail-open option. An exhausted total deadline is
timeout / execution_deadline_exceeded at the same stage; waiting uses cooldown_wait.
A secondary publication failure does not replace recording_failed or cancellation.

Safe DEBUG events rate_limit.cooldown_storage report operation, duration_ms, outcome and
trace context. The final read's log is deferred until outside admission. Failed terminal
logs include reason/stage (and retryAfterMs for active cooldown); no raw keys, identities,
Redis exception messages or connection secrets are exported. Debug response is null before
HTTP, or this execution's actual response after publication failure, never another client's.
Storage I/O consumes the execution budget, but is not counted as additional cooldown sleep.

Admission and the provider HTTP call are not a distributed transaction. Another process
may publish after the last read while an already admitted HTTP attempt starts. No refund,
rollback, durable delivery of 429, or cancellation of in-flight HTTP is promised.

For a deliberate return to local state, construct a **new client** using
$config->with(cooldownBackend: null). Its state starts empty; Redis deadlines are not imported.
Existing clients/operations and Redis keys remain as they were. Other Redis dependencies,
such as quotas or authentication, remain enabled. The application applies this operational
choice; it is not a live switch or automatic fallback.

Run `php docs/example/cooldown/shared.php` for two clients, independent scopes and budgeted
waiting without network calls. Redis process commands are in the Redis reference.
