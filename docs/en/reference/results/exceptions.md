<!-- languages --> <a href="exceptions.md">English</a> · <a href="../../../ru/reference/results/exceptions.md">Русский</a> <!-- /languages -->
# Result types and custom exceptions <a id="section-1"></a>

`#[Returns(Dto::class)]` automatically checks the type of the final successful value.
No additional configuration is required: after response handling and transformation
stages, the result must be an instance of the declared DTO or its subclass. An SDK
method can therefore return `send($request)->dataOrFail()` without another `instanceof`.
This is a runtime guarantee; IDEs do not infer PHP types from the attribute.

## Type mismatch messages <a id="section-2"></a>

Request fragment; `AccountInfo` is your SDK's DTO:

```php
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use Example\ResultErrors\AccountInfo;

#[Get('/account')]
#[Returns(AccountInfo::class, mismatchMessage: 'Получен неожиданный результат чтения аккаунта.')]
final class GetAccountRequest extends AbstractRequest
{
}
```

Message priority:

1. The request's `Returns::mismatchMessage`.
2. `ClientConfig::resultExceptions->mismatchMessage`.
3. ApiSutra's message containing the request class, expected type, and actual type.

Both overrides are optional; `null` falls through to the next level. An empty or
whitespace-only string produces `configuration_error` during execution, before HTTP.
The operation catalog does not perform this check. Standard messages exclude data
values. Overrides are literal strings without interpolation; do not include secrets.
Built-in messages use the client's [localization](../client/localization.md);
literal overrides and exceptions returned by your factory keep their own text.

A mismatch produces `FAILED`, `hydration_error`, and `ResponseTypeMismatchException`
(a `HydrationException` subclass in `Exceptions\Serialization`). The exception and
first error's context expose `reason=response_type_mismatch`, `path=$`, `expected`,
and `actual`. The HTTP response is preserved; the invalid result is not cached as a success.

This message applies only to a **final type mismatch**. HTTP, transport, JSON decoding,
and DTO field errors retain their own reasons and messages.

## One factory per client <a id="section-3"></a>

To use custom exception classes, supply the optional
`ApiSutra\Contracts\Interfaces\Errors\ExecutionExceptionFactoryInterface`:

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\ResultExceptionConfig;
use Example\ResultErrors\ProviderExceptionFactory;

$config = new ClientConfig(
    baseUrl: 'https://api.example.test',
    resultExceptions: new ResultExceptionConfig(
        mismatchMessage: 'Провайдер вернул результат неожиданного типа.',
        exceptionFactory: new ProviderExceptionFactory(),
    ),
);
```

Contract signature: `make(ExecutionResult $result, string $message): ?Throwable`.
The factory receives the complete, finalized result: response, errors, exception,
meta, nested results, traceId, and trace. It can handle any terminal error and return
`null` for others. This preserves the original exception, or falls back to `SdkException`
if none exists. Return your Throwable with the supplied message. To preserve causality,
pass `$result->exception` as your exception's `previous`.

Without `resultExceptions`, with an empty `new ResultExceptionConfig()`, or without
`exceptionFactory`, standard exceptions apply. Configure a shared message without a
factory, or a factory without a shared message. `with(resultExceptions: null)` removes
the block from a copy; ordinary `with()` preserves it. The optional Laravel `ClientConfigFactory::make()` accepts the
block in overrides. Create objects in your application's provider/factory, not in
cached Laravel configuration. No new dependencies or container are required.

`resultExceptions` is independent of [HydrationConfig](../dto/configuration.md):
hydration creates DTOs from attributes and external rules, then Returns checks the
final class. RequiredInput, ForbidExplicitNull, Shape, and ConstructorValue errors
retain their original reasons, paths, and HTTP response; the factory receives them
without replacement by a final type mismatch. DTO attributes work without either block.

For [pool consume](../execution/pool-consumption.md#section-3), terminal FAILED selection receives one real failed child ExecutionResult, not a PoolResult aggregate. A factory branching on aggregates can choose a different type. The selected exception is delivered directly; source/handler/factory/executor crashes use PoolConsumptionException with a summary and a cause chain.

## When the factory runs <a id="section-4"></a>

The factory runs when an exception is delivered from `FAILED`: explicit
`ResultHandle::dataOrFail()` / `ExecutionResult::throw()`, public send/aggregate delivery
with `throwOnErrors: true`, terminal failed-result delivery from await, and a pool
error callback. SUCCESS/PARTIAL do not call it. Reading raw/resolved results does not
call it when the public send returned normally.

Every internal request uses [the executor](../extensions/execution.md), which returns
a canonical result regardless of throwOnErrors or the presence of a factory. Auth,
retry, FailStrategy, and readiness resolvers decide using that result. Public
`send()`/`sendAsync()` overrides apply only to direct user calls; use hooks or an
executor decorator for shared behavior.

| Delivery | Factory calls |
| --- | --- |
| Internal failed request; raw/resolved read | 0 |
| Public FAILED with throwOnErrors true | 1, with the complete final aggregate if applicable |
| Pool FAILED error callback | 1 for that child; automatic aggregate delivery is separate |
| Two explicit `throw()` calls | 2; factory output is not cached |

An aggregate's own failure takes precedence. Otherwise its exception is the first
FAILED child's exception in **input order**, regardless of completion order. If that
child has no exception, the default message comes from its first error; a later
child's exception is not borrowed. The factory always receives the whole aggregate
with all canonical children. Partial/IgnoreErrors continuation does not depend on
public throwOnErrors.

A factory should only select/create an exception, without HTTP or recursive `throw()`.
If it throws (including TypeError), delivery raises `ExceptionFactoryException` from
`Exceptions\Configuration`, with `reason=exception_factory_failed`, the original
result in `result`, and the factory failure in `previous`. There is no second mapping,
retry, or factory invocation. A pool callback failure stops new work and further
callbacks, drains issued promises, and propagates without automatic aggregate throw.
Apply [redaction](observability.md) in custom logs; raw factory bodies are not logged automatically.

## Returns validation boundaries <a id="section-5"></a>

- [Declaration validation](../attributes/response.md#declaration-validation) precedes HTTP;
  it checks DTO and hydrator classes even if hydration is bypassed. DI stays deferred.
- A non-null response handler result is checked without repeated hydration.
  Returning `null` still selects standard response parsing.
- The actual value returned after stages is checked, including EarlyReturn and
  successful composite results. An existing `FAILED` is not replaced by a type error.
- With unwrap, `Returns::type` applies when specified; otherwise `response` applies.
  Composite retains its separate assembly contract.
- Pagination checks page DTOs. The aggregated array of pages/items is not checked
  as a single page DTO. The continuation final value has its own contract.
- `Download` retains priority for the final value, without skipping declaration validation; RawResponse remains incompatible with a
  DTO. This check adds no stream reads or file buffering.
- Without an active DTO, null/scalars/arrays retain their behavior. An empty response
  or JSON null for a DTO without unwrap may still hydrate through defaults; this
  mechanism does not introduce strict JSON-object validation.

The [complete executable example](../../examples/result-errors.md) demonstrates two
requests, one factory, and fallback.
