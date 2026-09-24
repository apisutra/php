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

Output format: `dto` contains values and types through observable properties; `dx` is toArray(); `wire` is the sent request's JSON; `fallback` shows the fallback key and defaults; `errors` contains error reasons and paths; `conflictRejected` shows rejection of overlapping declarations.

[Choose a DTO declaration approach](../start/describe-dto.md) · [All examples](README.md).
