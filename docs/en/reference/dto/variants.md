<!-- languages --> <a href="variants.md">English</a> · <a href="../../../ru/reference/dto/variants.md">Русский</a> <!-- /languages -->
# List item variants <a id="section-1"></a>

## Discriminator <a id="section-2"></a>

`ValueShape::variants(string $discriminator, array $map, NestedDiscriminatorMode $mode = Value,
NestedUnknownVariant $unknown = KeepRaw)` is used only as an item of `list()`.
Enums live in `ApiSutra\Enums\DataTransfer`.

Value selects a class from the value at the discriminator path; Key uses the first
wrapper key (an empty discriminator means the current object). Map contains
values/keys and DTO classes. An unknown or missing variant is handled by KeepRaw,
Skip, or Error. Error produces `unknown_nested_variant`. An error in a known variant
is never suppressed. KeepRaw is incompatible with a typed collection that accepts
only DTOs: this is a configuration error.
