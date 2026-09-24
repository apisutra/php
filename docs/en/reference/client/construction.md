<!-- languages --> <a href="construction.md">English</a> · <a href="../../../ru/reference/client/construction.md">Русский</a> <!-- /languages -->
# Client construction and dependencies <a id="section-1"></a>

Configure container integration through `ContainerProviderInterface`.

## Default behavior <a id="section-2"></a>
The core does not detect a framework. Outside an active execution, resolution order is
call/client override → explicit `ContainerProviderRegistry::set()` → integration default
(`setDefault()` or `setDefaultResolver()`) → `NullContainerProvider`.
An execution retains its selected provider across nested calls and Fiber suspensions;
an explicit call/client override still takes priority. New registrations affect subsequent
executions. `reset()` clears explicit/default registrations and the default resolver;
it preserves active scopes until they exit through `finally`, including suspended Fibers.
Null in ClientConfig uses the registry; an explicit NullContainerProvider disables container access.


During canonical execution, request hooks read the executing client through
`getClient()` or the protected `$client` property. This binding is isolated per Fiber
and nested invocation; leaving execution restores the permanent binding. Explicit
`setClient()` still validates namespace ownership.

## Without Laravel <a id="section-3"></a>
No action is needed if you:
- Pass the client explicitly when sending (`$client->send($request)`) or through `$request->setClient($client)`.
- Do not use automatic client resolution or container-level DTO validation.

Without a container:
- `ClientResolverInterface` is not found automatically.
- Validate declarations without a configured factory produce `configuration_error`;
  requests without these rules do not need a factory.
- `ClientDiscoveryService` takes `basePath` from `getcwd()`.
- Set `debug/environment` manually in `ClientConfig`.

If you need a container, connect a custom provider. It enables automatic client
resolution and `basePath`/`environment` lookup without Laravel. Validation needs only a factory.

For validation without an application container, configure a factory through
`Validator::useFactory()`; see the [standalone example and priorities](validation.md#section-5).
A Laravel application is not required; Illuminate Validation components are needed
only when using their rules.

## Override through ClientConfig <a id="section-4"></a>
```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;

final class ExampleContainerProvider implements ContainerProviderInterface
{
    public function bound(string $id): bool { return false; }
    public function make(string $id): ?object { return null; }
    public function basePath(): ?string { return null; }
    public function environment(): ?string { return null; }
    public function isDebug(): ?bool { return null; }
    public function validatorFactory(): ?object { return null; }
}

$provider = new ExampleContainerProvider();

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    containerProvider: $provider,
);
```

An explicit client `containerProvider` determines the factory used to validate its
requests. Null from `validatorFactory()` does not fall back to the global factory:
when rules exist, this is a configuration error. Manual validation of an already
bound request uses the same source. For a standalone DTO, pass the provider explicitly
to `Validator::check($dto, provider: $provider)`.

## Global configuration (bootstrap) <a id="section-5"></a>
```php
use ApiSutra\Support\ContainerProviderRegistry;

ContainerProviderRegistry::set($provider);
```

## Purpose <a id="section-6"></a>
The provider affects:
- `ClientResolver` for automatic request client resolution.
- `Validator` for requests and DTOs under [context selection rules](validation.md#section-6).
- `ClientDiscoveryService` for `basePath`.

The provider can also resolve `transport` automatically in high-level client factories
through `TransportResolver::resolve(...)`. This is useful for `Client::make(...)`, but
does not change the low-level constructor contract, which requires explicit `transport`.

With `apisutra/laravel` installed, package discovery registers the provider without mandatory config
publishing. Ordinary DI preserves SDK request values; explicit RequestFactory maps
incoming HTTP data. Custom bindings take priority.
See [setup and testing](https://github.com/apisutra/laravel/blob/master/docs/en/guides/integration/laravel.md).

`DefaultTransportFactory::create(ContainerProviderInterface $app)` builds HttpTransport
from PSR-18/PSR-17 container bindings, with a Guzzle fallback. It does not
register a transport or alter TransportResolver's explicit/container selection.
Laravel uses this factory for its lazy default binding; standalone code can call it
explicitly. Invalid bindings raise ConfigurationException before fallback.

## Mega-client contract <a id="section-7"></a>
`MultiServiceClientInterface` requires a list of service clients.
```php
use ApiSutra\Contracts\Interfaces\Core\MultiServiceClientInterface;

final readonly class MegaClient implements MultiServiceClientInterface
{
    public function __construct(
        private RealtyClient $realty,
        private TaxClient $tax,
    ) {}

    public function services(): array
    {
        return [$this->realty, $this->tax];
    }
}
```
