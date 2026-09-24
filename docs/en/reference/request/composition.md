<!-- languages --> <a href="composition.md">English</a> · <a href="../../../ru/reference/request/composition.md">Русский</a> <!-- /languages -->
# Composite and dependent requests <a id="section-1"></a>

This guide covers requests that combine several requests into one operation.
They run within one pipeline and return a single `ExecutionResult` with nested results.

## CompositeRequest <a id="section-2"></a>
**Purpose:** execute a set of independent requests and aggregate their results.

Contract:
- `requests(): RequestCollection`.
- `aggregate(ResultCollection $results, PipelineContext $ctx): mixed`.

### Example <a id="section-3"></a>
```php
use ApiSutra\Attributes\Behavior\Execution;
use ApiSutra\Collections\RequestCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Execution\ExecutionMode;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\VO\Pipeline\PipelineContext;

#[Execution(mode: ExecutionMode::Parallel, failStrategy: FailStrategy::Partial)]
final class UserProfileComposite extends AbstractRequest implements CompositeRequestInterface
{
    public function __construct(
        public string $userId,
    ) {}

    public function requests(): RequestCollection
    {
        return RequestCollection::make([
            new GetUser($this->userId),
            new GetUserOrders($this->userId),
        ]);
    }

    public function aggregate(ResultCollection $results, PipelineContext $ctx): mixed
    {
        return [
            'user' => $results->get(GetUser::class)?->data,
            'orders' => $results->get(GetUserOrders::class)?->data,
        ];
    }
}
```

### Behavior <a id="section-4"></a>
- `ExecutionMode` selects **sequential** or **parallel** execution for child requests.
- `FailStrategy::FailAll`: any error makes the composite return FAILED.
- `FailStrategy::Partial/IgnoreErrors`: the composite continues and returns PARTIAL/SUCCESS.
- Child results are available in `ExecutionResult::$nested`; metadata is in
  `ExecutionResult::$meta` (BatchMeta).

## DependsOnRequest <a id="section-5"></a>
**Purpose:** execute dependencies, then the main request.

Contract:
- `dependencies(): RequestCollection`.
- `processDependencies(ResultCollection $results, PipelineContext $ctx): void`.

### Example <a id="section-6"></a>
```php
use ApiSutra\Contracts\Interfaces\Core\DependsOnRequestInterface;

final class CreateOrder extends AbstractRequest implements DependsOnRequestInterface
{
    public function __construct(
        public string $userId,
        public ?string $token = null,
    ) {}

    public function dependencies(): RequestCollection
    {
        return RequestCollection::make([
            new FetchUserToken($this->userId),
        ]);
    }

    public function processDependencies(ResultCollection $results, PipelineContext $ctx): void
    {
        $this->token = (string) ($results->get(FetchUserToken::class)?->data ?? '');
    }
}
```

### Behavior <a id="section-7"></a>
- `DependsOn` **always runs sequentially**, even when Parallel is configured.
- `FailStrategy::FailAll` ends execution if a dependency fails.
- After `processDependencies()`, the main request is sent normally.

## RequestRole <a id="section-8"></a>
Within the pipeline, the request role reflects its execution context:
- `Root`: the main request.
- `Nested`: a composite child request.
- `Dependency`: a dependency request.

The role is used in audit logs, debug data, and hooks.

## Replacing the prepared HTTP body <a id="section-9"></a>

In a hook before the first send, assign a new copy to the context:
`$context->preparedRequest = $context->preparedRequest->withBody($text)` or
`->withStream($stream)`. To remove the body, use `->withoutBody()`;
`with(body: null)` and `with(stream: null)` preserve the previous value.
Explicitly update Content-Type when changing formats. See the
[transport guide](../execution/transport.md#section-3) for the full contract and a hook example.

With debug enabled, the final result reflects the actual request associated with the
received response, including the last retry attempt. A failure without a response
uses the context's current prepared request. Changing the context in `AfterResponse`
does not replace the sent body in debug output; redaction remains active.
Replacing the body after the first attempt stops retry with `body_changed`.

## Composite request diagnostics <a id="section-10"></a>

A composite has its own start/terminal events and `nested` child results.
DependsOn dependencies are also retained in `nested`; the main HTTP request continues
the same execution after `processDependencies()`. There is no second started event
for the main request. Children of the same class are distinguished by
`trace.executionId` and linked to their parent through `trace.parentExecutionId`.
See the [shared contract](../results/observability.md#section-5).
