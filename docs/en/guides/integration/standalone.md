<!-- languages --> <a href="standalone.md">English</a> · <a href="../../../ru/guides/integration/standalone.md">Русский</a> <!-- /languages -->
# Use without Laravel <a id="section-1"></a>

The client constructor explicitly accepts `ClientConfig` and `TransportInterface`.
Resource calls need no container: the resource binds each request it creates to the client.

## Verify assembly without network access <a id="section-2"></a>

The [tutorial SDK](../../examples/sdk.md) passes `MockTransport` and
[configuration](../../../example/sdk/src/Config/ClientConfigFactory.php) to `DemoClient`.
Run it first, then replace the settings with those of your API.

## Real HTTP <a id="section-3"></a>

The standard transport requires the Guzzle HTTP client and PHP's `ext-curl` extension:

```bash
composer require guzzlehttp/guzzle:^7
```

Setup snippet after loading Composer's autoloader and your SDK's autoloader:

```php
use ApiSutra\Transport\HttpTransport;
use Example\Records\Config\ClientConfigFactory;
use Example\Records\DemoClient;

$client = new DemoClient(
    ClientConfigFactory::create('https://api.example.test'),
    HttpTransport::createDefault(),
);
```

Replace the example URL with the real address. Creating a transport does not itself
perform HTTP. For another PSR-18 client, pass it with PSR-17 factories to
`HttpTransport`. The [transport contract](../../reference/execution/transport.md)
covers support for timeouts, redirects, and streams.

## Optional integrations <a id="section-4"></a>

External caching uses PSR-16. Without it, the client retains only the available local
state of its mechanisms; [response caching](../../reference/execution/cache.md) is opt-in.
`#[Validate]` requires an Illuminate Validation factory, which can be passed through
`Validator::useFactory()` without a Laravel application. See [setup and precedence](../../reference/client/validation.md).

Connect your own container through [ContainerProviderInterface](../../reference/client/construction.md).
This is a separate capability, not a prerequisite for ordinary requests.
