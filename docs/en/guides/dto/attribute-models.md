<!-- languages --> <a href="attribute-models.md">English</a> · <a href="../../../ru/guides/dto/attribute-models.md">Русский</a> <!-- /languages -->
# DTOs with attributes <a id="section-1"></a>

Attributes suit a model owned by the SDK, with mapping kept next to each property.
[RecordDto](../../../example/sdk/src/AttributeExample/RecordDto.php) is a separate class
with `From('record_id')`, typed id/title, and `AbstractResponseDto` as its base.

## Describe the model <a id="section-2"></a>

1. Choose a base class or implement a supported [DTO contract](../../reference/dto/models.md).
2. Use From/Map for the input data path; outgoing To is set separately.
3. For a single nested object or a list, use
   [Nested](../../reference/dto/shapes.md). Native `array` does not check element types.
4. Define missing/null and defaults using the [presence contract](../../reference/dto/defaults.md).
5. For shared transformation behavior, use a
   [profile](../../reference/dto/profiles.md) or a targeted Cast.

## Verify <a id="section-3"></a>

Load a valid array, missing input, null, and an incorrect type. Ensure the error path
identifies the right property/element. For output, check
[DX/wire](../../reference/serialization/dto-output.md) separately: input mapping does
not automatically determine the request format.

When moving to [external rules](plain-models.md), first resolve overlapping declarations
using the [conflict table](../../reference/dto/field-rules.md).

[Attribute signatures](../../reference/attributes/hydration.md) ·
[DTO route](../../start/describe-dto.md).
