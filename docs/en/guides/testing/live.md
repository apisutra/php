<!-- languages --> <a href="live.md">English</a> · <a href="../../../ru/guides/testing/live.md">Русский</a> <!-- /languages -->
# Organize SDK live checks <a id="section-1"></a>

## When live tests are needed <a id="section-2"></a>

Unit tests with `MockTransport` check SDK logic: serialization, hydration, and error mapping.
However, they do not cover:
- The provider's actual contract (fields, statuses, error codes).
- Behavior under rate limits, timeouts, and unstable networks.
- Correct async/polling flows with real delays.
- Binary responses (files, archives).

Live tests are especially important when:
- The provider has no sandbox or test environment.
- Requests cost money and spending must be controlled.
- Responses depend on actual data in an external system.

Live tests are usually unnecessary or can be greatly simplified when:
- The provider has a stable sandbox with predictable test data.
- All risky scenarios are already well covered by mock/unit tests.
- The SDK does not handle files, async flows, or expensive endpoints.

## Principles <a id="section-3"></a>

- Strongly recommended: manual execution. Live tests for production-like, paid, or unstable endpoints usually do not run automatically in CI.
- Strongly recommended: environment gating. Without an explicit flag and credentials, tests should preferably skip (`skip`) rather than fail.
- Recommended: minimal configuration. Keep required live-suite settings small.
- Optional: caching. Useful for expensive or repeated scenarios, but unnecessary for every SDK.
- Optional: response dumping. Needed when responses are expensive, large, file-based, or require analysis outside the test.
- Optional: grouping. Useful for large live suites; possibly excessive for a few smoke tests.

## When to enable each feature <a id="section-4"></a>

### Basic minimum <a id="section-5"></a>

- `LiveEnvLoader` is almost always needed if the SDK has live tests at all.
- `LiveResultAssertions` is almost always useful as a technical baseline before business checks.
- `LiveTestGuard` or equivalent skip logic is practically mandatory when live execution depends on environment variables and credentials.

### Recommended by default <a id="section-6"></a>

- `record/playback` is usually worth enabling for paid, slow, rate-limited, or noticeably nondeterministic APIs.
- A provider-side `LiveClientFactory` usually pays off when the live client needs separate timeout/cache/debug settings.
- Grouping the live suite is almost always useful once an SDK has more than a few live scenarios.
- ApiSutra's built-in helpers are especially useful when live tests must be consistent across multiple packages.

### Needed under specific conditions <a id="section-7"></a>

- `LivePolling::waitUntil()` is needed for async jobs, status endpoints, eventual consistency, or delayed result readiness.
- Response dumping is strongly recommended for asynchronous, file-based, poorly documented APIs or when responses need later analysis outside the test.
- A live cache is needed when requests are expensive, slow, limited, or frequently repeated during debugging.
- A negative live suite is needed when errors are a substantial part of SDK DX and cheap, safe scenarios exist.
- Cost/balance preflight checks are needed for paid APIs or sensitive limits.
- A Laravel smoke test makes sense when the package ships a service provider, container bindings, configuration integration, or a facade/DI layer.

### Operator convenience <a id="section-8"></a>

- `LiveFixtureLoader` helps when live scenarios need input datasets reused across tests.
- `LiveResponseDumper` helps teams investigate unstable responses, save binary files, or use dumps as a temporary state source.
- A Makefile or equivalent task runner helps when the suite runs in different groups and is used by people other than the package author.

## Structure <a id="section-9"></a>

One possible provider-side arrangement:

```text
tests/
├── Unit/
├── Integration/
└── Live/
    ├── Sync/
    ├── Async/
    ├── Negative/
    ├── Laravel/
    ├── Fixtures/
    │   ├── live.dataset.example.php
    │   └── live.dataset.php
    ├── Support/
    │   ├── LiveEnv.php
    │   ├── LiveTestGuard.php
    │   ├── LiveClientFactory.php
    │   ├── LiveFixtureLoader.php
    │   └── LiveResponseDumper.php
    └── .cache/
```

This is only an example. Some SDKs need only a couple of files and no separate `Support/` directory.

## Support layer <a id="section-10"></a>

Recommended provider-side classes:

