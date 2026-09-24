<!-- languages --> <a href="http.md">English</a> · <a href="../../../ru/reference/attributes/http.md">Русский</a> <!-- /languages -->
# HTTP attributes <a id="section-1"></a>

## Signatures and targets <a id="section-2"></a>

Class names belong to `ApiSutra\Attributes\Http`.
Signatures show constructor parameters and defaults; target identifies where the
attribute is allowed. Behavior and priorities are covered in the topic links below.

| Attribute | Target | Constructor |
| --- | --- | --- |
| Delete | CLASS | `Delete(string $path)` |
| Get | CLASS | `Get(string $path)` |
| Patch | CLASS | `Patch(string $path)` |
| Post | CLASS | `Post(string $path)` |
| Put | CLASS | `Put(string $path)` |

These request-class attributes define the HTTP method and endpoint.

## When to use them <a id="section-3"></a>
- **Get** — safe reads without side effects.
- **Post** — creation or complex requests with bodies.
- **Put/Patch** — full/partial resource updates.
- **Delete** — deletion or cancellation.

## Get <a id="section-4"></a>
**Target:** class.
**Parameters:** `path: string` — request path.
**Effect:** GET method and endpoint.

Example:
```php
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;

#[Get('/users/{id}')]
final class GetUser extends AbstractRequest {}
```

## Post <a id="section-5"></a>
**Target:** class.
**Parameters:** `path: string`
**Effect:** POST method and endpoint.

## Put <a id="section-6"></a>
**Target:** class.
**Parameters:** `path: string`
**Effect:** PUT method and endpoint.

## Patch <a id="section-7"></a>
**Target:** class.
**Parameters:** `path: string`
**Effect:** PATCH method and endpoint.

## Delete <a id="section-8"></a>
**Target:** class.
**Parameters:** `path: string`
**Effect:** DELETE method and endpoint.

## Notes <a id="section-9"></a>
- Fill path placeholders with Path.
- If a property name matches `{id}`, Path is not required.
  Use `#[Path('id')]` when the property has another name or there are several placeholders.
