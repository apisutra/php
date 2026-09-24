<!-- languages --> <a href="custom-result.md">English</a> · <a href="../../ru/examples/custom-result.md">Русский</a> <!-- /languages -->
# Custom result representation <a id="section-1"></a>

`SdkResult` adds example SDK methods: `recordOrFail()` returns `RecordDto`, and `requiresReauthorization()` recognizes HTTP 401. The standard interface delegates to an existing `ResolvedResult`; the original `ExecutionResult` and errors are preserved.

From the package checkout:

```bash
php docs/example/custom-result/run.php
```

From an application with the package installed:

```bash
php vendor/apisutra/php/docs/example/custom-result/run.php
```

MockTransport returns local responses; network access, Laravel, and credentials are unnecessary. The script prints JSON with [fixed expectations](../../example/custom-result/fixtures/expected.json).

| File | Purpose |
| --- | --- |
| [SdkResult](../../example/custom-result/src/SdkResult.php) | Two SDK methods and complete delegation of ResolvedResultInterface |
| [SdkResultFactory](../../example/custom-result/src/SdkResultFactory.php) | Wraps the standard factory's result |
| [run.php](../../example/custom-result/run.php) | Client configuration, type refinement, success, HTTP error, and a different operation |

The example reuses classes from the [client overview](client-showcase.md) and [TokenExtractor](../../example/continuation/src/TokenExtractor.php) from the await example. It checks preservation of extractor configuration; polling is not started here. Three operations produce three HTTP calls: reading the DTO, result, and diagnostics does not resend a request.

[Annotated recipe](../guides/recipes/custom-result.md) · [Result contract](../reference/results/handles.md#section-10) · [All examples](README.md).
