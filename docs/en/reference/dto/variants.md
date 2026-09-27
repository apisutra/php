<!-- languages --> <a href="variants.md">English</a> · <a href="../../../ru/reference/dto/variants.md">Русский</a> <!-- /languages -->
# DTO variants <a id="section-1"></a>

## Discriminator <a id="section-2"></a>

`ValueShape::variants(string $discriminator, array $map, DiscriminatorMode $mode = Value,
UnknownVariant|string $unknown = KeepRaw)` describes one variant value, on its own
or inside list(). VariantsShape is its attribute equivalent; Nested uses the same
selection rules. Enums live in `ApiSutra\Enums\DataTransfer`.

Value selects a class from a string/int at a path such as `event.type`. Missing/null
uses the unknown policy; bool, float, array and object (including Stringable) give
`invalid_discriminator_type`, without fallback or implicit string conversion.
PHP keys 1 and "1" coincide; "01" is distinct. Key uses the first wrapper key
(an empty discriminator means the current object), optionally below a path.

An unknown admissible tag uses KeepRaw, Skip, Error, or a fallback DTO class.
Error produces `unknown_nested_variant`. Known-model errors never invoke fallback.
The fallback receives the entire node after each, including the unknown tag or
Key wrapper; a known Key model receives its payload. JSON shape is retained.
Extras on a fallback captures PHP values, not the original JSON text.

| Position | Default | Allowed unknown policies |
| --- | --- | --- |
| List item in Shape | KeepRaw | KeepRaw, Skip, Error, fallback class; typed DTO collections reject KeepRaw |
| List item in Nested | KeepRaw | KeepRaw, Skip, Error, fallback class; a typed collection rejects a raw item if an unknown variant occurs |
| DtoVariants / withVariants | Error | Error or a compatible fallback class |
| Single VariantsShape | KeepRaw | Error or fallback; KeepRaw only when the native field allows raw arrays; no Skip |

For a typed collection, every map and fallback class must satisfy its itemClass
declaration. Shape checks this when compiling the field; Nested checks the whole
definition before processing items, even when the input contains only known tags.
The collection is not constructed for this check.
Nested retains its default KeepRaw: known tags work in typed collections; an unknown
raw item fails when the collection is built. Use `unknownVariant: UnknownVariant::Error`
for an explicit unknown-variant error, or a compatible fallback class to preserve it as a DTO.

Default belongs to the declaration and does not change with the property's type.
For a single object-typed field, explicitly choose `unknown: UnknownVariant::Error`
or a fallback class; the default KeepRaw is a configuration error in this position.
The error names the position and suggests a valid policy. Skip never becomes null.

The following field belongs to your DTO; ImageDto and RawDto are your models:

```php
use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Shapes\VariantsShape;

#[Shape(new VariantsShape('type', ['image' => ImageDto::class], unknown: RawDto::class))]
public ImageDto|RawDto $attachment;
```

## Declare variants once on a type <a id="type-variants"></a>

Declare DtoVariants on an interface, abstract class or concrete base class. No
registration, container or feature flag is needed. Every map/fallback class must
be concrete and implement/extend the declared type. The default here is Error:
KeepRaw and Skip cannot satisfy an object-type promise.

```php
use ApiSutra\Attributes\DataTransfer\DtoVariants;
use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\Serialization\Hydrator;

#[DtoVariants('type', ['text' => TextEvent::class], unknown: UnknownEvent::class)]
interface Event {}

final readonly class TextEvent implements Event
{
    public function __construct(public string $text) {}
}

final readonly class UnknownEvent implements Event
{
    public function __construct(#[Extras] public array $raw = []) {}
}

$dto = Hydrator::default()->hydrateJson('{"type":"new","payload":{"enabled":true}}', Event::class);
// UnknownEvent, with the entire PHP representation in raw.
```

The same Event works in Returns (including unwrap), native `Event`/`?Event` fields,
DtoShape, Nested.type, ListShape(DtoShape(...)), pagination itemsType and continuation
finalType/awaitAs. A native interface without a variants declaration is not guessed;
an ambiguous union of DTO targets needs an explicit shape. Already-created objects
are checked by instanceof, including typed collection items, without selecting again.

For third-party types, use existing HydrationRules rather than a global registry:

```php
use ApiSutra\Serialization\Rules\HydrationRules;

// Event, TextEvent and UnknownEvent are your models without a DtoVariants attribute.
$rules = HydrationRules::create()->withVariants(
    Event::class,
    discriminator: 'type',
    map: ['text' => TextEvent::class],
    unknown: UnknownEvent::class,
);
```

This is an alternative to the attribute, not an additional declaration for the same
type: combining both is a configuration error. Definitions belong to HydrationConfig
and are isolated between clients. The shared JSON shape setting still defaults to
true; false skips shape metadata, not variant selection or PHP type checks. There
is no global fallback model, and no local switch is required to enable variants.

### Selection and extension boundaries <a id="selection-boundaries"></a>

A class is selected once per input node, then the ordinary concrete hydration runs.
A concrete declaring class may select itself, including as fallback. The selected
class's own DtoVariants is not dispatched again on this node; a new nested node can
select independently. Direct hydration of a subtype does not search its parents or
implemented interfaces for a declaration. Normal field/profile inheritance remains.

A ready handler/composite object may satisfy an abstract or interface result type
without DtoVariants. Before HTTP, the declaration checks that the type exists and
validates any declared map. Actual hydration still requires a concrete class or
variants; an abstract type alone is not a construction recipe. A composite skips
hydration only for an object that already satisfies the declared response
type (`instanceof`). Other object sources, including stdClass and JsonSerializable, and arrays are hydrated.

The whole map and fallback are checked without constructors, supports() or DI.
For a Returns declaration this happens before HTTP through the common target check;
it does not eagerly compile every field of models owned by a custom hydrator.
Native/custom hydration is chosen after the concrete class; the custom hydrator
receives that class. Known-model failures never become successful fallback objects.

Built-in selection keeps source shape/path. User casts, computed and data-replacing
hooks retain their [transformation boundary](scope.md); context->hydrate() accepts
new PHP input. BeforeHydrate is scoped to the declared response type, AfterHydrate
to the concrete runtime type; parent/interface hooks are not invoked a second time.
Standalone hydrateJson has no HTTP hooks or execution trace. Serialization uses the
concrete DTO's rules and does not synthesize a discriminator or reconstruct a Key wrapper.

## Runnable HTTP, polling and webhook example <a id="example"></a>

[The complete example](../../../example/polymorphic-json/run.php) includes original
JSON, two variant declarations, nested DTOs, typed fallback with Extras, InputShape
before a dictionary cast and four invalid-shape scenarios. It uses MockTransport,
without a network or Laravel:

```bash
php vendor/apisutra/php/docs/example/polymorphic-json/run.php
```

HTTP and webhook share an explicit HydrationConfig; standalone defaults do not
implicitly inherit the configuration or Returns::hydrator of another client.
