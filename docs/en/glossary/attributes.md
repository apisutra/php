<!-- languages --> <a href="attributes.md">English</a> · <a href="../../ru/glossary/attributes.md">Русский</a> <!-- /languages -->
# Attributes <a id="section-1"></a>

| Term | Meaning | Details |
| --- | --- | --- |
| <a id="attributeregistry"></a> AttributeRegistry | A service that registers attribute → handler mappings. | [Contract](../reference/attributes/README.md) |
| <a id="attributecontext"></a> AttributeContext | The context supplied to an attribute handler. | [Contract](../reference/attributes/README.md) |
| <a id="attributecontexttype"></a> AttributeContextType | Indicates whether an attribute handler applies to a request or a DTO. | [Contract](../reference/attributes/README.md) |
| <a id="attributehandlerinterface"></a> AttributeHandlerInterface | The interface for a custom attribute handler. | [Contract](../reference/attributes/README.md) |
| <a id="get-post-put-delete"></a> Get, Post, Put, Delete | HTTP method attributes. | [Contract](../reference/attributes/http.md) |
| <a id="returns"></a> Returns | An attribute that specifies the DTO class used to deserialize a response. | [Contract](../reference/attributes/response.md) |
| <a id="path-query-body-header-ignore"></a> Path, Query, Body, Header, Ignore | Attributes that map request properties to HTTP request parts. | [Contract](../reference/attributes/request.md) |
| <a id="validate-attribute"></a> Validate (attribute) | An attribute that validates request properties before sending. | [Contract](../reference/client/validation.md) |
| <a id="label-attribute"></a> Label (attribute) | An attribute that supplies a human-readable field name for validation messages. | [Contract](../reference/client/validation.md) |
| <a id="about-attribute"></a> About (attribute) | An attribute that describes the business meaning of a DTO field for documentation, analysis, and export tooling. | [Contract](../reference/attributes/hydration.md) |
| <a id="validationerror"></a> ValidationError | A value object for a validation error. | [Contract](../reference/client/validation.md) |
| <a id="validationmessages"></a> validationMessages() | A static method on the request class for custom validation messages. | [Contract](../reference/client/validation.md) |
| <a id="beforesend-afterresponse-beforehydrate-afterhydrate-attributes"></a> BeforeSend, AfterResponse, BeforeHydrate, AfterHydrate (attributes) | Attributes that attach reusable handler classes to lifecycle hooks. | [Contract](../reference/attributes/hooks.md) |
| <a id="queryarrayformat"></a> QueryArrayFormat | The way an array is encoded in a query: brackets, indices, commas, or repeated parameter names. | [Contract](../reference/serialization/uri-query.md) |
| <a id="serializenulls"></a> serializeNulls | A parameter in the client's request-level configuration. | [Contract](../reference/serialization/request-parts.md) |

[All terms](README.md).
