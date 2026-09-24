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
| Success | The data object contains record fields, author/contact, tags, assets and metadata |
| DTO | Readonly DTO graph, enum, dates, typed collection and discriminator variants; unknown fields in _extra |
| Error | HTTP 404 via resolved(); twelve malformed HTTP 200 responses via raw() with field diagnostics |
| Authentication | Optional Bearer token; the local example uses none |

The [fixtures](../../example/sdk/fixtures/record.json) are the source for this teaching
contract, not evidence about a real provider. The SDK has one resource and one operation;
DTO features are covered below; other mechanisms have [topic examples](README.md).

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

The output is formatted JSON with seven sections:

| Section | What to inspect |
| --- | --- |
| dto | Author/contact objects, enum value/title, tag names, attachment types, 185 seconds from `03:05`, exact large ID and file preview |
| serialized | Actual `toArray()` of the entire graph: mapped names, UTC dates, enum value, cast back to `03:05`, Base64 and unknown fields |
| copy | A new title from `with()` alongside the unchanged original |
| standalone | Equality with explicit Hydrator output; the small attribute-only model's `from()` |
| defaults | Fallback ID, missing/null title, absent collection, nullable date, revision 0 and an explicit author name overriding its default provider |
| httpError | `failed: true`, `status: 404` |
| hydrationErrors | Twelve failures: code/reason, DTO path, original sourcePath/kind and HTTP status 200 |

## DTO features in this SDK <a id="dto-features"></a>

Start with the [response model](../../example/sdk/src/Resources/Records/Get/GetRecordResponseDto.php)
and its [JSON fixture](../../example/sdk/fixtures/record.json). All its supporting types
belong to `Resources/Records/Get/`; nested models are in `Dto/`.

| Feature | Where it is visible |
| --- | --- |
| From + fallback, To, Map | record_id/id, state/status and outgoing field names |
| Nested path | metrics.rating becomes rating; unread metrics.votes remains in _extra |
| Readonly and ConstructorValue | kind=record; attachment types are checked against constructor-assigned values |
| Nested DTO graph | AuthorDto → ContactDto, including extras at both levels |
| Default provider | Missing display_name is built from first_name/last_name; explicit input wins |
| Typed collection | TagCollection validates TagDto items and provides first/count/mapToArray; missing tags becomes an empty collection |
| ListShape and variants | related_ids requires integers; assets[*].value.type selects an image or document DTO |
| No silent item loss | Unknown attachment variants fail; wrapper rank and extra variant fields survive in _extra |
| Dates and output timezone | DateTimeFrom validates DATE_ATOM; DateTimeTo serializes the same instant in UTC |
| Enum | RecordStatus provides a typed value and title(); DtoSerialize selects the raw value for toArray() |
| Custom bidirectional cast | ReadingTimeCast converts MM:SS ↔ seconds with bounds and format checks |
| Inline file | DataUriBase64FileCast creates Base64File; toArray() returns plain Base64. This small preview is materialized, not streamed |
| Missing/null/defaults | DefaultValue for title, EmptyStringAsNull for description/phone, nullable updatedAt, ForbidExplicitNull for revision |
| Strict scalars and exact IDs | Numeric strings fail for int fields; external_id stays a string with all digits |
| Preserved extra data | Root, author, contacts, tags, variants and each-wrapper remainders retain false, zero, null and empty lists |
| Serialization and copies | toArray() applies attributes recursively; with() creates a shallow immutable copy |
| Diagnostics | data.author.contact.email points to /data/author/contact/email; attachment paths include their original value wrapper |

For example, after extracting `$record` in run.php:

```php
$name = $record->author->displayName;
$email = $record->author->contact->email;
$firstTag = $record->tags->first()?->name;
$statusTitle = $record->status->title();
$array = $record->toArray();
$renamed = $record->with(title: 'Обновлённая запись');
```

`toArray()` is the declared DTO representation, not a byte-for-byte reconstruction
of the response: input wrappers are projected into DTOs, names/formats follow output
rules, and unknown values are retained in `_extra`. It is not automatically a request
body. [HTTP serialization has its own rules](../reference/serialization/dto-output.md).
The main README keeps a shortened model; [additional DTO examples](../guides/dto/showcase.md)
cover other model styles and request serialization.

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
| [run.php](../../example/sdk/run.php) | DTO graph, serialization, standalone, defaults and failures |
| [DemoClient](../../example/sdk/src/DemoClient.php) | The `records()` entry point |
| [ClientConfigFactory](../../example/sdk/src/Config/ClientConfigFactory.php) | Shared runtime defaults and standalone config creation |
| [HydrationConfigFactory](../../example/sdk/src/Config/HydrationConfigFactory.php) | Strict types and collection of unknown fields in `_extra` |
| [RecordsResource](../../example/sdk/src/Resources/Records/RecordsResource.php) | Bound request creation |
| [GetRecordRequest](../../example/sdk/src/Resources/Records/Get/GetRecordRequest.php) | GET, path parameter, typed response, and retries on temporary errors |
| [GetRecordResponseDto](../../example/sdk/src/Resources/Records/Get/GetRecordResponseDto.php) | Root DTO with mapping, dates, shapes, extras and output rules |
| [AuthorDto](../../example/sdk/src/Resources/Records/Get/Dto/AuthorDto.php), [ContactDto](../../example/sdk/src/Resources/Records/Get/Dto/ContactDto.php) | Nested models, defaults and extras |
| [TagDto](../../example/sdk/src/Resources/Records/Get/Dto/TagDto.php), [TagCollection](../../example/sdk/src/Resources/Records/Get/Dto/TagCollection.php) | Typed collection items and container |
| [ImageAttachmentDto](../../example/sdk/src/Resources/Records/Get/Dto/ImageAttachmentDto.php), [DocumentAttachmentDto](../../example/sdk/src/Resources/Records/Get/Dto/DocumentAttachmentDto.php) | Discriminator variants, fixed types and inline Base64 |
| [RecordStatus](../../example/sdk/src/Resources/Records/Get/RecordStatus.php) | Backed enum with a readable title |
| [ReadingTimeCast](../../example/sdk/src/Resources/Records/Get/ReadingTimeCast.php), [AuthorDisplayNameProvider](../../example/sdk/src/Resources/Records/Get/AuthorDisplayNameProvider.php) | Explicit custom conversion and derived default |
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
