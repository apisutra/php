<!-- languages --> <a href="uri-query.md">English</a> · <a href="../../../ru/reference/serialization/uri-query.md">Русский</a> <!-- /languages -->
# URI, path, and query <a id="section-1"></a>

Pass a complete URL through `$request->withUrl($url)`. The SDK uses it in full,
without the client's base path/query. An ordinary signed URL needs no extra settings.
An absolute `getEndpoint()` follows the same contract.

```php
declare(strict_types=1);

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\File;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Http\FileFormat;
use ApiSutra\VO\Files\FileInput;

#[Post('/upload-sessions')]
final class CreateUploadSessionRequest extends AbstractRequest {}

// Example of an API that returns a URL for multipart POST.
#[Post('/upload')]
final class UploadToSessionRequest extends AbstractRequest
{
    /** @param list<FileInput> $files */
    public function __construct(
        #[File(name: 'file', format: FileFormat::Multipart)]
        public array $files,
    ) {}
}

$session = (new CreateUploadSessionRequest())->setClient($client)->dataOrFail();
$upload = new UploadToSessionRequest([FileInput::fromPath('/tmp/report.pdf')]);
$result = $upload->setClient($client)->withUrl($session['url'])->send()->raw();
```

Set the method, body format, and required headers according to the provider contract.
If a session returns special headers, set them with `withHeader()`. Preserving the URL
does not fix incorrect methods/bodies/signed headers or mean the SDK validates its
signature. Binary/multipart uploads and downloads support
[streaming](../../guides/recipes/files.md), including saving to a path or user-supplied stream.

## Complete URL rules <a id="section-2"></a>

- Priority: `withUrl()` → absolute endpoint → relative endpoint with effective base URL.
  A new `withUrl()` replaces the previous one; `withoutUrl()` clears only this override.
  An earlier `withBaseUrl()` takes effect again after clearing. The original request is unchanged.
- HTTP/HTTPS, ASCII/punycode hosts, and IPv6 are supported. The URL must already be
  encoded. Userinfo, other schemes, `//host`, spaces, control characters, and invalid
  percent sequences cause `configuration_error`. Unicode in path/query must be percent-encoded.
- Path/query are preserved: `%2f`, `%2F`, +, `%20`, duplicate keys, order, an empty ?, and
  boundary & characters. The fragment is removed; an empty path becomes /.
  The standard cURL adapter also preserves dot segments without resolving `..`.
- Base URL, relative endpoint placeholders, and additional query do not apply.
  If fields, pagination, continuation, or an explicitly enabled enricher create a
  query pair, the SDK returns `configuration_error` instead of ignoring it. Omitted
  null/an empty list creates no pair; included null/an empty string creates a conflicting pair.
- Query auth cannot append a key to a complete URL even when explicitly enabled.
  Build dynamic queries for ordinary APIs through relative endpoints.

## Cache, redirects, and diagnostics <a id="section-3"></a>

Complete URLs automatically bypass the HTTP cache. Explicitly enabling cache,
including through a request attribute, produces `configuration_error`: the SDK does
not know signature expiry. Custom-key semantics for ordinary requests are unchanged.

The standard adapter does not follow redirects. A 3xx enters normal HTTP result
handling; origin permission does not authorize following `Location`. A late origin
change through stages/hooks/auth/retry handlers, or any address change for a complete
URL, is rejected before cache lookup/HTTP.

In safe `requestDebug()`, logs, and recorded fixtures, the complete URL becomes its
origin plus `/[redacted]`. Reflected complete URLs and request targets are also masked.
This needs no `RedactionPolicy`. Additional rules are still required for other secret
API fields. Original response, `exception`, result data, and `requestDebug(false)` provide
raw access and must not be treated as safe exports.

## Custom transports <a id="section-4"></a>

