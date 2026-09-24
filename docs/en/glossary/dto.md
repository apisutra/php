<!-- languages --> <a href="dto.md">English</a> · <a href="../../ru/glossary/dto.md">Русский</a> <!-- /languages -->
# DTO <a id="section-1"></a>

| Term | Meaning | Details |
| --- | --- | --- |
| <a id="dtointerface"></a> DtoInterface | The ApiSutra DTO interface with a `from(array\|object): static` factory. | [Contract](../reference/dto/models.md) |
| <a id="responsedtointerface"></a> ResponseDtoInterface | The interface for root response DTOs. | [Contract](../reference/dto/models.md) |
| <a id="abstractdto"></a> AbstractDto | An optional DTO base class. | [Contract](../reference/dto/models.md) |
| <a id="dtohydrationprofileinterface"></a> DtoHydrationProfileInterface | A contract for input-model naming and transformations, attached through an attribute on the class or its shared base. | [Contract](../reference/dto/profiles.md) |
| <a id="dtohydrationpolicy"></a> DtoHydrationPolicy | An immutable DTO hydration policy after defaults and overrides have been resolved. | [Contract](../reference/dto/profiles.md) |
| <a id="dtohydrationprofile-attribute"></a> DtoHydrationProfile (attribute) | A class-level binding attribute that associates a DTO or base DTO with `DtoHydrationProfileInterface`. | [Contract](../reference/dto/profiles.md) |
| <a id="dtohydrate-attribute"></a> DtoHydrate (attribute) | An attribute that partially configures the input model on top of a profile. | [Contract](../reference/dto/profiles.md) |
| <a id="dtoserializationprofileinterface"></a> DtoSerializationProfileInterface | A centralized body DTO policy contract for an SDK/package. | [Contract](../reference/serialization/dto-output.md) |
| <a id="dtoserializationpolicy"></a> DtoSerializationPolicy | An immutable DTO serialization policy after defaults and overrides have been resolved. | [Contract](../reference/serialization/dto-output.md) |
| <a id="dtoserializationprofile-attribute"></a> DtoSerializationProfile (attribute) | A class-level binding attribute that associates a DTO or base DTO with `DtoSerializationProfileInterface`. | [Contract](../reference/serialization/dto-output.md) |
| <a id="dtoserialize-attribute"></a> DtoSerialize (attribute) | A class-level partial override of package/profile defaults. | [Contract](../reference/serialization/dto-output.md) |
| <a id="abstractresponsedto"></a> AbstractResponseDto | An abstract class for root response DTOs. | [Contract](../reference/dto/models.md) |
| <a id="validatableinterface"></a> ValidatableInterface | An interface for objects that support validation. | [Contract](../reference/client/validation.md) |
| <a id="customvalidatablerequestinterface"></a> CustomValidatableRequestInterface | Extensible preflight request validation before serialization/HTTP. | [Contract](../reference/client/validation.md) |
| <a id="validatorinterface"></a> ValidatorInterface | The validator contract. | [Contract](../reference/client/validation.md) |
| <a id="validationresult"></a> ValidationResult | The validation result. | [Contract](../reference/client/validation.md) |
| <a id="nested-attribute"></a> Nested (attribute) | An attribute for hydrating a single object or a list, including projections and polymorphic items. | [Contract](../reference/dto/shapes.md) |
| <a id="from-attribute"></a> From (attribute) | An attribute that renames a field during DTO hydration. | [Contract](../reference/dto/profiles.md) |
| <a id="to-attribute"></a> To (attribute) | An attribute for serializing a DTO into the request body. | [Contract](../reference/serialization/dto-output.md) |
| <a id="defaultvalue-attribute"></a> DefaultValue (attribute) | A value or provider for Missing/Null/Present, according to when. | [Contract](../reference/dto/defaults.md) |
| <a id="defaultvalueproviderinterface"></a> DefaultValueProviderInterface | A contract for computing complex defaults. | [Contract](../reference/dto/defaults.md) |
| <a id="valuestate-enum"></a> ValueState (enum) | The state of a value extracted from a response: Missing, Null, or Present. | [Contract](../reference/dto/defaults.md) |
| <a id="cast-attribute"></a> Cast (attribute) | An attribute for custom value conversion during hydration. | [Contract](../reference/serialization/casts.md) |
| <a id="datetimefrom-attribute"></a> DateTimeFrom (attribute) | A property-level override of date-time hydration semantics. | [Contract](../reference/dto/profiles.md) |
| <a id="datetimeto-attribute"></a> DateTimeTo (attribute) | A property-level override of date-time body serialization semantics. | [Contract](../reference/serialization/dto-output.md) |
| <a id="emptystringasnull-attribute"></a> EmptyStringAsNull (attribute) | A property-level hydration normalizer. | [Contract](../reference/dto/defaults.md) |
| <a id="castinterface"></a> CastInterface | A converter interface with `hydrate()` and `serialize()` methods. | [Contract](../reference/serialization/casts.md) |
| <a id="castregistry"></a> CastRegistry | A cast registry for serialization; hydration does not read it. | [Contract](../reference/serialization/casts.md) |
| <a id="hydrator"></a> Hydrator | Converts PHP arrays into DTOs; forRules() explicitly attaches an external rule set. | [Contract](../reference/dto/lifecycle.md) |
| <a id="dtoserializer"></a> DtoSerializer | A DTO serializer; the client context can add a wire policy and exclude the receiver. | [Contract](../reference/serialization/dto-output.md) |
| <a id="computed"></a> computed() | The final response DTO handler, called after input properties have been populated. | [Contract](../reference/dto/lifecycle.md) |
| <a id="hydrationrules"></a> HydrationRules | An immutable set of class rules and shared defaults bound to a client or standalone hydrator. | [Contract](../reference/dto/field-rules.md) |
| <a id="dtorules"></a> DtoRules | Rules for a specific class: policy, fields, and an optional receiver. | [Contract](../reference/dto/field-rules.md) |
| <a id="fieldrule"></a> FieldRule | A field rule: mapping, presence, default, and the selected transformation. | [Contract](../reference/dto/field-rules.md) |
| <a id="valueshape"></a> ValueShape | A description of a scalar, DTO, list, or item variants, with nested shape validation. | [Contract](../reference/dto/shapes.md) |
| <a id="rulepolicy"></a> RulePolicy | A partial policy for scalars, empty strings, names, dates, and casts. | [Contract](../reference/dto/field-rules.md) |
| <a id="scalarpolicy"></a> ScalarPolicy | Legacy or Strict mode for scalar value validation. | [Contract](../reference/dto/scalars.md) |
| <a id="scalartype"></a> ScalarType | Identifies a scalar branch of ValueShape. | [Contract](../reference/dto/scalars.md) |
| <a id="inputshape"></a> InputShape | An Object or List precondition for the field's source value. | [Contract](../reference/dto/shapes.md) |
| <a id="handlerspec"></a> HandlerSpec | A handler class specification with arguments restricted to allowed values. | [Contract](../reference/dto/field-rules.md) |
| <a id="defaultspec"></a> DefaultSpec | A literal default or provider specification for selected value states. | [Contract](../reference/dto/field-rules.md) |
| <a id="sourcepathkind"></a> SourcePathKind | The precision of the source-data path in a hydration error. | [Contract](../reference/dto/diagnostics.md) |

[All terms](README.md).
| <a id="hydrationcontext"></a> HydrationContext | The context of one input handler; preserves the current rules for nested DTOs. | [Contract](../reference/dto/scope.md) |
| <a id="serializationcontext"></a> SerializationContext | The context of one output handler; preserves the policy and traversal branch. | [Contract](../reference/dto/scope.md) |
