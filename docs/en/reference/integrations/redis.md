<!-- languages --> <a href="redis.md">English</a> · <a href="../../../ru/reference/integrations/redis.md">Русский</a> <!-- /languages -->
# Redis for shared quotas and cooldown <a id="section-1"></a>

Use this backend when several workers or SDK instances must share quota accounting.
Ordinary local mode does not require Redis. General limit configuration is covered
in the [rate-limit reference](../execution/rate-limit.md).

## Setup <a id="section-2"></a>

Requires phpredis >= 6.2 and standalone Redis >= 7.0. This adapter does not support
RedisCluster, Predis, Sentinel orchestration, or active-active deployments. The
application installs the extension/server; ext-redis is not a required SDK dependency.

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\RateLimiting\Backends\PhpRedisRateLimitBackend;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379, 1.0);

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    rateLimit: new RateLimitConfig(limit: 100, period: 60),
    rateLimitBackend: new PhpRedisRateLimitBackend(
        redis: $redis,
        scope: 'provider-example/account-42',
    ),
);
```

`scope` is a stable accounting group selected by the application or provider SDK.
Several clients may share one account's quota, while different accounts have separate
quotas. The core does not infer this from URLs/credentials. Do not change the scope
on each request or token rotation. Sharing requires the same server/DB, scope, and
connection Redis prefix; the SDK constructs and hashes the technical quota keys.

## Dedicated connection and timing <a id="section-3"></a>

The connection belongs to the adapter; each worker opens its own. The adapter disables
OPT_MAX_RETRIES and requires normal mode, without MULTI/pipeline, serialization, or
compression. Redis prefixes are supported. Do not pass a connection whose mode,
transaction, or options are concurrently changed by other code. Connection secrets are not logged.

`ioTimeoutMs` defaults to 1000 ms. Before I/O, the adapter reduces the read timeout
to the smaller of its limit and the call's remaining budget. A previously positive
read timeout is restored afterwards. If the initial read timeout is <= 0, the adapter
first sets a finite default on the dedicated connection: restoring zero in phpredis
6.2 can break the next read. Users do not need to implement this workaround.
The application connects before the SDK call; its connect timeout is configured separately.

Prefer Redis close to the application on the network. Network calls and quota waits
count towards RetryConfig.totalTimeoutMs when set; ClientConfig.timeout limits an
HTTP attempt. Without a total budget, waiting for quota may continue, but each Redis
call is bounded. The SDK does not promise completion at an exact millisecond during
process suspension or system delays.

## Guarantees and availability <a id="section-4"></a>

Normally one EVALSHA checks the entire quota set; after NOSCRIPT, EVAL runs the same
constant script. This is safe because NOSCRIPT means no execution took place.
Window timing uses server TIME. Quota rejection changes neither counters nor windows.

Redis becomes an availability dependency: a Redis error stops the call **even when
quota is available**. The SDK does not fall back to memory or repeat an uncertain
deduction. If the response is lost, the command may have executed; HTTP is not sent,
and permits may remain consumed. Retrying the job is an application decision.

Quota keys must not be evicted. A practical option is dedicated Redis with
maxmemory-policy=noeviction and memory monitoring. A separate logical DB does not
protect keys from the server-wide eviction policy. OOM may reject a write; this is
an execution error, not permission to send a request. Clearing data, restarting without
persistence, or replica rollback on failover may reset counters. Strict durability
under every failure is not promised.

Lua validates all states first and writes counters with one MSET, then sets absolute
TTLs. An error after MSET may leave all permits consumed and some TTLs unset; HTTP
is not performed. State contains the window end, but correct memory reclamation is
not guaranteed with broken ACLs. There is no automatic rollback/refund. The application
provides the required permissions and server operations.

## Laravel <a id="section-5"></a>

Use the same adapter when constructing the SDK client in a ServiceProvider. No separate
Laravel adapter or automatic Redis activation is needed:

```php
use ApiSutra\RateLimiting\Backends\PhpRedisRateLimitBackend;
use Illuminate\Support\Facades\Redis;

