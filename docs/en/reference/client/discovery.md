<!-- languages --> <a href="discovery.md">English</a> · <a href="../../../ru/reference/client/discovery.md">Русский</a> <!-- /languages -->
# Finding the client for a request <a id="section-1"></a>

This mechanism automatically finds a request's owner from its namespace, without
passing a client manually.

## Components <a id="section-2"></a>
- **`ClientRegistry`** stores `namespace → Client` mappings.
- **ClientResolver** finds a client by request class.
- **ClientDiscoveryService** discovers requests and registers them.

## Resolution <a id="section-3"></a>
1) Read the request class namespace.
2) Select the **longest matching** namespace in `ClientRegistry`.
3) If there is no match, throw `ConfigurationException`.

This safely supports nested structures.

## Simple manual registration <a id="section-4"></a>
```php
use ApiSutra\Resolver\ClientRegistry;

$registry = new ClientRegistry();
$registry->register($client, 'Vendor\\Package\\Requests');
```

## Automatic discovery <a id="section-5"></a>
Discovery scans the client namespace for request classes.
It uses Composer's classmap when available, with a PSR-4 scan as fallback.

```php
use ApiSutra\Resolver\ClientDiscoveryCache;
use ApiSutra\Resolver\ClientDiscoveryService;
use ApiSutra\Resolver\ClassMapProvider;
use ApiSutra\Resolver\DiscoveryOptions;
use ApiSutra\Resolver\RequestNamespaceDetector;
use ApiSutra\Resolver\RequestScanner;

$registry = new ClientRegistry();
$detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
$cache = new ClientDiscoveryCache($psr16Cache);
$service = new ClientDiscoveryService($registry, $detector, $cache);

$service->registerAuto($client, DiscoveryOptions::auto());
```

### Scan scope <a id="section-6"></a>
1) The client's **root namespace** (first two segments: `Vendor\\Package`).
2) If nothing is found, fall back to `Vendor\\Package\\Requests` and `Vendor\\Package\\Resources`.

### Discovery cache <a id="section-7"></a>
`DiscoveryOptions` controls caching:
- `Auto` — enabled in `Production/Staging`, disabled in `Local/Testing`.
- `ForceOn` — always enabled.
- `ForceOff` — always disabled.

Additional settings:
- `cacheTtl` — cache TTL.
- `cacheKeyVersion` — a manual key version for invalidation.

Defaults: `cacheTtl = null` (use the store TTL), `cacheKeyVersion = null` (not included
in the key). Set `cacheTtl` for expensive scans or many clients. Use `cacheKeyVersion`
after namespace structure changes to force cache invalidation on deployment.

The cache key includes ClientClass + `cacheKeyVersion` + Composer checksum.

## Multi-service clients <a id="section-8"></a>
If a provider has several service clients, register each service's namespaces through
`ServiceRegistrar`. `RequestNamespaceDetector` is used by default; supply a manual list
through `RequestNamespaceProviderInterface` when needed.

```php
use ApiSutra\Resolver\ServiceRegistrar;

$registrar->register($mega->services());
```

In Laravel, `SdkServiceProvider` does this automatically when resolving `MultiServiceClientInterface`.

## Automatic resolution in requests <a id="section-9"></a>
`AbstractRequest` attempts to obtain `ClientResolverInterface` from the container through
`ContainerProviderRegistry`. If found, the request resolves its client through `ClientRegistry`.

Without a container or registered resolver, either:
- Send through the client: `$client->send($request)`.
- Bind the client manually: `$request->setClient($client)`.

## Recommendations <a id="section-10"></a>
- Enable Composer classmaps in production for fast scanning.
- In large projects, use `cacheKeyVersion` to invalidate the cache manually after namespace structure changes.
