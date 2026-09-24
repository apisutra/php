<!-- languages --> <a href="CHANGELOG.md">English</a> · <a href="docs/ru/changelog.md">Русский</a> <!-- /languages -->
# Changelog <a id="section-1"></a>

## 0.1.0

Initial release of the framework-independent PHP 8.4+ SDK toolkit.

- Declarative clients and requests, validation, resources and extension hooks.
- DTO hydration and serialization: attributes, external rules, nested models, collections,
  custom hydrators and explicit handling of unknown fields.
- Synchronous calls and concurrent async execution with typed promises, cancellation,
  batch/pool and incremental consumption with bounded result storage.
- Page/offset/cursor pagination, lazy item iteration and concurrent collection of
  independent pages with a shared deadline.
- Authentication strategies and OAuth2 Client Credentials / Authorization Code with
  PKCE S256, automatic refresh and persistable token/authorization-attempt snapshots.
- Safe retries, timeouts, execution budgets, response caching, quotas and Retry-After
  cooldown; optional atomic Redis backends for shared quotas and cooldown.
- File uploads/downloads, streamed payloads, composite requests and continuation polling.
- Structured results and errors, correlated traces, debug snapshots, secret masking,
  execution observation and English/Russian messages.
- Mock transports, strict fake sessions, recording/playback, client/request/DTO generation,
  and runnable examples including one Records SDK for PHP and Laravel.

Laravel integration is provided by [apisutra/laravel](https://github.com/apisutra/laravel).
See the [capability map](README.md#section-5) and [documentation](docs/en/README.md).
