<!-- languages --> <a href="custom-result.md">English</a> · <a href="../../../ru/guides/recipes/custom-result.md">Русский</a> <!-- /languages -->
# Custom result representation <a id="section-1"></a>

Connect your own factory so that `resolved()` returns an object with your SDK's methods.
In this example, `SdkResult::recordOrFail()` returns a concrete `RecordDto`, and
`requiresReauthorization()` reports HTTP 401. Data is still created according to
`#[Returns]`; the common contract is [ResolvedResultInterface](../../reference/results/handles.md#section-6).

## Run the example <a id="section-2"></a>

```bash
php docs/example/custom-result/run.php
```

In an installed package, prefix the path with `vendor/apisutra/php/`.
The [complete example](../../examples/custom-result.md) uses the existing tutorial SDK
and MockTransport: success, HTTP 401, and saving a record are checked without network access.

## 1. Add SDK methods <a id="section-3"></a>

The standard `ResolvedResult` is `final`. Therefore,
[SdkResult](../../../example/custom-result/src/SdkResult.php) implements
`ResolvedResultInterface` and stores a ready-made representation in `$inner`.
Every interface method delegates explicitly to it: statuses, data, errors, token,
and access to the original `ExecutionResult`. The complete class is linked above;
only its two additional methods are shown below:

```php
use Example\ClientShowcase\Resources\Records\RecordDto;
use UnexpectedValueException;

    public function recordOrFail(): RecordDto
    {
        $this->inner->result()->throw();
        $data = $this->inner->data();
        if (!$data instanceof RecordDto) {
            throw new UnexpectedValueException('Эта операция не вернула RecordDto');
        }

        return $data;
    }

    public function requiresReauthorization(): bool
    {
        return $this->inner->errorStatus() === 401;
    }
```

For `FAILED`, the first method preserves the standard exception. For a successful
operation with another data type, it throws `UnexpectedValueException`; ordinary
`data()` stays generic. `PARTIAL` is not turned into success: `isPartial()` and errors
are preserved, while `recordOrFail()` may return a DTO if present. Detecting HTTP 401
does not itself initiate authentication.

## 2. Create a factory <a id="section-4"></a>

[SdkResultFactory](../../../example/custom-result/src/SdkResultFactory.php) receives
the standard factory and wraps its result:

```php
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResolvedResultFactoryInterface;
use Override;

final readonly class SdkResultFactory implements ResolvedResultFactoryInterface
{
    public function __construct(private ResolvedResultFactoryInterface $defaults)
    {
    }

    #[Override]
    public function make(ExecutionResult $result): SdkResult
    {
        return new SdkResult($this->defaults->make($result));
    }
}
```

Both classes are in the `Example\CustomResult` namespace. The concrete `SdkResult`
return type is compatible with the factory interface. The wrapper preserves the
original result, does not rehydrate data, and does not send an HTTP request.

## 3. Connect it to the client <a id="section-5"></a>

Snippet from [run.php](../../../example/custom-result/run.php), where `$transport`
is already configured for local responses. `DemoClient` comes from the
[client showcase](../client/showcase.md), and `TokenExtractor` from the
[waiting example](../../../example/continuation/src/TokenExtractor.php).
The extractor reads the fictional `operationToken` field; polling is not needed here.

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Result\ResolvedResultFactory;
use Example\ClientShowcase\DemoClient;
use Example\Continuation\TokenExtractor;
use Example\CustomResult\SdkResultFactory;

$base = new ClientConfig(
    baseUrl: 'https://api.example.test',
    authRetryOn401: false,
    continuationTokenExtractor: new TokenExtractor(),
);

// The custom factory explicitly receives the standard representation's settings.
$defaults = new ResolvedResultFactory(
    mapper: $base->errorMapper,
    errorContextFactory: $base->errorContextFactory,
    continuationTokenExtractor: $base->continuationTokenExtractor,
);
$client = new DemoClient(
    $base->with(resolvedResultFactory: new SdkResultFactory($defaults)),
    $transport,
);
```

When a custom `resolvedResultFactory` is set, the client uses it directly.
The `errorMapper`, `errorContextFactory`, and `continuationTokenExtractor` settings
are not injected into it automatically; above, they are explicitly passed to the
standard factory. Rebuild the factory when changing these strategies.

The setting applies to every operation on this client. A method for a specific DTO
therefore checks its type; another operation may return an array, collection, or `null`.
If you only need custom error codes, `errorMapper` is sufficient without a new result class.

## 4. Narrow the type for the IDE <a id="section-6"></a>

The declared type of `ResultHandle::resolved()` remains `ResolvedResultInterface`.
Setting a factory does not change the method signature. An `instanceof` check both
verifies the setup and exposes the additional methods to the IDE:

```php
use Example\CustomResult\SdkResult;

$handle = $client->records()->get(7)->send();
$resolved = $handle->resolved(); // Declared type: ResolvedResultInterface.
if (!$resolved instanceof SdkResult) {
    throw new LogicException('Клиент должен использовать SdkResultFactory');
}
// After the check, the IDE sees SdkResult methods and the concrete RecordDto type.
$record = $resolved->recordOrFail();
$needsLogin = $resolved->requiresReauthorization();
```

In your SDK, you can put this check in a method returning `SdkResult`.
A PHPDoc `@var` annotation alone only hints the type to the IDE; it does not check the object at runtime.

`send()` still returns `ResultHandle`, and `raw()` returns `ExecutionResult`.
`$handle->dataOrFail()` reads data from the original result and does not call
`recordOrFail()`. `request->resolvedAsync()` uses the same factory. Reading `resolved()` again
does not send HTTP, but may create a new wrapper: do not rely on its object identity.
In this example, `result()` always returns the same `ExecutionResult`, preserving
`trace`, `audit`, `debug`, and child results.

[Factory contract](../../reference/results/handles.md#section-10) ·
[Error mapping](../../reference/results/errors.md) · [More recipes](../README.md).
