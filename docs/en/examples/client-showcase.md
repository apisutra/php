<!-- languages --> <a href="client-showcase.md">English</a> · <a href="../../ru/examples/client-showcase.md">Русский</a> <!-- /languages -->
# Client construction and configuration <a id="section-1"></a>

One fictional SDK demonstrates client settings through execution results. MockTransport returns local responses; network access, Laravel, and real credentials are unnecessary. All example tokens are fictional.

From the package checkout:

```bash
php docs/example/client-showcase/run.php
```

From an application with the package installed:

```bash
php vendor/apisutra/php/docs/example/client-showcase/run.php
```

The [annotated overview](../guides/client/showcase.md) explains snippets by task. The script prints JSON; the [expected result](../../example/client-showcase/fixtures/expected.json) is defined separately.

| Files | Purpose |
| --- | --- |
| [run.php](../../example/client-showcase/run.php) | Setup, auth, retry, quota, cache, DTOs, diagnostics, and overrides |
| [DemoClient](../../example/client-showcase/src/DemoClient.php), [resource](../../example/client-showcase/src/Resources/Records/RecordsResource.php) | Construction entry point and `records()->get(7)` |
| [GetRecordRequest](../../example/client-showcase/src/Resources/Records/Get/GetRecordRequest.php) | GET with a typed response; execution policies come from the client |
| [RecordDto](../../example/client-showcase/src/Resources/Records/RecordDto.php), [SaveRecordRequest](../../example/client-showcase/src/Resources/Records/Save/SaveRecordRequest.php) | Shared read/save model and outgoing JSON verification |
| [MemoryStore](../../example/client-showcase/src/Support/MemoryStore.php), [MemoryLogger](../../example/client-showcase/src/Support/MemoryLogger.php) | Teaching implementations in memory; the application supplies its own PSR-16 store and PSR-3 logger |

Expected behavior: two attempts for a 503 → 200 sequence, local rejection of the third request with a 2/min quota, and one HTTP call for two cached reads. Strict rules reject an invalid string `id`; `_extra` is available in the DTO and excluded from the request. Diagnostics mask Bearer credentials, while per-call options preserve the original request and configuration. Timeout forwarding to the transport is checked without simulating network waiting.

[All examples](README.md) · [Full configuration](../reference/client/configuration.md).
