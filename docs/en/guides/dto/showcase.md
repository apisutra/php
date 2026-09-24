<!-- languages --> <a href="showcase.md">English</a> · <a href="../../../ru/guides/dto/showcase.md">Русский</a> <!-- /languages -->
# DTO capabilities in one example <a id="section-1"></a>
`CatalogItemDto` describes a product: mapping, defaults, transformations, nesting, collections, a Base64 file, and outgoing JSON.

[Input JSON](#section-2) · [DTO](#section-3) · [Policy](#section-4) ·
[Result](#section-5) · [Serialization](#section-6) · [File in a DTO](#section-7) ·
[Dispatch](#section-8) · [Errors](#section-9) · [Other options](#section-10).

From a checkout after `composer install`:

```bash
php docs/example/dto-showcase/run.php
```

In an installed package, prefix the path with `vendor/apisutra/php/`. MockTransport uses no network. The [expected result](../../../example/dto-showcase/fixtures/expected.json) covers the DTO, DX, HTTP payload, and errors.

## Input JSON <a id="section-2"></a>

This is the [`data` field](../../../example/dto-showcase/fixtures/item.json) of a successful API response: displayName and stock are absent; title=null.

```json
{
  "kind": "catalog_item",
  "product_id": 7,
  "vendor_code": "BK-7",
  "title": null,
  "description": "   ",
  "available": true,
  "metrics": {"rating": 4.8, "votes": 12},
  "created_at": "2026-09-15T10:30:00+00:00",
  "state": "active",
  "price": "12.34",
  "manual_file": "data:text/plain;base64,U0RLIG1hbnVhbA==",
  "seller": {"id": 9, "name": "Книжная лавка", "tier": "gold"},
  "tags": [{"name": "php"}, {"name": "sdk"}],
  "related_ids": [11, 12],
  "assets": [
    {"value": {"type": "image", "url": "https://assets.example.test/cover.png", "width": 640}, "rank": 1},
    {"value": {"type": "video", "url": "https://assets.example.test/demo.mp4", "duration": 30}, "rank": 2}
  ],
  "future_flag": false
}
```

## DTO declaration <a id="section-3"></a>

Complete [CatalogItemDto](../../../example/dto-showcase/src/CatalogItemDto.php): `AbstractDto` provides `from()`, `toArray()`, `with()`, and works with `Returns`.
`AbstractResponseDto` adds `computed()`; [ordinary PHP classes](plain-models.md) are also supported.

```php
declare(strict_types=1);

namespace Example\DtoShowcase;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use ApiSutra\Attributes\DataTransfer\DateTimeTo;
use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Attributes\DataTransfer\DtoSerialize;
use ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\Map;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\Casts\DataUriBase64FileCast;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Enums\Serialization\EnumOutput;
use ApiSutra\VO\Files\Base64File;
use ApiSutra\Attributes\DataTransfer\ConstructorValue;
use ApiSutra\Attributes\DataTransfer\RequiredInput;
use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\Attributes\DataTransfer\ForbidExplicitNull;
use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Enums\DataTransfer\NestedUnknownVariant;
use ApiSutra\Serialization\Rules\ScalarType;
use ApiSutra\Serialization\Shapes\ListShape;
use ApiSutra\Serialization\Shapes\VariantsShape;
use DateTimeImmutable;

// toArray() preserves null and string enum values.
#[DtoSerialize(enumOutput: EnumOutput::Value, serializeNulls: true)]
final readonly class CatalogItemDto extends AbstractDto
{
    // The constructor value is checked against input without rewriting the readonly property.
    #[ConstructorValue]
    public string $kind;

    /**
     * @param list<int> $relatedIds
     * @param list<ImageDto|VideoDto> $media
     * @param array<string, mixed> $_extra
     */
    public function __construct(
        // Input name with a fallback path; the output name is set separately.
        #[From('product_id', fallback: ['id'])]
        #[To('product_id')]
        #[RequiredInput]
        public int $id,
        // One external name for both reading and writing.
        #[Map('vendor_code')]
        public string $sku,
        // Missing input and explicit null are allowed by the fictional API contract.
        #[DefaultValue('Без названия', when: [ValueState::Missing, ValueState::Null])]
        public string $title,
        // The key is required, but its value may be null or an empty string.
        #[EmptyStringAsNull(blank: true)]
        public ?string $description,
        // The shared Strict policy checks the exact bool type.
        public bool $available,
        // A nested path can be flattened into a separate DTO property.
        #[From('metrics.rating')]
        public float $rating,
        // Input contains a time with a timezone; the output format is a calendar date.
        #[From('created_at')]
        #[To('created_at')]
        #[DateTimeFrom(format: DATE_ATOM, strictFormat: true)]
        #[DateTimeTo(format: 'Y-m-d', timezone: 'UTC')]
        public DateTimeImmutable $createdAt,
        // The API string becomes a backed enum.
        #[From('state')]
        #[To('state')]
        public ItemStatus $status,
        // A custom cast reads "12.34" as 1234 and performs the reverse transformation.
        #[From('price')]
        #[To('price')]
        #[Cast(MinorUnitsCast::class)]
        public int $priceMinor,
        // File inside JSON: input accepts a data URI; output contains plain Base64.
        #[Map('manual_file')]
        #[Cast(DataUriBase64FileCast::class)]
        public Base64File $manual,
        // SellerDto is an ordinary PHP class; Nested creates a separate nested object.
        #[Nested(type: SellerDto::class)]
        public SellerDto $seller,
        // Elements become DTOs; the container checks their type and provides first()/count().
        #[Nested(type: TagDto::class)]
        public TagCollection $tags,
        // Shape checks every list element; PHPDoc is for the IDE.
        #[From('related_ids')]
        #[To('related_ids')]
        #[RequiredInput]
        #[Shape(new ListShape(ScalarType::Int))]
        public array $relatedIds,
        // Each value becomes a variant DTO; its sibling rank remains in extras.
        #[From('assets')]
        #[To('assets')]
        #[RequiredInput]
        #[Shape(new ListShape(new VariantsShape('type', [
            'image' => ImageDto::class,
            'video' => VideoDto::class,
        ], unknown: NestedUnknownVariant::Error), each: 'value'))]
        public array $media,
        // The provider computes a missing value from the original DTO data.
        #[DefaultValue(provider: DisplayNameProvider::class)]
        public string $displayName,
        // Missing input is allowed; an original null is forbidden.
        #[ForbidExplicitNull]
        public ?int $stock = null,
        // Unread fields are retained here and excluded from client requests.
        #[Extras]
        public array $_extra = [],
    ) {
        $this->kind = 'catalog_item';
    }
}
```

Supporting types: [SellerDto](../../../example/dto-showcase/src/SellerDto.php) is an ordinary class; [TagDto](../../../example/dto-showcase/src/TagDto.php) and [TagCollection](../../../example/dto-showcase/src/TagCollection.php) form a typed collection; [ImageDto](../../../example/dto-showcase/src/ImageDto.php) / [VideoDto](../../../example/dto-showcase/src/VideoDto.php) are variants; [ItemStatus](../../../example/dto-showcase/src/ItemStatus.php) is an enum.

[MinorUnitsCast](../../../example/dto-showcase/src/MinorUnitsCast.php) converts a price string ↔ integer hundredths, allowing up to seven digits before the point and exactly two after it. [DisplayNameProvider](../../../example/dto-showcase/src/DisplayNameProvider.php) computes `Товар BK-7` from vendor_code in the original DTO data.

## Shared client policy <a id="section-4"></a>

[CatalogHydration](../../../example/dto-showcase/src/CatalogHydration.php) sets shared Strict behavior.
All field declarations, including the nested receiver, live on the models; no DTO registry is needed.

```php
declare(strict_types=1);

namespace Example\DtoShowcase;

use ApiSutra\Config\HydrationConfig;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;

final class CatalogHydration
{
    public static function create(): HydrationConfig
    {
        return new HydrationConfig(policy: new RulePolicy(scalars: ScalarPolicy::Strict));
    }
}
```

In [run.php](../../../example/dto-showcase/run.php), `$source` contains JSON and `$hydration = CatalogHydration::create()`.
Standalone: `Hydrator::forConfig($hydration)->hydrate($source, CatalogItemDto::class)`.
The same block is passed to `ClientConfig(hydration: $hydration, ...)`; the [request](../../../example/dto-showcase/src/GetCatalogItemRequest.php)
declares `Returns(CatalogItemDto::class, unwrap: 'data')`.
`CatalogItemDto::from()` reads attributes but does not inherit the shared client policy.

[External rules](plain-models.md) are useful for third-party models. Do not use them
to duplicate an input attribute on the same field: that is a [configuration conflict](../../reference/dto/field-rules.md#section-3).

## What the application receives <a id="section-5"></a>

| Property | Input / condition | Mechanism → result |
| --- | --- | --- |
| `kind` | `"catalog_item"` | ConstructorValue checks the fixed constructor value |
| `id` | `product_id: 7` | From → `7`; if the key is absent, `id` is checked; a found null does not trigger fallback |
| `sku` | `vendor_code: "BK-7"` | Map → `"BK-7"`; the same external name is used for output |
| `title` | null or missing key | DefaultValue → `"Без названия"` |
| `description` | `" "` | EmptyStringAsNull → null; ordinary text is preserved; a missing key is an error |
| `available` | true | Strict → bool; string `"true"` does not substitute for bool |
| `rating` | `metrics.rating: 4.8` | From with a nested path → float; sibling votes remains in `_extra` |
| `createdAt` | Date string with offset | DateTimeFrom → DateTimeImmutable; the date remains an object until serialization |
| `status` | `state: "active"` | Native enum → `ItemStatus::Active` |
| `priceMinor` | `price: "12.34"` | Cast → `1234`; serialization produces `"12.34"` again |
| `manual` | data URI in manual_file | Cast → Base64File; `content()` returns `"SDK manual"`, `size()` returns 10 bytes |
| `seller` | Object with id/name/tier | Nested → SellerDto; unread tier is retained in `seller->_extra` |
| `tags` | Two objects with name | Nested → TagCollection with two TagDto objects; a missing field yields an empty collection |
| `relatedIds` | `[11, 12]` | ValueShape::list(int) → int list; a string inside the list causes an error |
| `media` | `assets[*].value` | each + variants → ImageDto and VideoDto; an unknown type causes an error |
| `displayName` | Missing key | Provider → `"Товар BK-7"` |
| `stock` | Missing key / 0 / null | Produces null / 0 / error, respectively |
| `_extra` | metrics.votes, future_flag, and rank next to value | Data remainder not read by fields; does not create dynamic properties |

`_extra` contains this [remainder](../../reference/dto/extras.md):

```json
{"metrics":{"votes":12},"assets":[{"sourceKey":0,"remainder":{"rank":1}},{"sourceKey":1,"remainder":{"rank":2}}],"future_flag":false}
```

The seller remainder belongs to SellerDto; the discriminator is read by ImageDto/VideoDto. Read null is removed; unread false is retained.

## Serialization attributes <a id="section-6"></a>

To/Map set names, DateTimeTo and Cast set value representations, and DtoSerialize configures the dump.
See the [complete DX/wire contract](../../reference/serialization/dto-output.md); both paths' results appear in the dispatch table below.

## A file in a DTO field <a id="section-7"></a>

The `manual` above is a manual embedded in JSON. `DataUriBase64FileCast` accepts plain Base64 and data URIs:
`$item->manual->content()` returns `"SDK manual"`; `$item->manual->saveTo($path)` saves those bytes to the specified file.
During `toArray()` and dispatch, Cast returns `manual_file: "U0RLIG1hbnVhbA=="` without the data URI MIME prefix.
Base64 is materialized in memory. Streaming uploads/downloads are shown in the [file recipe](../recipes/files.md).
See the [Base64File contract and file lists](../../reference/files/downloads.md#section-8).

## What is sent in the request <a id="section-8"></a>

[SaveCatalogItemRequest](../../../example/dto-showcase/src/SaveCatalogItemRequest.php) passes the object through `BodyRoot`; MockTransport records the JSON.

| Value | `$item->toArray()` — DX | Client request JSON — wire |
| --- | --- | --- |
| `id` / `sku` | product_id / vendor_code | product_id / vendor_code |
| Date / enum / price | `"2026-09-15"` / `"active"` / `"12.34"` | The same values |
| `manual` | Plain Base64 under manual_file | The same string without the data URI prefix |
| `description` / `stock` | null is retained through DtoSerialize | Keys are omitted by the default wire policy |
| `_extra`, including seller | An ordinary property containing the remainder | Excluded by Extras attributes at both depths |
| `media` | An array of objects under assets | An array without the input value wrapper or rank |

Input `each` does not restore the wrapper; `toArray()` does not guarantee a byte-for-byte JSON round trip.
Choose [DX and wire](../../reference/serialization/dto-output.md) settings according to the API contract.
Receiver exclusion depends on the class declaration, including manually created DTOs: see [cast and representation boundaries](../../reference/serialization/receiver-output.md).

## What errors look like <a id="section-9"></a>

`run.php` separately hydrates ten invalid variants. `path` identifies the DTO property;
`sourcePath` is a JSON Pointer into standalone input; with Returns, the external unwrap adds `/data`.

| Violation | reason | path | sourcePath |
| --- | --- | --- | --- |
| Incorrect kind | constructor_value_mismatch | kind | /kind |
| Both id names absent | required_field_missing | id | /product_id (expected) |
| `product_id: "7"` | invalid_field_type | id | /product_id |
| `product_id: null`, while `id: 8` | null_not_allowed | id | /product_id |
| `stock: null` | explicit_null_not_allowed | stock | /stock |
| `related_ids: [11, "12"]` | invalid_field_type | relatedIds[1] | /related_ids/1 |
| String seller.id | invalid_field_type | seller.id | /seller/id |
| Unknown variant type | unknown_nested_variant | media[0] | /assets/0/value |
| Date does not match the format | invalid_datetime | createdAt | /created_at |
| Price `"12,34"` | invalid_price (custom cast) | priceMinor | /price (boundary) |

A cast boundary points to transformation input; the precise source may be unavailable for computed or opaque transformations.
[Diagnostics and safe logging](../../reference/dto/diagnostics.md) describes precision boundaries.

## Choosing another approach <a id="section-10"></a>

| Task | Option and boundary |
| --- | --- |
| A DTO already exists and must not depend on ApiSutra | [Ordinary PHP class + HydrationRules](plain-models.md); shape examples work without a base DTO |
| Shared names, dates, and casts across many models | [NamingStrategy and a hydration profile](../../reference/dto/profiles.md); the profile is an alternative to DtoRules for that class |
| Nested-list rules are more convenient on the DTO | [Nested with each/discriminator](../../reference/dto/shapes.md); do not combine with FieldRule on the same property |
| Dictionaries, nested lists, unions, or nullable elements are needed | [ValueShape and strict scalars](../../reference/dto/scalars.md), [shapes](../../reference/dto/shapes.md); PHPDoc alone does not validate elements |
| Unknown variants must be retained or skipped | [KeepRaw / Skip](../../reference/dto/variants.md); KeepRaw requires a container that accepts raw values |
| A cast/provider creates nested DTOs itself | [HydrationContext](../../reference/dto/scope.md) passes current rules; ordinary Hydrator::default() loses them |
| A default depends on context or a found value needs checking | [DefaultValue providers and states](../../reference/dto/defaults.md#section-9); one provider can handle multiple states |
| Application rules and field descriptions are needed | [Validate, Label, About](../../reference/attributes/hydration.md); validator setup is a [separate step](../../reference/client/validation.md) |

[Choose DTOs for your SDK](../../start/describe-dto.md) · [Complete reference](../../reference/dto/README.md) · [Sources and execution](../../examples/dto-showcase.md).
