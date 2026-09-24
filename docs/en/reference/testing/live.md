<!-- languages --> <a href="live.md">English</a> · <a href="../../../ru/reference/testing/live.md">Русский</a> <!-- /languages -->
# Live testing tools <a id="section-1"></a>

A guide to organizing tests that make real HTTP requests to a provider API.

Scope:
- These are recommended provider-side patterns, not a strict ApiSutra core contract.
- An SDK may use only part of this guide.
- With a complete provider sandbox and inexpensive smoke scenarios, the live suite
  may be minimal or absent.

## Levels of applicability <a id="section-2"></a>

To distinguish “optional” from “rarely needed”, live patterns fall into four levels:
- Basic minimum: the technical foundation for live checks.
- Recommended by default: quickly pays off for most production SDKs.
- Needed under certain conditions: selected based on API characteristics.
- Operator convenience: improves developer experience and maintenance without defining the SDK contract.

For an SDK built on a real external API rather than for teaching, many of these
patterns are used regularly. Read “optional” below as “outside the core contract”,
not “almost never needed”.

## What the core provides and what the SDK builds <a id="section-3"></a>

### ApiSutra core <a id="section-4"></a>

The core package provides only basic primitives:
- `LiveEnvLoader`: loads `.env.live.local` into the environment.
- `LivePolling::waitUntil()`: a polling helper.
- `LiveResultAssertions::assertSuccess()` and `assertDataInstanceOf()`: framework-agnostic
  assertions for technical success at the result layer and the expected `data()` type.

`assertSuccess()` does not evaluate the response's business meaning. It checks only
that `ResolvedResultInterface` is marked successful at the result layer. SDK authors
assess business validity separately through branded results, custom predicates,
DTOs, metadata, and provider-specific statuses.

### Provider-side layer <a id="section-5"></a>

Everything else in this guide belongs to the architecture of a particular SDK:
- `LiveEnv`, `LiveTestGuard`, `LiveClientFactory`, `LiveFixtureLoader`, `LiveResponseDumper`.
- The `tests/Live/*` structure.
- Live-suite grouping.
- Caching, response dumps, preflight checks, balance checks, Makefile, and operator experience.

None of these is mandatory merely because the SDK uses ApiSutra. In practice, however,
a production SDK with dozens of endpoints, mixed sync/async flows, files, unstable
responses, or expensive requests should generally treat this support layer as
recommended by default.

## ApiSutra helper methods <a id="section-6"></a>

### `LivePolling::waitUntil()` <a id="section-7"></a>

Poll until ready:
```php
use ApiSutra\Testing\LivePolling;

$status = LivePolling::waitUntil(
    fetch: fn () => $client->resource()->checkStatus($id)->withoutCache()->send()->resolved()->data(),
    isReady: fn ($dto) => $dto->status === Status::Ready,
    timeoutSeconds: 30,
    intervalMilliseconds: 1000,
);
```

`->withoutCache()` is especially important when status changes over time.

Between polls, the helper cooperatively waits inside an SDK async task and responds
to its cancellation. Outside an SDK task it sleeps synchronously. The callbacks
themselves must avoid blocking I/O if other tasks need to keep progressing.

### `LiveResultAssertions` <a id="section-8"></a>

```php
use ApiSutra\Testing\LiveResultAssertions;

$resolved = $client->resource()->get($id)->send()->resolved();
LiveResultAssertions::assertSuccess($resolved, 'resource/get');
LiveResultAssertions::assertDataInstanceOf($resolved, UserDto::class, 'resource/get');
$user = $resolved->data();
```

These helpers do not replace provider-specific assertions:
- Check business statuses inside the payload separately.
- If a branded result is needed, it is a convenient place to encapsulate business invariants.

## Caching <a id="section-9"></a>

Caching helps when live requests are expensive, slow, or repeated. A small,
inexpensive smoke suite may not need a separate live cache.

ClientConfig accepts `Psr\SimpleCache\CacheInterface` (PSR-16). For a filesystem
cache, use Symfony Cache (`FilesystemAdapter` + `Psr16Cache`) or any other
PSR-16-compatible implementation.

```php
use ApiSutra\Config\CacheConfig;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

$cacheDir = __DIR__ . '/../.cache';
$cache = new Psr16Cache(new FilesystemAdapter('', 0, $cacheDir));

$client = ProviderClient::make(
    apiKey: LiveEnv::apiKey(),
    transport: $transport,
    cacheConfig: new CacheConfig(store: $cache, ttl: 604800),
);
```

A filesystem cache is often a good provider-side default for production SDKs, but
remains an SDK architecture decision, not an ApiSutra contract.

## Response dumping <a id="section-10"></a>

Response dumping is not always needed. This provider-side pattern is useful when:
- Responses are expensive and need analysis without repeating the call.
- There are file/download scenarios.
- Intermediate identifiers and states are useful in async flows.

For SDKs with sync + async flows, binary responses, or weak external documentation,
response dumps often become a practical diagnostic and reproducibility tool.

One possible approach:
- Save JSON responses as `{resource}/{method}.json`.
- Save file responses as a binary file plus `.meta.json`.
- When needed, use dumps as the state source for subsequent runs.

## Test grouping <a id="section-11"></a>

Grouping live tests makes sense for a sufficiently large suite. A couple of smoke
tests can work without separate groups.

One possible grouping:
- `live-check`: preflight.
- `live-sync`: synchronous methods.
- `live-async`: async/polling flow.
- `live-negative`: error handling.
- `live-laravel`: container smoke test.

## Makefile developer experience <a id="section-12"></a>

A Makefile wrapper is entirely optional. It is convenient for a large live suite
but is not part of the ApiSutra contract.

One possible implementation:
```makefile
PEST = ./vendor/bin/pest
LIVE_ENV = .env.live.local

live-sync:
	@set -a; . $(LIVE_ENV); set +a; $(PEST) --group live-sync

live-one:
	@set -a; . $(LIVE_ENV); set +a; $(PEST) --filter "$(TEST)"
```

## .gitignore <a id="section-13"></a>

Typically useful entries:
```gitignore
.env.live.local
tests/Live/.cache/
tests/Live/Fixtures/live.dataset.php
.live-dumps/
```

## Live testing (real requests) <a id="section-14"></a>

For tests that make real HTTP requests to a provider API, see the separate
[live testing guide](../../guides/testing/live.md).

ApiSutra core provides:
- `LiveEnvLoader`: loads `.env.live.local` without additional dependencies.
- `LivePolling::waitUntil()`: a polling helper for async/live flows.
- `LiveResultAssertions::assertSuccess()` and `assertDataInstanceOf()`: checks for
  technical success at the result layer and the expected `data()` type.

The provider SDK remains responsible for:
- Deciding whether live tests are needed at all.
- Its environment conventions.
- `LiveEnv`, `LiveTestGuard`, `LiveClientFactory`, `LiveFixtureLoader`, `LiveResponseDumper`.
- The `tests/Live` structure, Makefile/run experience, balance preflight, and response dumps.

Practical guidance:
- A small, sandbox-oriented SDK that covers its main risks through mock/unit tests
  may need only minimal live infrastructure.
- For production-like APIs, async/file flows, or expensive requests, plan live
  primitives and a provider-side support layer from the start.
