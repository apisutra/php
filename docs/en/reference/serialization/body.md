<!-- languages --> <a href="body.md">English</a> · <a href="../../../ru/reference/serialization/body.md">Русский</a> <!-- /languages -->
# HTTP request body <a id="section-1"></a>

## Request DateTime <a id="section-2"></a>
```php
use ApiSutra\Config\DateTimeSerializationPolicy;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    requestDateTime: new DateTimeSerializationPolicy(
        format: DATE_ATOM,
        timezone: null,
    ),
);
```

- `requestDateTime` applies to request-level serialization: query, header, path,
  and request body fields that do not pass through the DTO body serializer.
- `timezone` converts the date to the specified zone before formatting.
- `requestDateTime` does not define DTO hydration/body semantics; external input
  rules are connected separately through `ClientConfig::hydration`.

For union fields (`string|DateTimeInterface`), the branch is selected from the runtime
value, not the order of types in the property declaration.

Large integer JSON identifiers are preserved automatically. No ClientConfig parameter
is required: provider DTOs choose string or int|string; int overflow causes an error.
See [large integers in responses](../dto/scalars.md#section-4) for the contract.

For detailed behavior, see [request serialization](request-parts.md).

## Request-level enum serialization <a id="section-3"></a>

Keep query/header/path enum policy in the client.

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Serialization\EnumOutput;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    dtoSerializationProfile: $dtoProfile,
    wireBodySerializationPolicy: new DtoSerializationPolicy(enumOutput: EnumOutput::Value),
    requestPartsEnumOutput: EnumOutput::Value,
);
```

Recommended configuration:
- DX / `toArray()` → `DtoSerializationProfile`
- Wire body → `wireBodySerializationPolicy`
- Query/header/path → request-level config

See [request serialization](request-parts.md).

## Body and nested <a id="section-4"></a>
```php
use ApiSutra\Attributes\Request\Body;

#[Body('payload.user')]
public array $user;
```

`nested` supports dot paths. When `nested` is empty, the field name is used.

## Root body (JSON Patch / bulk) <a id="section-5"></a>
```php
use ApiSutra\Attributes\Request\BodyRoot;

#[BodyRoot]
public array $operations; // [{...}, {...}]
```

In this mode, the property value becomes the body root (for example `[...]`),
without being wrapped in an object.

## JSON encoding errors <a id="section-6"></a>

Outgoing JSON bodies, Base64 JSON, multipart JSON values, and `JsonCast::serialize()`
are encoded strictly. Invalid UTF-8, INF/NAN, unsupported values, and cyclic structures
produce `SerializationException` and `serialization_error` before HTTP. If `json_encode`
fails, the original `JsonException` is available as `previous`. Nested array traversal
has a 512-level limit, which also stops cyclic arrays before final encoding. DTO
graphs are checked for object re-entry in the current branch and depth limits;
reusing one DTO in independent fields is allowed.

The SDK does not silently repair or replace data. JSON false and [] are preserved;
query remains a separate request part. Binary and ordinary text multipart fields
are not validated as JSON. See [error handling](../results/handles.md) for result/exception delivery.

## Streaming file bodies <a id="section-7"></a>

Binary and multipart are sent through `PreparedRequest.stream` from the source's
current position; binary does not place the file in `PreparedRequest.body`.
Base64 JSON still fully materializes its contents. See [files](../../guides/recipes/files.md)
for details.
