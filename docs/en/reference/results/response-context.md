<!-- languages --> <a href="response-context.md">English</a> · <a href="../../../ru/reference/results/response-context.md">Русский</a> <!-- /languages -->
# Response context after failure <a id="section-1"></a>

After retries are exhausted, the last HTTP response goes through normal error mapping.
For example, 503 remains `service_unavailable`, including HTML or malformed JSON
responses. Status 400 uses `bad_request`; other unmapped 4xx statuses use `client_error`.
The response and its raw body are preserved. A string `message` from JSON becomes
the message; otherwise `HTTP <status>` is used. If the last HTTP attempt ends in a
network failure, the previous attempt's response is not substituted for the missing
current response.

For a request that first receives HTTP 500, distinguish the final cause from
response context. `ExecutionResult::response` and `RequestError::response` agree:

| What ends execution next | Error code / reason | Response |
| --- | --- | --- |
| Another HTTP error | Code mapped from that status | New response |
| Connection failure | `connection_failed` | null |
| Transport timeout, with total budget remaining | `timeout`, without `execution_deadline_exceeded` | null |
| Total deadline before retry or during HTTP | `timeout` / `execution_deadline_exceeded` | Previous 500 as context |
| Local quota or cooldown refusal | `rate_limited` / `local_rate_limit_exceeded` or `server_cooldown_active` | Previous 500 as context |
| Quota/cooldown backend failure | `execution_error` / `rate_limit_backend_error` or `cooldown_backend_error` | Previous 500 as context |

Use code, reason, stage, and exception to classify the final failure; a stored
HTTP status alone does not identify its cause. An auth dependency can also pass its
last response to the parent execution, so retained context is not necessarily the
last response of the original request. The exception chain preserves underlying
transport/backend causes where applicable.

[Error classification and delivery](errors.md) · [All result contracts](README.md).
