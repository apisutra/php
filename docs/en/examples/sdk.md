<!-- languages --> <a href="sdk.md">English</a> · <a href="../../ru/examples/sdk.md">Русский</a> <!-- /languages -->
# Example Records SDK <a id="section-1"></a>

Records is a small, runnable example for **authors of SDKs**. It shows how one client,
request and set of DTOs form a Composer package that works in plain PHP and Laravel.
Use it as a reference or copy the parts you need into your own namespace. Applications
using an existing provider SDK do not need to install this example.

The service is fictional: running the example uses local responses and makes no HTTP
calls. It does not require credentials, a server, a database or a queue worker.

## The API being described <a id="api"></a>

| Part | Example contract |
| --- | --- |
| Operation | GET /records/{id}; id is a path parameter |
| Success | The data object contains record_id, title, created_at and optional nested author.name |
| DTO | Integer ID, string title, DateTimeImmutable date, nullable authorName/description; unknown fields in _extra |
| Error | HTTP 404; inspected through resolved() in the same runnable script |
| Authentication | Optional Bearer token; the local example uses none |

The [fixtures](../../example/sdk/fixtures/record.json) are the source for this teaching
contract, not evidence about a real provider. The SDK has one resource and one operation;
advanced mechanisms remain in their [topic examples](README.md).

## Read it in this order <a id="learning-path"></a>

1. Run the example below and inspect the DTO and error it produces.
2. Follow run.php → client → resource → request → response DTO in the source table.
3. Adapt the URL, operation and mapping to confirmed responses from your own API.
   ClientConfigFactory supplies shared runtime defaults; its create() constructs a standalone config.
4. Install the same sources as a package, then follow the
   [Laravel SDK author guide](https://github.com/apisutra/laravel/blob/master/docs/en/guides/sdk/laravel.md)
   to add framework defaults and DI. No second client, DTO set or bridge package is needed.

## Install as a package <a id="section-2"></a>

`example/records-sdk` is a local example, not published on Packagist. In an application with a compatible ApiSutra version installed, run:

```bash
composer config repositories.records-example path vendor/apisutra/php/docs/example/sdk
composer require "example/records-sdk:@dev"
```

For a checkout, replace the vendor path with the absolute path to its `docs/example/sdk`. Configure the Composer repository in the application. SDK classes use the SDK's own PSR-4 configuration; Laravel loads the provider automatically. Settings, optional publishing, and DI are described in the [consumer guide](https://github.com/apisutra/laravel/blob/master/docs/en/guides/integration/laravel.md). When building a real SDK, replace the example name, namespace, and API data.

## Run <a id="section-3"></a>

From the ApiSutra checkout after `composer install`:

```bash
php docs/example/sdk/run.php
```

From an application with the package installed:

```bash
php vendor/apisutra/php/docs/example/sdk/run.php
```

If the example is installed as a separate package:

```bash
php vendor/example/records-sdk/run.php
```

Expected output:

```json
{"id":7,"title":"Первая запись","createdAt":"2026-09-15T10:30:00+00:00","authorName":"Анна","description":null,"extra":{"future_flag":false},"failed":true,"status":404}
```

## One SDK, three environments <a id="environments"></a>

| Environment | How to use the same classes |
| --- | --- |
| Plain PHP | Build DemoClient explicitly with ClientConfigFactory::create() and a transport; run.php demonstrates this |
| Laravel without apisutra/laravel | Use the same explicit construction or run.php; discovery and config:cache remain safe, automatic SDK client/request DI reports how to install the adapter |
| Laravel with apisutra/laravel | Discovery registers the client and requests; use app(DemoClient::class) or injection, with application configuration |

The SDK requires apisutra/php and only **suggests** apisutra/laravel. Composer does not
install suggestions. The application explicitly installs the adapter when it wants
Laravel integration. A Laravel-only SDK can instead require the adapter. Neither variant
needs a separate SDK bridge package.

The Laravel factory reuses ClientConfigFactory::defaults() through the adapter's
ClientConfigFactory. Application debug/environment defaults are read during construction;
containerProvider stays null so the config does not pin the Application. Explicit client,
transport and already-bound request overrides remain the application's choice.

## Source files <a id="section-4"></a>

| File | Purpose |
| --- | --- |
| [composer.json](../../example/sdk/composer.json) | Installation, PSR-4, and Laravel package discovery |
| [config/records.php](../../example/sdk/config/records.php) | Defaults, environment, and optional publishing |
| [bootstrap.php](../../example/sdk/bootstrap.php) | Autoloading the example namespace |
| [run.php](../../example/sdk/run.php) | Explicit construction, two calls, and reading results |
| [DemoClient](../../example/sdk/src/DemoClient.php) | The `records()` entry point |
| [ClientConfigFactory](../../example/sdk/src/Config/ClientConfigFactory.php) | Shared runtime defaults and standalone config creation |
| [HydrationConfigFactory](../../example/sdk/src/Config/HydrationConfigFactory.php) | Strict types and collection of unknown fields in `_extra` |
| [RecordsResource](../../example/sdk/src/Resources/Records/RecordsResource.php) | Bound request creation |
| [GetRecordRequest](../../example/sdk/src/Resources/Records/Get/GetRecordRequest.php) | GET, path parameter, typed response, and retries on temporary errors |
| [GetRecordResponseDto](../../example/sdk/src/Resources/Records/Get/GetRecordResponseDto.php) | AbstractResponseDto subclass: From with fallback and nested path, DateTimeFrom, EmptyStringAsNull |
| [RecordDto with an attribute](../../example/sdk/src/AttributeExample/RecordDto.php) | DTO creation through from() without a client or external rules |
| [Laravel provider](../../example/sdk/src/Laravel/DemoServiceProvider.php) | Lazy client, overrides, and request registration |
| [LaravelClientConfigFactory](../../example/sdk/src/Laravel/LaravelClientConfigFactory.php) | Application defaults and auth without pinning the container |
| [Success](../../example/sdk/fixtures/record.json), [error](../../example/sdk/fixtures/error.json) | Synthetic local responses |

`run.php` neither loads Laravel nor makes real HTTP calls. Checks execute this published
file, including after installation without dev dependencies. The three environments above
are covered by installation checks; Laravel additionally checks discovery, request-first DI,
overrides, publishing, config caching and two Application instances. The SDK source is shared
between repositories; its Laravel checks use the selected core revision.

## Use as a starting point <a id="section-5"></a>

Copy the classes you need into your SDK namespace and register PSR-4 in Composer. Replace fixtures with confirmed API responses. [Quickstart](https://github.com/apisutra/php/blob/master/docs/en/guides/quickstart.md) explains execution; [creating an SDK](https://github.com/apisutra/php/blob/master/docs/en/start/create-sdk.md) describes the complete expansion workflow. For real transport, follow [standalone setup](https://github.com/apisutra/php/blob/master/docs/en/guides/integration/standalone.md). To ship a Laravel provider, follow the [SDK author guide](https://github.com/apisutra/laravel/blob/master/docs/en/guides/sdk/laravel.md).
