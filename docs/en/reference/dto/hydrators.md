<!-- languages --> <a href="hydrators.md">English</a> · <a href="../../../ru/reference/dto/hydrators.md">Русский</a> <!-- /languages -->
# Custom DTO hydration <a id="section-1"></a>

Use `DtoHydratorInterface` when an existing application factory or mapper should
construct response objects, including classes with private constructors. No handler
is required for ordinary attribute-based DTOs. Serialization is a separate direction.

The [complete runnable example](../../../example/dto-hydrator/run.php) combines a
[factory](../../../example/dto-hydrator/src/UserFactory.php),
[hydrator](../../../example/dto-hydrator/src/UserHydrator.php), native nested DTOs,
explicit serialization and a failed page during lazy item consumption.

## Contract and configuration <a id="contract"></a>

The interface lives in `ApiSutra\Contracts\Interfaces\Serialization`:

```php
use ApiSutra\Serialization\Context\HydrationContext;

// Methods of DtoHydratorInterface; $dtoClass is a class-string.
public function supports(string $dtoClass): bool;
public function hydrate(array|object $data, string $dtoClass, HydrationContext $context): object;
```

`supports()` must be free of I/O and side effects. `false` selects native hydration.
Throwing, returning the wrong object type, or returning null does not select fallback.

The example classes below come from the linked runnable example:

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\HydrationConfig;
use Example\DtoHydrator\UserFactory;
use Example\DtoHydrator\UserHydrator;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    hydration: new HydrationConfig(hydrator: new UserHydrator(new UserFactory())),
);
```

`HydrationConfig::hydrator` accepts a ready instance, default null. Dependencies
belong to the application. A hydrator needs no mandatory logger, clock or SDK client.

For one request, use `Returns::hydrator`:

```php
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use Example\DtoHydrator\User;
use Example\DtoHydrator\UserHydrator;

#[Get('/user')]
#[Returns(User::class, hydrator: UserHydrator::class)]
final class GetMappedUser extends AbstractRequest {}
```

| Value | Selection |
| --- | --- |
| null or omitted | Inherit HydrationConfig::hydrator |
| Class name | Replace the global handler for this response |
| false | Use native hydration throughout this response |

The selected handler applies to the response graph, including nested DTOs and page
items. Unsupported types use native hydration, not the previous global handler.
Each child HTTP request selects its own handler. There is no priority chain or discovery.

A class-string hydrator in Returns is resolved through the available container
provider; a class-string cast is constructed directly without arguments. Laravel
can autowire a hydrator and its concrete factory without registering either class.
Interfaces and special parameters need ordinary application bindings. Without a
container, a local handler must have no required constructor arguments; otherwise
pass a ready instance in HydrationConfig. Bad classes or unresolved dependencies
produce `configuration_error`, without silently choosing another handler. DTO and
hydrator class declarations are [validated before HTTP](../attributes/response.md#declaration-validation).
Creating the handler through DI remains deferred until hydration is needed, so
dependency construction failures can still occur after a successful HTTP response.

## Graph ownership and context <a id="context"></a>

The standard JSON path validates the selected DTO's object shape before invoking
the custom hydrator, including the local empty-list exception. The hydrator still
receives ordinary PHP arrays/objects and owns its internal fields; ready objects
are not traversed again. [Shape and transformation boundaries](shapes.md#section-3).

The handler receives data after HTTP decoding, BeforeHydrate and unwrap, before the
native field pipeline for this node. It owns construction and validation of its object.
ApiSutra does not reapply computed, DtoHydrationProfileInterface policy/casts, field
attributes or external field rules to the returned object. With `supports() === false`
all native rules apply as before. For example, a profile that uppercases strings acts
on a native DTO; a custom-created DTO keeps the string supplied by its factory.
BeforeHydrate/AfterHydrate and the final Returns guard retain their HTTP responsibilities.

The presence of any global hydrator disables eager compilation of external DTO
rules when constructing Hydrator; supports() is not called during construction.
For supports=false, native rules are checked when that class is first used by the
native path, with the same configuration error as before. For custom-owned nodes,
unused native rules may never be checked. This does not disable validation of rules
that the native path actually uses.

Use the current context to build children:

```php
// Inside hydrate(), after validating $data['address'] and $data['items'].
use Example\DtoHydrator\Address;
use Example\DtoHydrator\User;

$address = $context->hydrate($data['address'], Address::class);
$items = $context->hydrateCollection($data['items'], User::class);
$http = $context->http();
$version = $http?->response?->header('X-Api-Version');
```

Address and User are the linked example models. The selected handler
is considered again for each child; calling hydrate on the same node is not a native
fallback operation and can recurse. Native parents can contain custom-created children
and custom parents can delegate children to native hydration.

`http(): ?HttpMappingContext` is the short form of
`extension(HttpMappingContext::class)`: it returns the same object without I/O or a
second snapshot. It provides request, response, traceId, role, runtime options and
pagination options. Both access paths obey the [context lifetime](scope.md).
Standalone and final continuation payloads may have no HTTP extension; then http()
returns null. After the handler finishes every context method throws instead.

The config instance can be shared, as can a container binding. Do not store the
current data, request or context in the handler. Each invocation receives its own
context, including concurrent execution. Local resolution occurs once per response,
shared by its container and items. Metadata caches hold declarations, not handlers.

## Other entry points and failures <a id="failures"></a>

- `Hydrator::forConfig($config)` uses the same handler without HTTP or a container.
  DTO::from and Hydrator::default do not read a client's global configuration.
- Cache hits hydrate again from the stored HTTP response. Raw, downloads and a final
  response handler retain their existing exits; this API does not add XML decoding.
- Composite aggregates use their declared handler. Final continuation DTOs use the
  client handler, not the handler declared for the original acknowledgement.
- Internal OAuth token DTOs explicitly select native hydration so application
  handlers cannot bypass token response normalization.
- Handler failures and wrong output types produce `hydration_error`; root mismatches
  respect Returns::mismatchMessage and the configured result exception factory.
  Control-flow cancellation and deadline exceptions retain their meaning.
- Diagnostics retain safe types and paths. Custom transformations mark a source
  boundary; a handler's private mapping is not presented as an exact JSON Pointer.
  Arbitrary exception messages are not copied into SDK logs. Logging performed by
  the handler itself does not automatically inherit the client's redaction policy.
- Custom work is part of hydration and the execution budget. Blocking user code
  cannot be interrupted in its middle; no extra HTTP attempt or trace is introduced.

Custom hydration does not change DTO::toArray(): explicit serialization follows the
DTO's declared output rules. See [lazy item consumption](../execution/pagination-items.md)
for preserving those objects while traversing pages.
