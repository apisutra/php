<!-- languages --> <a href="localization.md">English</a> · <a href="../../../ru/reference/client/localization.md">Русский</a> <!-- /languages -->
# Message language <a id="section-1"></a>

ApiSutra emits its own `messages` in English by default. Built-in en and ru need no
additional dependencies. The setting belongs to a client instance and is retained
by `ClientConfig::with()`.

Configuration snippet for your SDK:

```php
use ApiSutra\Config\ClientConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example.test',
    localization: 'ru',
);
$english = $config->with(localization: 'en');
```

A string is shorthand for `new LocalizationConfig('ru')`. The constructor and `with()` accept `LocalizationConfig|string`; the `$config->localization` property
always holds `LocalizationConfig`. Use the object for custom catalogs and overrides.
`with(localization: 'en')` replaces the entire block, including catalogs; fields without
an override are retained.

Create a second client using `$english`: the existing Russian client does not change.
The language applies to ApiSutra exceptions, `RequestError`, `ClientError`, result `messages`,
and the SDK's own log entries. It is preserved across async, batch/pool, pagination,
nested hydration, and `awaitAs()`, including remapping a stored result. The shared
metadata cache does not store language.

The [executable example](../../examples/localization.md) demonstrates both languages,
a DTO error, and an additional SDK catalog.

## What remains unchanged <a id="section-2"></a>

- `ErrorCode`, `reason`, path, `sourcePath`, statuses, and technical context keys.
- External API response text, third-party validator `messages`, and exceptions with
  ordinary strings from custom handlers.
- HTTP headers, payloads, cache keys, and DTO data. Set `Accept-Language` separately
  if the provider requires it.

`ErrorCode::title($localization)` accepts the same block; without an argument it returns
the English title. Localization does not replace [error mapping](../results/errors.md).

## SDK catalogs and overrides <a id="section-3"></a>

`Message` contains a stable key and named parameters. Pass a language → key → template
array as `messages`. Use your own prefix for SDK `messages`.

```php
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;

$localization = new LocalizationConfig('ru', messages: [
    'en' => ['records.limit' => 'Limit exceeded: {limit}'],
    'ru' => ['records.limit' => 'Превышен лимит: {limit}'],
]);
$error = new ConfigurationException(new Message('records.limit', ['limit' => 10]));
$translated = $error->localized($localization);
// Exact Russian output from $translated->getMessage(): «Превышен лимит: 10».
```

If a handler throws such an exception during request execution, the client applies
its catalog automatically. Outside the client, pass `localization:` to the
`SdkException`/`ConfigurationException` constructor or call `localized()` explicitly.
Built-in keys and templates are available through `MessageCatalog::all('en')` and `all('ru')`.

Scalar, null, and nested `Message` parameters are allowed; arbitrary objects are
forbidden. `{name}` substitution neither executes PHP nor interprets placeholders inside
an inserted value. `messageDefinition()` exposes the descriptor, but it is not added
automatically to error JSON or logs. Do not put secrets in error text;
[redaction rules](../results/observability.md) still apply.

Locales are normalized: `ru_RU` → `ru-ru`. Lookup order: selected variant, base language,
en; within each language, user catalogs precede built-ins. An unknown key is returned
as text. An override's placeholder set must match the English template; invalid
translations are skipped. Without an SDK English template, supplied parameter names
are the reference. A missing required parameter returns the key. Invalid catalog
structure or locale causes `ConfigurationException`; an empty key or unsupported
parameter causes `InvalidArgumentException`.

## Standalone and Laravel <a id="section-4"></a>

`Hydrator`, `Serializer`, `DtoSerializer`, `ClientRegistry`, `ServiceRegistrar`, and
`RequestNamespaceDetector` accept optional `localization:` as their final constructor
argument. `Hydrator::forConfig($hydration, $localization)` also preserves the block.
Without explicit configuration, standalone use is English; `Dto::from()`, `toArray()`,
and `default()` do not borrow the last client's language.

With `apisutra/laravel` installed, pass the chosen language in the client factory:
`app(\ApiSutra\Laravel\ClientConfigFactory::class)->make(['baseUrl' => $url, 'localization' => 'ru'])`.
The application's config file may store a locale string; build `LocalizationConfig`
in the factory for a custom catalog. The core does not read `app.locale`: the application
decides where language comes from. Configure a shared registrar's language separately;
a client does not modify the shared registry.

Errors during `ClientConfig` construction use the supplied block. Objects constructed
**before** it, such as `new RetryConfig(...)`, have no client context yet and default
to English. Explicit `localized()` is available for them.

## Exceptions and alternative representations <a id="section-5"></a>

`getMessage()` still returns a string. If translation changes the text, `localized()`
creates an exception of the same SDK class, preserves its fields, and puts the original
in `previous` with its original stack trace. The original object is unchanged. If text
is unchanged or the original message is an ordinary string, the same object is returned.
The `previous` chain may therefore become longer.

`ExecutionResult::localized($localization)` creates another-language representation of
errors and nested results; data, response, and original result are preserved.
`localization()` returns representation settings. Custom string `messages` remain unchanged.

For an `SdkException` subclass with its own constructor signature and `Message`, override
`protected copyForLocalization(): static`: construct the same type, transfer custom
fields, and pass `$this` as `previous`. The factory is not called for an ordinary string.
`ExecutionResult` subclasses using `localized()` must retain the base constructor
signature or override the method.

[Client parameters](configuration.md).

## Localized diagnostics <a id="section-6"></a>

`Message` translation preserves `ExecutionTrace`, audit, and parentExecutionId links.
Machine `event` and log correlation fields are independent of EN/RU. External logger
or diagnostic formatting failures do not change the API result.
See [log and audit fields](../results/observability.md).
