<!-- languages --> <a href="requests.md">English</a> · <a href="../../ru/glossary/requests.md">Русский</a> <!-- /languages -->
# Requests <a id="section-1"></a>

| Term | Meaning | Details |
| --- | --- | --- |
| <a id="httpmethod"></a> HttpMethod | The operation's HTTP method: GET, POST, PUT, PATCH, or DELETE. | [Contract](../reference/request/declaration.md) |
| <a id="requestinterface"></a> RequestInterface | The public SDK request contract implemented by AbstractRequest. | [Contract](../reference/request/declaration.md) |
| <a id="abstractrequest"></a> AbstractRequest | The base class for all requests. | [Contract](../reference/request/declaration.md) |
| <a id="body-dto"></a> Body DTO | A DTO can be passed in the body (the input contract). | [Contract](../reference/request/declaration.md) |
| <a id="abstractresource"></a> AbstractResource | The base class for an API resource. | [Contract](../reference/client/resources.md) |
| <a id="sdk-asynchronous-sendasync"></a> SDK asynchronous execution (sendAsync) | Concurrent SDK execution through promises; built-in Guzzle overlaps HTTP and SDK waits. | [Contract](../reference/execution/transport.md) |
| <a id="sendasync"></a> sendAsync() | Starts execution and returns `ResultPromiseInterface<ResultHandle>`; wait yields the completed result. Fire-and-forget is not supported. | [Contract](../reference/execution/transport.md) |
| <a id="deferred-result-polling"></a> Deferred result readiness (polling) | A scenario where the provider returns an intermediate state and final data becomes available later. | [Contract](../reference/execution/continuation-await.md) |
| <a id="promiseinterface"></a> PromiseInterface | The Guzzle promise contract used by the result API; the type itself does not determine transport concurrency. | [Contract](../reference/execution/transport.md) |
| <a id="compositerequestinterface"></a> CompositeRequestInterface | An interface for composite (virtual) requests. | [Contract](../reference/request/composition.md) |
| <a id="dependsonrequestinterface"></a> DependsOnRequestInterface | An interface for requests with dependencies. | [Contract](../reference/request/composition.md) |
| <a id="preparedrequest"></a> PreparedRequest | A prepared HTTP request with a URL, headers, a single body source, and send options. | [Contract](../reference/execution/transport.md) |
| <a id="resolveendpoint"></a> resolveEndpoint() | An AbstractRequest method for resolving the endpoint dynamically. | [Contract](../reference/serialization/uri-query.md) |
| <a id="resolvebaseurl"></a> resolveBaseUrl() | An AbstractRequest method for overriding the base URL. | [Contract](../reference/serialization/uri-query.md) |
| <a id="withbaseurl"></a> withBaseUrl() | Creates an execution with an overridden baseUrl; the destination isolation contract applies. | [Contract](../reference/serialization/uri-query.md) |

[All terms](README.md).
