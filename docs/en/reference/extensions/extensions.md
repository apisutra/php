<!-- languages --> <a href="extensions.md">English</a> · <a href="../../../ru/reference/extensions/extensions.md">Русский</a> <!-- /languages -->
# Public extensions <a id="section-1"></a>

Extensions attach handlers and additional behavior without changing the client core.

## Use cases <a id="section-2"></a>
- Registering casts, hooks, and response handlers.
- Adding custom attribute handlers.
- Centrally attaching cross-cutting behavior (logging, metrics, tracing).

If no customization is needed, omit `extensions`; default behavior stays unchanged.

See the [extensions guide](../../guides/recipes/extensions.md) for a registration example.

## Recommended approach: a separate class <a id="section-3"></a>
Imports are omitted; this skeleton shows the purpose of each method.
```php
final class ExampleExtension implements ExtensionInterface
{
    public function getName(): string
    {
        // Unique extension key for registration, conflicts, and logging.
        return 'example';
    }

    public function register(ExtensionContext $context): void
    {
        // Register casts, hooks, response handlers, and attribute handlers.
        // Example: $context->registerHook(...); $context->registerCast(...);
    }

    public function boot(ClientConfig $config): void
    {
        // Initialize after registration (caches, clients, resource preparation).
    }

    public function checkDependencies(): void
    {
        // Check dependencies/configuration; throw on failure.
    }

    public function isEnabled(): bool
    {
        // Return whether the extension is enabled under the current conditions.
        return true;
    }
}
```

## Basic configuration <a id="section-4"></a>
```php
$config = new ClientConfig(
    baseUrl: 'https://api.example',
    extensions: [
        new ExampleExtension(),
    ],
);
```

Extensions provide a single SDK extension mechanism for casts, hooks, response
handlers, and custom attributes.

## Lifecycle <a id="section-5"></a>

Attaching an extension calls `checkDependencies()` and `register()`.
Registered hooks, casts, and attribute handlers become available through their
respective registries.

When selecting a response handler, the SDK checks its extension's `isEnabled()`.
If disabled, it raises `ExtensionDisabledException`. Before the selected handler's
first use, the SDK calls `boot()`; a successful boot runs once during the extension's
registration lifetime. This path does not run for an extension containing only hooks
or casts: `boot()` is not a general client construction phase, and `isEnabled()`
does not disable registrations in other registries.

## What can be registered <a id="section-6"></a>
- Request serialization casts (`registerCast`); see the [cast reference](../serialization/casts.md#section-9)
  for how sources participate in hydration.
- Hooks (`registerHook`).
- Response handlers (`registerResponseHandler`).
- Attribute handlers (`registerAttributeHandler`).

## Response handlers and priority <a id="section-7"></a>
Handlers are selected by `Content-Type` in this order:
1) Exact match (`application/json`).
2) Type wildcard (`application/*`).
3) `*`.

If no MIME type matches, the handler's `supports()` is used.

A non-null `handle()` result becomes the response value, bypassing standard
`Returns::unwrap` and DTO hydration. Returning null delegates to the standard path.
Subsequent `AfterHydrate` hooks still run. To modify data before standard hydration,
use [BeforeHydrate](hooks.md#section-6).

## Conflicts and overrides <a id="section-8"></a>
If a MIME type already has a handler:
- Without `override`: `ExtensionConflictException`.
- With `override = true`: the handler is replaced.

By default, handlers are registered only through attached extensions.
Use `override = true` to replace another extension's behavior for the same `Content-Type`.