$backend = new PhpRedisRateLimitBackend(
    redis: Redis::connection('apisutra')->client(),
    scope: $quotaScope,
);
```

`apisutra` is a dedicated named connection in config/database.php using the phpredis
driver. It can use the same suitable Redis server. The application/provider SDK defines
`$quotaScope`; pass the constructed `$backend` to ClientConfig. Connections and backends
do not belong in config files: those contain serializable values, and the container
creates objects after configuration is loaded. This works with config:cache.
See the [Laravel guide](https://github.com/apisutra/laravel/blob/master/docs/en/guides/integration/laravel.md) for general integration.

## Verification <a id="section-6"></a>

Real concurrent workers, lost acknowledgements, ACL failures before/after writing,
OOM, NOSCRIPT, network timeout, and the Laravel recipe are tested separately from
ordinary unit tests. Run `composer test:redis` for the local Redis suite; it creates
and cleans up an isolated environment. Requirements and manual Laravel verification
are in the [testing guide](https://github.com/apisutra/php/blob/master/docs/en/development/testing.md#section-3).
Do not point this environment at production Redis: some scenarios change ACLs,
the script cache, and memory settings on the dedicated test server.

## Shared server cooldown <a id="cooldown"></a>

Quotas and server prohibitions have separate backends. To share 429 Retry-After across
clients/processes, open a **dedicated connection** for cooldown:

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\RateLimiting\Cooldown\Backends\PhpRedisCooldownBackend;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379, 1.0);
$config = new ClientConfig(
    baseUrl: 'https://api.example',
    cooldownBackend: new PhpRedisCooldownBackend(redis: $redis),
);
```

Only the connection is required. scope defaults to 'default'; set it to separate
applications/environments sharing Redis, not to make unrelated endpoints share a ban.
The SDK hashes technical keys automatically. Sharing requires matching server, DB,
connection prefix, scope and the full [cooldown identity](../execution/cooldown.md#identity).
Do not share the mutable connection with the quota adapter or arbitrary application code.

**Cost even without 429:** an ordinary Wait attempt performs two reads, Throw three;
waits add rechecks and a qualifying 429 adds one publication (plus EVAL after NOSCRIPT).
If one read costs 1 ms, this adds about 2–3 ms per attempt; at 5 ms, about 10–15 ms.
These are illustrative calculations, not measured infrastructure latency. For N normal
attempts the cost is 2N/3N reads. Pool(5) with pagination(5) can have 25 active HTTP pages;
it increases the density of reads, not the reads per attempt. phpredis commands remain
synchronous and temporarily block progress of other Fibers in that PHP process.
Cooldown sleeps are cooperative. Measure actual latency in the application's environment.

ioTimeoutMs defaults to 1000, bounded further by the remaining execution budget on every
operation. The connection/timeout restrictions above apply. Read/publish errors stop the
SDK call; there is no local fallback. See the [failure contract](../execution/cooldown.md#shared-backend)
for retained 429, cancellation and deliberate return to local state.

The backend stores a marker with TTL. PTTL reads the remainder; Lua compares it with the
new duration and uses SET PX only to extend. Values and expiry are written together.
No expiry on an existing key is corruption and fails closed. Only NOSCRIPT permits EVAL;
a lost acknowledgement may mean publication succeeded and is never blindly repeated.
Shorter delays and successful HTTP do not clear an active key. Time spent before publication
and NOSCRIPT fallback is deducted; network transit may conservatively lengthen the ban.
No wall-clock or monotonic timestamps are exchanged between PHP processes.

TTL does not remove Redis's clock dependency: expiry is stored against absolute time.
Stable, aligned clocks are required on primary and replicas that may be promoted; time
jumps or failover can change expiry. [Redis expiry behavior](https://redis.io/docs/latest/commands/expire/)
describes persistence and replication. Cooldown keys must not be evicted. A cleared/lost key
looks like no prohibition; noeviction, monitoring and failover/persistence policy belong
to the application. This is transient coordination, not durable event delivery.

With a disposable Redis instance, run in separate processes using the same connection
settings (REDIS_HOST/REDIS_PORT and optional APISUTRA_COOLDOWN_SCOPE):

```bash
php docs/example/cooldown/redis.php publish
php docs/example/cooldown/redis.php read
```

The example mocks provider HTTP; only Redis is real. The second process sees a local denial,
without receiving the first process's response or trace. Keys expire after 30 seconds.