| Class | Purpose |
|-------|---------|
| `LiveEnv` | Reads environment variables through `getenv()`. Typed methods: `apiKey()`, `baseUrl()`, `isEnabled()`. Has no knowledge of files. |
| `LiveTestGuard` | Skip logic: skip the test if the run flag or credentials are not set. |
| `LiveClientFactory` | Creates a client with `HttpTransport`. Add cache, timeouts, and other live settings only when justified for the provider. |
| `LiveFixtureLoader` | Loads input data from `live.dataset.php`. |
| `LivePolling` | **ApiSutra.** `ApiSutra\Testing\LivePolling::waitUntil(callback, isReady, timeout)` for async/polling flows. Calls use `->withoutCache()`. |
| `LiveResultAssertions` | **ApiSutra.** `assertSuccess()` and `assertDataInstanceOf()`. Checks technical success at the result layer and the DTO contract. |
| `LiveResponseDumper` | Optional helper for saving responses to files. |

## Environment convention <a id="section-11"></a>

### Files <a id="section-12"></a>

Usually in the SDK package root:
- `.env.live.example` — a template without secrets.
- `.env.live.local` — real values, listed in `.gitignore`.

### Required variables <a id="section-13"></a>

Keep them minimal:

```env
PROVIDER_LIVE_TESTS=1
PROVIDER_API_KEY=your-key-here
```

Set anything else only when the particular SDK actually needs it.

### Loading <a id="section-14"></a>

ApiSutra provides `LiveEnvLoader`:

```php
// tests/bootstrap.php
require_once __DIR__ . '/../vendor/autoload.php';

\ApiSutra\Testing\LiveEnvLoader::loadForTests(__DIR__);
```

`loadForTests(__DIR__)` looks for `.env.live.local` in the package root. The file is optional.
Existing environment variables are not overwritten.

### Provider-side reader <a id="section-15"></a>

One possible implementation:

```php
final class LiveEnv
{
    public static function isEnabled(): bool
    {
        $value = getenv('PROVIDER_LIVE_TESTS');
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function apiKey(): string
    {
        return (string) getenv('PROVIDER_API_KEY');
    }

    public static function baseUrl(): string
    {
        $url = getenv('PROVIDER_LIVE_BASE_URL');
        return is_string($url) && $url !== '' ? $url : 'https://api.provider.com/v1';
    }
}
```

## Separating data: environment vs fixtures <a id="section-16"></a>

Clear separation:

| What | Where | Example |
|------|-------|---------|
| Credentials (API keys) | `.env.live.local` | `PROVIDER_API_KEY=abc123` |
| Configuration (base URL, flags) | `.env.live.local` | `PROVIDER_LIVE_TESTS=1` |
| Request input data | `live.dataset.php` | IDs, addresses, dates |

Input fixtures in a PHP file are convenient, but this is a provider-side pattern,
not a requirement of ApiSutra core.

```php
// tests/Live/Fixtures/live.dataset.example.php
return [
    'entity_id' => 'EXAMPLE-ID-123',
    'search_query' => 'Пример запроса',
    'date' => '2025-01-15',
];
```

## Negative tests <a id="section-17"></a>

This layer is optional. Add it when:
- The provider offers stable, safe negative scenarios.
- Errors are an important part of SDK DX.
- The cost of these checks is controllable.

Recommendations:
- Use only inexpensive methods.
- Choose safe scenarios: invalid identifier format or empty required fields.
- Do not use a valid format with nonexistent data if the provider might charge for the attempt.
- Check the error type, error code, context, and correct mapping.

## Cost control <a id="section-18"></a>

This section applies only to paid or limited APIs. A free sandbox usually does not need it.

Possible provider-side measures:
- A balance preflight check.
- A cache to reduce repeated charges.
- Displaying the balance delta before/after a run.
- Manually running only deliberately selected scenarios.

## Sandbox and test data <a id="section-19"></a>

Before development, determine:
- Whether the provider has a sandbox/test environment and separate baseUrl.
- Which test keys/credentials are available and how they differ from production.
- Whether the sandbox has rate limits.
- Whether official examples/fixtures/static responses exist.

This helps plan configuration and architecture in advance:
- Separate `ClientConfig` for production/sandbox.
- Prepare a mock/fixture strategy (`MockTransport`, record/playback).
- Provide extension points for tests (hooks/extensions).

See [testing](unit.md).
