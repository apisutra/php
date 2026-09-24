<!-- languages --> <a href="receiver-output.md">English</a> · <a href="../../../ru/reference/serialization/receiver-output.md">Русский</a> <!-- /languages -->
# Receivers in outgoing requests <a id="section-1"></a>

## Receivers in outgoing requests <a id="section-2"></a>

The client and standalone Serializer exclude the receiver declared by Extras or
external model-class rules, in both hydrated and manually created objects. It is
neither sent under its own name nor expanded into the root. `toArray()` and DtoSerializer
without config continue to serialize `_extra` as an ordinary property.

For body, BodyRoot, multipart body, and query assembly, this applies to DtoInterface,
plain DTOs, lists, and public plain wrappers at any allowed depth. Plain DTOs preserve
JSON for their other public properties; no properties produces {}. The core replaces
only containers on the path to the receiver, without changing DTOs or calling
constructors. Header/path/file still do not serialize DTOs.

The query URL builder accepts only scalars and flat lists: excluding a receiver does
not make a DTO an acceptable query value. The structure is rejected before HTTP.
For such an API, declare explicit query fields in a separate request model.

Opaque transformations have explicit restrictions:

- A class combining a receiver with DateTimeInterface, JsonSerializable, Stringable,
  or `toArray()` without DtoInterface is rejected when its class description is resolved.
- A property Cast or type cast receiving a value with a visible receiver produces
  `SerializationException` **before the cast and HTTP** (`serialization_error`).
  This also applies to JsonCast on BodyRoot/Body/query.
- Traversal does not open JsonSerializable, Stringable, custom `toArray()`, DateTime,
  enums, or closures. If such an external wrapper hides a DTO with a receiver,
  its representation remains the SDK author's responsibility.
- To, DateTimeTo, Query, Body, BodyRoot, Header, Path, or File on a receiver produces
  `ConfigurationException` during compilation.

The prepared request, requestDebug, HTTP cache key, and log use the representation
with the receiver already removed. Create an explicit request model to send extra data.

[Input remainder and name collisions](../dto/extras.md) · [DX/wire](dto-output.md).
