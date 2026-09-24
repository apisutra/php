<!-- languages --> <a href="hooks.md">English</a> · <a href="../../../ru/reference/attributes/hooks.md">Русский</a> <!-- /languages -->
# Hooks and invocation points <a id="section-1"></a>

## Signatures and targets <a id="section-2"></a>

Class names belong to `ApiSutra\Attributes\Hooks`.
Signatures show constructor parameters and defaults; target identifies where the
attribute is allowed. Behavior and priorities are covered in the topic links below.

| Attribute | Target | Constructor |
| --- | --- | --- |
| AfterHydrate | CLASS / REPEATABLE | `AfterHydrate(string $handler, HookPriority $priority = HookPriority::Normal, ?string $name = null)` |
| AfterResponse | CLASS / REPEATABLE | `AfterResponse(string $handler, HookPriority $priority = HookPriority::Normal, ?string $name = null)` |
| BeforeHydrate | CLASS / REPEATABLE | `BeforeHydrate(string $handler, HookPriority $priority = HookPriority::Normal, ?string $name = null)` |
| BeforeSend | CLASS / REPEATABLE | `BeforeSend(string $handler, HookPriority $priority = HookPriority::Normal, ?string $name = null)` |

These attributes attach hook handlers to the request lifecycle.
All are repeatable (`IS_REPEATABLE`).

## When to use them <a id="section-3"></a>
- **`BeforeSend`** — add/change headers, trace IDs, or signatures.
- **AfterResponse** — logging, metrics, and auditing.
- **BeforeHydrate** — normalize data before DTO hydration.
- **AfterHydrate** — post-process DTOs.

Detailed centralized hooks guide: `docs/en/reference/extensions/hooks.md`.

## BeforeSend <a id="section-4"></a>
**Parameters:**
- `handler: string` — handler class.
- `priority: HookPriority = Normal`
- `name?: string` — name for duplicate protection.

## AfterResponse <a id="section-5"></a>
**Parameters:** same as `BeforeSend`.

## BeforeHydrate <a id="section-6"></a>
**Parameters:** same as `BeforeSend`.

## AfterHydrate <a id="section-7"></a>
**Parameters:** same as `BeforeSend`.

Example:
```php
use ApiSutra\Attributes\Hooks\AfterResponse;
use ApiSutra\Attributes\Hooks\BeforeSend;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Hooks\HookPriority;

#[BeforeSend(AddTraceHook::class, priority: HookPriority::First, name: 'trace')]
#[AfterResponse(LogResponseHook::class)]
final class SomeRequest extends AbstractRequest {}
```
