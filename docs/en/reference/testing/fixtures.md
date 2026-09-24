<!-- languages --> <a href="fixtures.md">English</a> · <a href="../../../ru/reference/testing/fixtures.md">Русский</a> <!-- /languages -->
# Recording and replaying responses <a id="section-1"></a>

## Fixtures (record/playback) <a id="section-2"></a>

The recorder masks standard credentials by default, even without a Fixture.
`$client->record()` uses `ClientConfig::redaction`; custom Fixture rules extend the
baseline. See [logging](../results/observability.md#section-8) for redaction settings and boundaries.

```php
use ApiSutra\Testing\Fixture;

final class UserFixture extends Fixture
{
    protected function defineSensitiveHeaders(): array
    {
        return ['Authorization' => '***'];
    }
}

$client->record(__DIR__ . '/fixtures', [
    GetUser::class => new UserFixture(),
]);

$client->playback(__DIR__ . '/fixtures');
```

Fixtures can mask:
- Headers.
- JSON fields.
- Regex patterns.

By default, a missing fixture means the request is unmocked and returns
`MockResponse::notFound()` unless `preventStrayRequests()` is enabled.

You can enable strict handling of missing fixtures:
```php
use ApiSutra\Testing\MockConfig;

MockConfig::throwOnMissingFixtures();
```

In strict mode, a missing fixture throws an exception.

## Recording errors <a id="section-3"></a>

Recording is explicitly enabled. A failed write returns `execution_error` with
`reason=recording_failed` and `RecordingException`; `response` retains the actual
HTTP response, and `previous` contains the technical cause. The automatic message
contains neither the directory path nor the body. In result-first mode, check the
error; with throwOnErrors, handle the exception.

HTTP has already completed at this point: for example, a POST created an object but
the fixture could not be written. The SDK does not repeat HTTP for this error, even
with POST retry enabled and `retryExceptions: [Throwable::class]`. Applications must
also distinguish it from a network error to avoid creating the object again.

A fixture is published in full under an unused name without overwriting an existing
file. Concurrent recorders receive different names. An interrupted process may leave
a hidden `.recording-*` temporary file, but never a partially published JSON fixture.
Valid large JSON and text are not truncated by the safe debug/log limit. Invalid
UTF-8 produces an explicit recording error instead of an empty file; no new binary
format is introduced. Streams retain bodyOmitted/size and are not read to create a fixture.
