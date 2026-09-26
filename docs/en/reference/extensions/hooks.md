<!-- languages --> <a href="hooks.md">English</a> · <a href="../../../ru/reference/extensions/hooks.md">Русский</a> <!-- /languages -->
# Hooks and invocation points <a id="section-1"></a>

Hooks are centralized pipeline stage handlers. They add behavior without changing
request or DTO code.

## Hook types <a id="section-2"></a>
- `BeforeSend`: before sending the HTTP request.
- `AfterResponse`: after receiving the response.
- `BeforeHydrate`: before DTO hydration (can return modified data).
- `AfterHydrate`: after DTO hydration.

## Registering hooks with HookRegistry <a id="section-3"></a>
```php
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Enums\Hooks\HookPriority;
use ApiSutra\Hooks\HookRegistry;

$hooks = new HookRegistry(resolver: fn (string $class) => new $class());

// Global hook
$hooks->on(Hook::BeforeSend, AddTraceHeader::class, priority: HookPriority::First, name: 'trace');

// Hook for a specific request only
$hooks->on(Hook::AfterResponse, LogResponse::class, for: [GetUser::class]);

// Hook for a specific DTO only
$hooks->on(Hook::BeforeHydrate, UnwrapData::class, forDto: UserDto::class);
```

### Naming and removal <a id="section-4"></a>
`name` prevents duplicates and allows a hook to be removed:
```php
$hooks->remove(Hook::BeforeSend, 'trace');
```

## Execution order <a id="section-5"></a>
Each stage runs in this order:
1) **Global** hooks.
2) **Request-specific** hooks (`for`).
3) **DTO-specific** hooks (`forDto`).
4) **Hook attributes** on the request.
5) **Request methods** (`beforeSend/afterResponse/beforeHydrate/afterHydrate`).

Within each group, priorities are `First → Normal → Last`.

## BeforeHydrate: modifying data <a id="section-6"></a>
`BeforeHydrate` can return modified data:
```php
use ApiSutra\Contracts\Interfaces\Hooks\BeforeHydrateHookInterface;
use ApiSutra\VO\Pipeline\PipelineContext;

final class UnwrapData implements BeforeHydrateHookInterface
{
    public function handle(PipelineContext $context): array
    {
        return $context->response?->json('data') ?? [];
    }
}
```

Without `BeforeHydrate`, data goes into hydration unchanged.
The request's `beforeHydrate()` method returns the input array unchanged by default.

An observer returning null preserves original JSON container forms. An array returned
by a handler or an overridden request method is new PHP input, even if equal to the
original array. See [transformation boundaries](../dto/scope.md#section-3).

For a successful response without a DTO, the hook runs only for array data. `null`,
JSON scalars, and `text/plain` bypass `BeforeHydrate`; `AfterResponse` and
`AfterHydrate` still run. Details and empty-body rules are in the
[successful response contract](../results/handles.md#section-3).

## Hook attributes <a id="section-7"></a>
Hook attributes are covered separately: [hook attributes](../attributes/hooks.md).
