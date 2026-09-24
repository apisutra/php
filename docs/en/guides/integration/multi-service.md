<!-- languages --> <a href="multi-service.md">English</a> · <a href="../../../ru/guides/integration/multi-service.md">Русский</a> <!-- /languages -->
# Multiple services in one SDK <a id="section-1"></a>

A mega-client is a facade over **multiple service clients**, each with its own
`ClientConfig` (baseUrl/auth/pagination). The facade does not mix configurations;
it only routes calls to the appropriate service.

## Terminology <a id="section-2"></a>
Definitions: [Glossary: architecture](../../glossary/architecture.md#multi-service-terminology).

In brief, multiple services means several **Service** instances belonging to one **Vendor**
with **different global settings** (baseUrl/auth/pagination/serialization).
If there are no differences, use one client with resources.

## When to use it <a id="section-3"></a>
- Several **different** API services from one vendor.
- Different baseUrl, authentication keys, pagination rules, or serialization rules.
- Separate API domains/versions requiring independent configurations.

## When it is unnecessary <a id="section-4"></a>
- If global settings match, use one client with resources.

## Service client (separate configuration) <a id="section-5"></a>
```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Core\AbstractClient;

final class RealtyClient extends AbstractClient
{
    public function __construct(ClientConfig $config, TransportInterface $transport)
    {
        parent::__construct($config, $transport);
    }
}
```

## Zero-config service registration <a id="section-6"></a>
`ServiceRegistrar` registers each service client's namespaces in `ClientRegistry`.
If no manual override is needed, do not configure anything else on the client.
Outside Laravel, register manually through a container or directly.

```php
use ApiSutra\Resolver\ServiceRegistrar;
use ApiSutra\Resolver\ClientRegistry;
use ApiSutra\Resolver\RequestNamespaceDetector;

// $registry and $detector are obtained from the container.
$registrar = new ServiceRegistrar($registry, $detector);
$registrar->register($mega->services());
```

## Optional namespace override <a id="section-7"></a>
If auto-detection does not fit your nonstandard namespaces, implement
`RequestNamespaceProviderInterface` on the service client:
```php
use ApiSutra\Contracts\Interfaces\Resolver\RequestNamespaceProviderInterface;

final class LegacyClient extends AbstractClient implements RequestNamespaceProviderInterface
{
    public function requestNamespaces(): array
    {
        return [
            'Vendor\\Legacy\\Requests',
            'Vendor\\Legacy\\Resources',
        ];
    }
}
```

## Laravel integration <a id="section-8"></a>
`SdkServiceProvider` automatically calls `ServiceRegistrar` when the container resolves
an object implementing `MultiServiceClientInterface`.
You only need to register the mega-client and service clients in the container.

## Service versions (optional) <a id="section-9"></a>
If a service gains versions (`v1/v2/v3`), the recommended DX is:
- Canonical: `->service()->v3()->resource()->method()`.
- Alternative: `->service()->useVersion(ServiceVersion::V3)->resource()->method()`.

The complete strategy and selection rules are in
[service versioning](../../reference/client/versioning.md).

To reduce boilerplate, you can use `VersionedResourceTrait`:
- Enum-only version switching through `v(UnitEnum $version)`.
- Unified resource/request routing through a version map.
- `UnsupportedVersionException` for an unsupported version.

The trait is optional and does not dictate structure; business methods remain in resources.

## Recommendations and limitations <a id="section-10"></a>
- Do not register facade namespaces: only service clients are registered.
- If services have different global settings, use **separate** clients.
- With multiple baseUrl values, account for `RateLimitConfig::key`.

## Calling through the SDK facade <a id="section-11"></a>

```php
$result = $mega->records()->reports()->get($id)->send();
$status = $mega->billing()->status()->check($account)->send();
```

Facade methods belong to the SDK and return its service clients.
