<!-- languages --> <a href="mocking.md">English</a> · <a href="../../../ru/reference/testing/mocking.md">Русский</a> <!-- /languages -->
# Fakes and request assertions <a id="section-1"></a>

ApiSutra provides basic testing primitives: `fake`, fixtures, assertion methods,
and several framework-agnostic helper classes.

Scope:
- This guide describes the core package's capabilities.
- Each provider SDK builds its own live-suite architecture: support layer, directories,
  Makefile, dumps, preflight checks, and so on.
- The live-testing strategy depends on the provider and is not mandatory for every SDK.

## Quick fake <a id="section-2"></a>
```php
use ApiSutra\Testing\MockResponse;

$client->fake([
    GetUser::class => MockResponse::success(['id' => 1, 'name' => 'Alice']),
]);

$client->preventStrayRequests();
```

`preventStrayRequests()` requires an active `MockTransport`: call `fake()` or
`playback()` first, or supply the mock transport when constructing the client.
Without it, the method immediately throws `ConfigurationException`; it does not
replace the transport. With a mock, unmatched requests are rejected rather than
receiving the default synthetic 404. Use `fake([])` followed by
`preventStrayRequests()` to reject every request until responses are configured.
`record()` wraps the current transport and is not a fake.

You can mock by URL pattern:
```php
$client->fake([
    'https://api.example/users/*' => MockResponse::success([]),
    '*' => MockResponse::notFound(),
]);
```

Lookup priority: exact request class match first, then URL patterns, then `*` as a fallback.

### Large identifiers in fakes and fixtures <a id="section-3"></a>

To test a large numeric literal, pass raw JSON:
```php
use ApiSutra\Testing\MockResponse;

$response = MockResponse::make('{"id":9223372036854775808}');
```

A PHP float in an array may have lost digits before MockResponse is called.
RecordingTransport, playback, and safe diagnostics preserve the exact digits of
large integers; recorded JSON may represent them as strings. Recording does not
change the raw HTTP response. Secret masking remains active; byte-for-byte equality
between the fixture and original body is not guaranteed.

## File responses <a id="section-4"></a>

For `#[Download]`, use `MockResponse::file('/path/to/fixture.bin')`. Each call opens
a new handle, so closing the previous `FileResponse` does not affect the next attempt
or test. Status and headers are supported, as is using a file response in
`MockResponse::sequence()`.

The recorder does not read upload/download streams to create fixtures. It records
metadata and `bodyOmitted: true`; this record does not contain the full body.
Playback explicitly rejects it and suggests a file fixture instead of returning
a successful empty response. Existing JSON fixtures retain their behavior.

## Response sequences <a id="section-5"></a>
```php
$client->fake([
    GetUser::class => MockResponse::sequence([
        MockResponse::success(['id' => 1]),
        MockResponse::success(['id' => 2]),
    ]),
]);
```

## Dynamic responses <a id="section-6"></a>
A response can be a callable:
```php
$client->fake([
    GetUser::class => function (GetUser $request) {
        return MockResponse::success(['id' => $request->id]);
    },
]);
```

## Assertions <a id="section-7"></a>
```php
$client->assertSent(GetUser::class);
$client->assertNotSent(DeleteUser::class);
$client->assertNothingSent();
```

All three assertions require an active `MockTransport` and throw
`ConfigurationException` when it is absent, even if no requests have been sent.
They never create an empty history to make a check pass. Assertions count transport
attempts, including retries and rejected unmatched requests; cache hits add no attempt.

For example, one `send()` of `GetUser` that receives 503 and then 200 under an enabled
retry policy produces two transport attempts: `assertSent(GetUser::class, times: 2)`
passes, while `times: 1` fails. A separate authentication request is counted under
its own class, not as another GetUser attempt. These checks do not prove how many
business actions the provider performed; they verify the SDK's attempted sends.

## Global MockClient <a id="section-8"></a>
```php
use ApiSutra\Testing\MockClient;

MockClient::global([GetUser::class => MockResponse::success()]);
// ...
MockClient::destroyGlobal();
```

## Helper for oneOf contracts <a id="section-9"></a>
A framework-agnostic helper is available for provider SDK tests:
`ApiSutra\Testing\RequestContractTestHelper`.

Example:
```php
use ApiSutra\Testing\RequestContractTestHelper;

$result = $request->send()->raw();

$isContractError = RequestContractTestHelper::isRequestContractViolation($result);
$context = RequestContractTestHelper::context($result);
$codes = RequestContractTestHelper::violationCodes($result);
$hasMismatch = RequestContractTestHelper::hasViolationCode($result, 'discriminator_mismatch');
```

The helper does not depend on PHPUnit/Pest and works with either testing style.

Practical guidance:
- For an SDK using `RequestOneOf` / `RequestDiscriminator`, the helper should almost
  always be considered part of the baseline.
- Without mutually exclusive payload variants, this layer is unnecessary.

## Reversible test sessions <a id="session"></a>

`$client->beginFakeSession()` returns ClientFakeSession with `fake`, `assertSent`,
`assertNotSent`, `assertNothingSent`, `verify` and idempotent `close`. Verify checks
missing mocks; close restores the transport. Invoke both at your test lifecycle boundary,
with close in finally. The session does not depend on PHPUnit.
Laravel handles this through its [integration trait](https://github.com/apisutra/laravel/blob/master/docs/en/reference/integrations/testing.md).
Sessions preserve auth/cache/quotas/cooldown. Repeated fake resets history but remembers
violations. Active async/lazy executions reject transport replacement.
