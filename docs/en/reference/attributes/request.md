<!-- languages --> <a href="request.md">English</a> · <a href="../../../ru/reference/attributes/request.md">Русский</a> <!-- /languages -->
# Request declaration and composition <a id="section-1"></a>

## Signatures and targets <a id="section-2"></a>

Class names belong to `ApiSutra\Attributes\Request`.
Signatures show constructor parameters and defaults; target identifies where the
attribute is allowed. Behavior and priorities are covered in the topic links below.

| Attribute | Target | Constructor |
| --- | --- | --- |
| AuthScope | CLASS | `AuthScope(string\|BackedEnum $scope)` |
| Body | PROPERTY | `Body(?string $nested = null)` |
| BodyRoot | PROPERTY | `BodyRoot()` |
| File | PROPERTY | `File(?string $name = null, FileFormat $format = FileFormat::Multipart)` |
| Header | PROPERTY | `Header(string $name)` |
| Ignore | PROPERTY | `Ignore()` |
| OperationDescriptor | CLASS | `OperationDescriptor(?string $title = null, ?string $description = null, ?string $note = null)` |
| Path | PROPERTY | `Path(?string $name = null)` |
| Query | PROPERTY | `Query(?string $name = null, ?QueryArrayFormat $arrayFormat = null, ?bool $nullable = null)` |
| RequestDefaults | CLASS | `RequestDefaults(RequestUnmappedTarget $unmapped = RequestUnmappedTarget::Convention)` |
| RequestDiscriminator | CLASS | `RequestDiscriminator(string $field, array $map)` |
| RequestOneOf | CLASS / REPEATABLE | `RequestOneOf(string $name, array $variants, array $requiredCommon = [], OneOfMode $mode = OneOfMode::ExactlyOne)` |
| <a id="skipcredentialsenrichment"></a> SkipCredentialsEnrichment | CLASS | `SkipCredentialsEnrichment()` |

These attributes define request data sources (query/body/path/header/file),
class-level request defaults, and request-level DX metadata.

## When to use them <a id="section-3"></a>
- **Query** — filters/search and URL parameters.
- **Body** — the main payload (POST/PUT/PATCH).
- **`BodyRoot`** — root payload, such as `[...]` for JSON Patch.
- **Path** — URL substitution (`/users/{id}`).
- **Header** — service values such as idempotency or trace ID.
- **File** — file uploads.
- **Ignore** — exclude a field from serialization.
- **`RequestDefaults`** — class-level defaults for unannotated properties.
- **`RequestOneOf`** — declarative payload oneOf contract.
- **RequestDiscriminator** — connect a discriminator value to a oneOf variant.
- **OperationDescriptor** — readable request DX metadata (title, description, note).
- **AuthScope** — select the request's auth scope.

## Query <a id="section-4"></a>
**Target:** property.
**Parameters:**
- `name?: string` — parameter name, defaulting to the property name.
- `arrayFormat?: QueryArrayFormat` — array format.
- `nullable?: ?bool` — include null in query; null inherits configuration.