Standard `HttpTransport::createDefault()` provides this contract automatically.
For custom transports/PSR adapters, see [DestinationAwareInterface](../execution/transport.md#section-11).
Missing support produces `configuration_error` before authentication/refresh and sending
the protected request. Sync and promise interfaces use the same rules.

An external destination needs explicit destination credentials/auth and an allowed
origin. For an independent service, create a separate client with its base URL and
credentials. Use `withUrl()` for complete URLs. Relative requests to the client's
original API use its ordinary settings.

## URI and path <a id="section-5"></a>

Endpoints are relative to `baseUrl`: `https://api.test/v1` + `/users` or `users` produces
`https://api.test/v1/users`. A leading slash does not reset the base path. Network paths
(`//host/...`), unsupported schemes, control characters, unescaped spaces, backslashes,
and . / .. segments are rejected with `configuration_error` before authentication and
HTTP. Absolute HTTP/HTTPS endpoints follow the [complete URL contract](uri-query.md).

The SDK separates query and fragment before joining paths. Query parts are appended
in this order: base URL → endpoint → serialized fields → query auth. For example:

```text
baseUrl:  https://api.test/v1?version=2
endpoint: /users?fixed=%2F&fixed=+#ignored
page:     3
URI:      https://api.test/v1/users?version=2&fixed=%2F&fixed=+&page=3
```

The fragment is removed. Existing valid query bytes are neither decoded nor sorted:
`%2F`, +, `%20`, duplicates, and order are preserved. Empty ? and boundary & are normalized.
Matching keys become repeated pairs; interpretation depends on the API. Runtime
fields do not overwrite fixed query.

`{name}` substitution occurs only in the path and accepts an original segment value.
`a/b +%` becomes `a%2Fb%20%2B%25`; value `%2F` becomes `%252F`, while literal `%2F`
in the endpoint is preserved. Unicode uses UTF-8 encoding. 0 is allowed; a missing,
null, empty, or . / .. parameter, or an unresolved placeholder, produces
`serialization_error` before HTTP. There is no automatic traversal through `..`.

`withBaseUrl()` retains its separate contract and does not change the original execution
or config. Use `withUrl()` for complete external or signed URLs; changing origin through
`withBaseUrl()` applies [credentials isolation](uri-query.md).

## Query <a id="section-6"></a>

Without extra settings, booleans are sent as `true → 1`, `false → 0`. This also applies
to query list items and scalar multipart text fields. JSON retains booleans; arrays
in multipart fields still encode as JSON. Optional
[textBooleanFormat](request-parts.md#section-4) changes the format to `true/false`;
an explicit field cast takes priority.

| Input value | Query |
| --- | --- |
| null | Omitted by default; key= when included |
| false / true | key=0 / key=1 |
| 0 / "0" | key=0 |
| Empty string | key= |
| Empty list | Omitted in all formats |
| Flat list of scalars and null | Preserves order, duplicates, and an empty position for null |
| Associative, sparse, or nested array | serialization_error before HTTP |

`#[Query(nullable: null)]` and omitted `nullable` inherit `ClientConfig::serializeNulls`
(default false). Explicit `true/false` on a field overrides the client setting. This
rule applies to the top level: null inside a list preserves its position. Included
null and an empty string both appear as `key=`.

```php
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Enums\Http\QueryArrayFormat;

#[Query('q')]
public string $query;

#[Query('ids', arrayFormat: QueryArrayFormat::Comma)]
public array $ids;
```

Details:
- `arrayFormat` selects the array format.
- `Query(nullable: ...)` controls null inclusion.
- Global `serializeNulls` applies to body and query.

### Array format in query <a id="section-7"></a>
`QueryArrayFormat` applies **only** to the query string, producing `a[]=1&a[]=2`,
`a=1%2C2%2C3`, and so on. It does not create a JSON string. Brackets uses `a[]`, Indices
`a[0]`, Repeat repeated a keys, and Comma a single string with URL-encoded commas.
Comma rejects items containing commas: use another format or an explicit cast
matching the API contract. For structures, use `JsonCast` or separate named Query fields.

```php
#[Query('regions', arrayFormat: QueryArrayFormat::Comma)]
public array $regions;
```

### JSON string instead of an array <a id="section-8"></a>
If the API expects a string such as `"[1,2,3]"` in query or body, use `JsonCast` or store
the string manually:
```php
use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Casts\JsonCast;

#[Query('regions')]
#[Cast(JsonCast::class)]
public array $regions = [1, 2, 3];

// Or provide a string manually
#[Query('regions')]
public string $regions = '[1,2,3]';
```

## Path <a id="section-9"></a>
```php
use ApiSutra\Attributes\Request\Path;

#[Path('id')]
public int $userId;
```

Path is optional when the property name matches an endpoint placeholder:
`{id}` binds automatically to `$id`. The attribute is required when names differ.

## Selecting endpoint and baseUrl <a id="section-10"></a>
**Endpoint:**
1) Request `resolveEndpoint()`, if overridden
2) Get('/path') or another HTTP attribute

**BaseUrl:**
1) Runtime `withBaseUrl()` override
2) Request `resolveBaseUrl()`
3) `ClientConfig::baseUrl`

`ClientConfig::baseUrl` is usually sufficient. Override when:
- One client calls multiple domains/API versions.
- Tests or individual endpoints need a temporary override.

## Custom wire representation <a id="section-11"></a>

- Query and text multipart encode false as `0`. For an empty string, declare a custom
  `CastInterface` returning `""` for false and `"1"` for true, or supply a prepared string.
- An empty Comma list is omitted. For an explicit empty value, pass an empty string
  or null with `Query(nullable: true)`.
- Maps, sparse/nested arrays, and Comma items containing commas require another
  representation: `JsonCast`, separate Query fields, or a suitable list format.
- Path values must resolve all placeholders and avoid empty/dot segments.
- Base URL and endpoint queries combine with serialized fields; fragments are removed.
  Use `withUrl()` when a complete signed URL must preserve its request target.
