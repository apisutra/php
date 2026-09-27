<!-- languages --> <a href="dto-showcase.md">English</a> · <a href="../../ru/examples/dto-showcase.md">Русский</a> <!-- /languages -->
# DTO capability catalog <a id="section-1"></a>

One fictional product demonstrates attributes and a shared policy, nested models, a typed collection, element variants, a Base64/data URI file, a custom cast, a provider, extras, and errors. The [DTO overview](../guides/dto/showcase.md) provides a step-by-step explanation and result tables.

## Run <a id="section-2"></a>

From the checkout after `composer install`:

```bash
php docs/example/dto-showcase/run.php
```

From a project with the package installed:

```bash
php vendor/apisutra/php/docs/example/dto-showcase/run.php
```

Network access and API keys are unnecessary: MockTransport records requests and returns a fixture. The run compares standalone hydration with Returns, sends a DTO through BodyRoot, and collects ten hydration errors. The file becomes a `Base64File` containing `SDK manual`; `toArray()` and sending return plain Base64 without the data URI prefix. [Expected values](../../example/dto-showcase/fixtures/expected.json) are defined separately; documentation checks compare them with the result and verify that overview declarations match the code.

## Source files <a id="section-3"></a>

| File | Purpose |
| --- | --- |
| [CatalogItemDto](../../example/dto-showcase/src/CatalogItemDto.php) | Main model with comments on each technique |
| [CatalogHydration](../../example/dto-showcase/src/CatalogHydration.php) | Shared Strict without a model registry |
| [SellerDto](../../example/dto-showcase/src/SellerDto.php) | Nested plain DTO with its own remainder |
| [TagDto](../../example/dto-showcase/src/TagDto.php), [TagCollection](../../example/dto-showcase/src/TagCollection.php) | Typed collection |
| [ImageDto](../../example/dto-showcase/src/ImageDto.php), [VideoDto](../../example/dto-showcase/src/VideoDto.php) | Two media variants |
| [ItemStatus](../../example/dto-showcase/src/ItemStatus.php) | Backed enum |
| [MinorUnitsCast](../../example/dto-showcase/src/MinorUnitsCast.php) | Exact conversion of a price string to integer hundredths and back |
| [DisplayNameProvider](../../example/dto-showcase/src/DisplayNameProvider.php) | Computes a missing value from source data |
| [CatalogClient](../../example/dto-showcase/src/CatalogClient.php) | Client with the same shared policy |
| [GetCatalogItemRequest](../../example/dto-showcase/src/GetCatalogItemRequest.php) | Returns with unwrap |
| [SaveCatalogItemRequest](../../example/dto-showcase/src/SaveCatalogItemRequest.php) | DTO serialization into the request body |
| [item.json](../../example/dto-showcase/fixtures/item.json), [expected.json](../../example/dto-showcase/fixtures/expected.json) | Input data and expected behavior |
| [run.php](../../example/dto-showcase/run.php) | Construction, transformations, result comparison, and error scenarios |

Output format: `dto` contains values and types through observable properties; `dx` is toArray(); `wire` is the sent request's JSON; `fallback` shows the fallback key and defaults; `errors` contains error reasons and paths.

## DTO variants and original JSON <a id="json-mapping"></a>

The [example Records SDK](sdk.md) adds the 0.3 mechanisms to this catalog: variants on a type,
a typed fallback, shape validation before a cast, and a public JSON entry point for webhooks.
Run it from an installed package:

```bash
php vendor/apisutra/php/docs/example/sdk/run.php
```

In the [original response](../../example/sdk/fixtures/record.json), the `assets` list contains
an image, a document, and an `audio` type the SDK does not yet know. The `cover` field is a single image.
One declaration on the [shared attachment type](../../example/sdk/src/Resources/Records/Get/Dto/AttachmentDto.php)
serves both fields and root JSON:

```php
namespace Example\Records\Resources\Records\Get\Dto;

use ApiSutra\Attributes\DataTransfer\DtoVariants;
use ApiSutra\DataTransfer\AbstractDto;

#[DtoVariants(
    discriminator: 'type',
    map: ['image' => ImageAttachmentDto::class, 'document' => DocumentAttachmentDto::class],
    unknown: RawAttachmentDto::class,
)]
abstract readonly class AttachmentDto extends AbstractDto {}
```

The variant classes in this namespace live in the same SDK directory.
`ImageAttachmentDto` and `DocumentAttachmentDto` extend the shared type and describe known fields.
[RawAttachmentDto](../../example/sdk/src/Resources/Records/Get/Dto/RawAttachmentDto.php) extends it too,
but captures the entire unknown node in `#[Extras] public array $raw`, including type, false, null, and empty lists.
An invalid field in a known model remains an error: the fallback handles unknown tags.

| Location in the response model | Declaration and result |
| --- | --- |
| assets | `ListShape(new DtoShape(AttachmentDto::class), each: 'value')` extracts the node from each wrapper; rank remains in extras |
| cover | `?AttachmentDto` selects a variant from the same map without Shape or a separate cast |
| localized_titles | `InputShape(ContainerShape::Object)` checks for an object before LocalizedTitlesCast; the cast validates language codes and string titles |

The [response model](../../example/sdk/src/Resources/Records/Get/GetRecordResponseDto.php) has the full attributes and imports.
For models you own, the attribute is enough; no config registry is required.
If a class cannot be modified, [HydrationRules::withVariants()](../reference/dto/variants.md) provides the same declaration
through external HydrationConfig rules. Choose one of these approaches per type.

Do not call `json_decode()` on a webhook first: conversion to associative arrays loses the distinction
between empty `{}` and `[]`. After loading the example's bootstrap.php:

```php
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Serialization\Hydrator;
use Example\Records\Config\ClientConfigFactory;
use Example\Records\Resources\Records\Get\Dto\AttachmentDto;
use Example\Records\Webhook\RecordPayload;

$config = ClientConfigFactory::create(); // The HTTP client receives this same config.
$hydrator = Hydrator::forConfig($config->hydration ?? new HydrationConfig(), $config->localization);
// $rawJson is the original webhook body with a {"data": {...}} envelope.
$payload = $hydrator->hydrateJson($rawJson, RecordPayload::class);
$record = $payload->data;
$unknown = $hydrator->hydrateJson('{"type":"audio","duration":12}', AttachmentDto::class);
// $unknown is RawAttachmentDto; raw contains type and duration.
```

JSON shape validation is enabled by default; the [shared setting](../reference/dto/configuration.md)
applies equally to HTTP and hydrateJson(). The example sends seventeen malformed bodies
through both entry points and displays their reason and sourcePath side by side:

| Data | Result |
| --- | --- |
| Unknown `type: "audio"` | RawAttachmentDto; its value also survives toArray() |
| Known `type: "image"`, but `width: "640"` | Strict int validation fails, without falling back |
| `type: true` | invalid_discriminator_type |
| `localized_titles: []` | invalid_object_shape before the cast; an empty object `{}` is accepted |
| `localized_titles: {"en": 17}` | invalid_localized_titles from the cast |
| `related_ids: {}` or `{"0": 11, "1": 12}` | invalid_list_shape; numeric keys still require a list |
| `cover: []` | DTO shape rejection with sourcePath `/data/cover` |

`raw` stores PHP values, not the original bytes or the shape of every JSON node.
`toArray()` follows DTO serialization rules: here an unknown attachment becomes an object with a raw field.
For existing PHP data, use [hydrate()](../reference/dto/configuration.md#json-input);
passing a new array from a cast does not automatically carry the original JSON shape.

[Choose a DTO declaration approach](../start/describe-dto.md) · [All examples](README.md).
