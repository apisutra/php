<!-- languages --> <a href="testing.md">English</a> · <a href="../../ru/glossary/testing.md">Русский</a> <!-- /languages -->
# Testing <a id="section-1"></a>

| Term | Meaning | Details |
| --- | --- | --- |
| <a id="transportinterface"></a> TransportInterface | A synchronous and promise-based PreparedRequest sending contract that the client receives explicitly. | [Contract](../reference/execution/transport.md) |
| <a id="httptransport"></a> HttpTransport | The production implementation of TransportInterface. | [Contract](../reference/execution/transport.md) |
| <a id="mocktransport"></a> MockTransport | The testing implementation of TransportInterface. | [Contract](../reference/testing/mocking.md) |
| <a id="mockclient"></a> MockClient | A facade for configuring tests. | [Contract](../reference/testing/mocking.md) |
| <a id="mockconfig"></a> MockConfig | Testing configuration. | [Contract](../reference/testing/mocking.md) |
| <a id="fake"></a> fake() | An AbstractClient method for registering mock responses. | [Contract](../reference/testing/mocking.md) |
| <a id="mockresponse"></a> MockResponse | A value object for a mock response. | [Contract](../reference/testing/mocking.md) |
| <a id="assertsent"></a> assertSent() | A method that verifies a request was sent in tests. | [Contract](../reference/testing/mocking.md) |
| <a id="assertnothingsent"></a> assertNothingSent() | A method that verifies no requests were sent. | [Contract](../reference/testing/mocking.md) |
| <a id="preventstrayrequests"></a> preventStrayRequests() | An AbstractClient method. | [Contract](../reference/testing/mocking.md) |
| <a id="record--playback"></a> record() / playback() | Methods for recording and playing back fixtures. | [Contract](../reference/testing/fixtures.md) |
| <a id="fixture"></a> Fixture | A base class for custom fixtures with redaction. | [Contract](../reference/testing/fixtures.md) |
| <a id="liveenvloader"></a> LiveEnvLoader | Loads an env file for live tests while preserving the priority of environment variables already set. | [Contract](../reference/testing/live.md) |
| <a id="livepolling"></a> LivePolling | Repeats a callback until the readiness criterion is met or the timeout expires. | [Contract](../reference/testing/live.md) |
| <a id="liveresultassertions"></a> LiveResultAssertions | Checks that ResolvedResult is successful and that its data has the expected class. | [Contract](../reference/testing/live.md) |
| <a id="request-pool"></a> Pool (request pool) | A stream of requests with a limit on active tasks; the promise API does not guarantee parallel HTTP. | [Contract](../reference/execution/batch-pool.md) |

[All terms](README.md).
