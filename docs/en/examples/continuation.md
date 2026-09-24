<!-- languages --> <a href="continuation.md">English</a> · <a href="../../ru/examples/continuation.md">Русский</a> <!-- /languages -->
# Awaiting an operation <a id="section-1"></a>

From the checkout after Composer install:

```bash
php docs/example/continuation/run.php
```

For an installed package, prefix the path with `vendor/apisutra/php/`. Expected output: `{"id":7,"cached_same_object":true,"requests":3}`.

[run.php](../../example/continuation/run.php) uses the example SDK client, a fake, and strict FinalDto rules. [StartRequest](../../example/continuation/src/StartRequest.php) declares the final type and polling request. [PollRequest](../../example/continuation/src/PollRequest.php) takes one required token. [TokenExtractor](../../example/continuation/src/TokenExtractor.php) reads it from the original HTTP JSON; [OperationStateResolver](../../example/continuation/src/OperationStateResolver.php) explicitly selects Pending/Ready/Failed. [FinalDto](../../example/continuation/src/FinalDto.php) is created only after Ready.

The start returns Pending; the first poll is also Pending and the second is Ready. Thus there are 3 HTTP calls with maxAttempts = 2. Repeated await returns the same DTO without new HTTP. The interval is zero only for this local example.

[Guide](../guides/recipes/continuation.md) · [Readiness](../reference/execution/continuation-state.md) · [Waiting and errors](../reference/execution/continuation-await.md).