Without `arrayFormat`, `ClientConfig::queryArrayFormat` applies. `nullable` defaults to
null, inheriting `ClientConfig::serializeNulls` (false). Explicit true includes `key=`;
false excludes null regardless of configuration. See [serialization](../serialization/uri-query.md#section-6)
for booleans, empty values, and lists.

Example:
```php
use ApiSutra\Attributes\Request\Query;

#[Query('q')]
public string $query;
```

## Body <a id="section-5"></a>
**Target:** property.
**Parameters:** `nested?: string` — nesting path within the body.

Without `nested`, the value is written at the body root.

Example:
```php
use ApiSutra\Attributes\Request\Body;

#[Body('payload')]
public array $payload;
```

## BodyRoot <a id="section-6"></a>
**Target:** property.
**Parameters:** none.
**Purpose:** use the property's value as the **entire** request body.

For endpoints requiring a root array/scalar, such as JSON Patch:
```php
use ApiSutra\Attributes\Request\BodyRoot;

#[BodyRoot]
public array $operations;
```

Restrictions:
- Only one `BodyRoot` per request class.
- Cannot be combined with Body or fields routed to body by default.
- Cannot be combined with File in one request.

Path/Query/Header may be combined with `BodyRoot`.

## Path <a id="section-7"></a>
**Target:** property.
**Parameters:** `name?: string` — placeholder name in path.

Without name, the property name is used. If the property already matches the endpoint
placeholder (`{id}` -> `$id`), Path may be omitted. Use `#[Path('...')]` when names differ.

Example:
```php
use ApiSutra\Attributes\Request\Path;

#[Path('id')]
public int $userId;
```

## Header <a id="section-8"></a>
**Target:** property.
**Parameters:** `name: string` — header name.

Example:
```php
use ApiSutra\Attributes\Request\Header;

#[Header('X-Request-Id')]
public string $requestId;
```

## File <a id="section-9"></a>
**Target:** property.
**Parameters:**
- `name?: string` — field name.
- `format: FileFormat = Multipart` — file format.

`Multipart` is the default.

Example:
```php
use ApiSutra\Attributes\Request\File;
use ApiSutra\Enums\Http\FileFormat;

#[File('document', FileFormat::Multipart)]
public FileInput $document;
```

## Ignore <a id="section-10"></a>
**Target:** property.
**Parameters:** none.
**Effect:** excludes the field from serialization.

## RequestDefaults <a id="section-11"></a>
**Target:** class.
**Parameters:** `unmapped: RequestUnmappedTarget = Convention`

Sets the default target for **unannotated** request properties.

Modes:
- `Convention` — HTTP method behavior: `GET/DELETE` -> query, other methods -> body.
- Query — unannotated properties enter query.
- Body — unannotated properties enter body.

Example:
```php
use ApiSutra\Attributes\Request\RequestDefaults;
use ApiSutra\Enums\Request\RequestUnmappedTarget;

#[RequestDefaults(unmapped: RequestUnmappedTarget::Body)]
final class PatchSomethingRequest extends AbstractRequest {}
```

Priorities:
1. Ignore
2. Property attributes (Path, Header, File, Query, Body, `BodyRoot`)
3. `RequestDefaults`
4. HTTP method convention

## OperationDescriptor <a id="section-12"></a>
**Target:** class.
**Parameters:**
- `title?: string`
- `description?: string`
- `note?: string`

Adds a short request-class DX descriptor without mixing it with runtime result
metadata or requiring a separate catalog.

Example:
```php
use ApiSutra\Attributes\Request\OperationDescriptor;

#[OperationDescriptor(
    title: 'Get history',
    description: 'Returns rights history for the object.',
    note: 'Available only for paid plans.',
)]
final class GetHistoryRequest extends AbstractRequest {}
```

Request accessors:
- `getOperationDescriptorAttribute()`
- `getOperationTitle()`
- `getOperationDescription()`
- `getOperationNote()`

## RequestOneOf <a id="section-13"></a>
**Target:** class (repeatable).
**Parameters:**
- `name: string` — contract name, unique within the class.
- `variants: array<string, list<string>>` — variants and their fields.
- `requiredCommon: list<string>` — shared required fields.
- `mode: OneOfMode` — validation mode (`ExactlyOne` or `AtLeastOne`).

Validation runs **before serialization and HTTP**.

Example:
```php
use ApiSutra\Attributes\Request\RequestOneOf;
use ApiSutra\Enums\Request\OneOfMode;

#[RequestOneOf(
    name: 'signature_payload',
    variants: [
        'cloudcrypt' => ['certificateId'],
        'goskey' => ['goskeyData'],
    ],
    requiredCommon: ['type', 'contents'],
    mode: OneOfMode::ExactlyOne,
)]
final class CreateSignatureRequest extends AbstractRequest {}
```

Top-level fields (type) and dot paths (`signature.certificateId`) are supported.
In the first version, dot paths do not support wildcards/indices (*, 0, and similar).

## RequestDiscriminator <a id="section-14"></a>
**Target:** class.
**Parameters:**
- `field: string` — request discriminator field.
- `map: array<string, string>` — discriminator value -> oneOf variant name.

Used with `RequestOneOf` for `requiredIf/prohibitedIf` rules without manual `assert...()`
calls in the request class.

Example:
```php
use ApiSutra\Attributes\Request\RequestDiscriminator;

#[RequestDiscriminator(
    field: 'type',
    map: [
        'CERT_PROVIDER' => 'cloudcrypt',
        'HSM_PROVIDER' => 'goskey',
    ],
)]
final class CreateSignatureRequest extends AbstractRequest {}
```

### oneOf populated-value semantics <a id="section-15"></a>
- null counts as unpopulated.
- '', [], 0, and false count as populated.

This deliberately preserves explicitly supplied values.

## AuthScope <a id="section-16"></a>
**Target:** class.
**Parameters:** `scope: string|BackedEnum` — scope key.

Example:
```php
use ApiSutra\Attributes\Request\AuthScope;
use ApiSutra\Core\AbstractRequest;
use App\Provider\Auth\ProviderScope;

#[AuthScope(ProviderScope::System)] // Recommended option for enum
final class SomeRequest extends AbstractRequest {}

#[AuthScope('system')] // Explicit scope
final class ScopedRequest extends AbstractRequest {}
```
