<!-- languages --> <a href="result-errors.md">English</a> · <a href="../../ru/examples/result-errors.md">Русский</a> <!-- /languages -->
# Operation messages and SDK exceptions <a id="section-1"></a>

Run from the package root after installing dependencies:

```bash
php docs/example/result-errors/run.php
```

[DemoClient](../../example/result-errors/src/DemoClient.php) has two methods returning
`AccountInfo` without manual `instanceof` checks: `Returns` provides runtime validation.
The [account request](../../example/result-errors/src/GetAccountRequest.php) and
[owner request](../../example/result-errors/src/GetOwnerRequest.php) declare different
messages. The [factory](../../example/result-errors/src/ProviderExceptionFactory.php)
is configured once on the client and creates a custom exception only for type
mismatches; HTTP errors retain their standard exceptions.

The [scenario](../../example/result-errors/run.php) first performs normal successful
requests without exception configuration, then enables a deliberately broken
response handler and checks both messages, the standard rejection, and the HTTP fallback. It uses MockTransport
without network access, Laravel, or credentials; the output is compared with
[expected.json](../../example/result-errors/fixtures/expected.json).

The provider classes belong to this example, not to the ApiSutra core.

[Full contract](../reference/results/exceptions.md).
