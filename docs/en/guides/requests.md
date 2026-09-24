<!-- languages --> <a href="requests.md">English</a> · <a href="../../ru/guides/requests.md">Русский</a> <!-- /languages -->
# Declare an operation request <a id="section-1"></a>

A request holds the parameters of one operation. The client supplies shared configuration,
the resource creates a request bound to that client, and `send()` executes the operation.

## Start with a working example <a id="section-2"></a>

[GetRecordRequest](../../example/sdk/src/Resources/Records/Get/GetRecordRequest.php)
declares GET `/records/{id}`, a Path parameter, and `Returns` with unwrap.
[RecordsResource](../../example/sdk/src/Resources/Records/RecordsResource.php) creates
the request, and [run.php](../../example/sdk/run.php) sends it through a fake.

1. Map every input field to path, query, header, body, or file.
2. Set the method and endpoint, then the result DTO and its path inside the envelope.
3. Check the prepared HTTP request against a local fixture.
4. Add an invalid-input check and an API error response.

## Choose a declaration <a id="section-3"></a>

The [request reference](../reference/request/declaration.md) covers conventions,
body DTOs, BodyRoot, oneOf/discriminator, and runtime options. Attribute parameters
are in the [catalog](../reference/attributes/request.md); assembly order is in
[request parts](../reference/serialization/request-parts.md).

Use a DTO for a recurring payload and BodyRoot for a root JSON Patch list.
A query array and a JSON string in a single parameter use different encodings:
see [URI/query](../reference/serialization/uri-query.md).

## Check the result <a id="section-4"></a>

The request must send only declared data, perform no I/O for locally invalid input,
and return a DTO or an explainable error. Read results through
[ResultHandle/resolved](../reference/results/handles.md).

The full process of adding an operation has a [separate route](../start/add-operation.md).
