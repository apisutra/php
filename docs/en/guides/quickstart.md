<!-- languages --> <a href="quickstart.md">English</a> · <a href="../../ru/guides/quickstart.md">Русский</a> <!-- /languages -->
# Your first request <a id="section-1"></a>

Run a request through the tutorial SDK in a few minutes: configuration → transport →
client → resource → request → DTO or error. Requires PHP 8.4+ and Composer.

## Run the published example <a id="section-2"></a>

In a consumer project:

```bash
composer require apisutra/php
php vendor/apisutra/php/docs/example/sdk/run.php
```

In an ApiSutra checkout:

```bash
composer install
php docs/example/sdk/run.php
```

Both commands execute [the same file](../../example/sdk/run.php). It uses local
fixtures and `MockTransport`: no API keys or network access are needed.
Result:

```json
{"id":7,"title":"Первая запись","createdAt":"2026-09-15T10:30:00+00:00","authorName":"Анна","description":null,"extra":{"future_flag":false},"failed":true,"status":404}
```

## How the example works <a id="section-3"></a>

1. [ClientConfigFactory](../../example/sdk/src/Config/ClientConfigFactory.php) sets
   `baseUrl` and connects DTO rules.
2. [DemoClient](../../example/sdk/src/DemoClient.php) receives configuration and transport
   through the `AbstractClient` constructor. The configuration created is actually used.
3. [RecordsResource](../../example/sdk/src/Resources/Records/RecordsResource.php)
   returns a request bound to the client.
4. [GetRecordRequest](../../example/sdk/src/Resources/Records/Get/GetRecordRequest.php)
   declares GET, a path parameter, `Returns` with `unwrap: 'data'`, and
   [retries for transient errors](../reference/execution/retry.md) through `Retry`.
5. [GetRecordResponseDto](../../example/sdk/src/Resources/Records/Get/GetRecordResponseDto.php)
   extends `AbstractResponseDto`: `From` takes `record_id` or the fallback `id`,
   reads the name from `author.name`, and `From` with `DateTimeFrom` converts `created_at`
   into `DateTimeImmutable`. `EmptyStringAsNull(blank: true)` replaces an empty or
   whitespace-only description with `null`.
   [Rules](../../example/sdk/src/Config/HydrationConfigFactory.php) enforce strict scalars
   and retain unknown fields in `_extra`; property mapping remains in attributes.
6. `dataOrFail()` returns the DTO or throws an exception. The second call uses
   `resolved()` and shows how to check an HTTP error without extracting data.

Sources are next to their explanations; copy them into your SDK and register your
namespace in Composer. `bootstrap.php` is needed only to autoload the tutorial namespace.

## Adapt it to your API <a id="section-4"></a>

Replace the base URL, request path, and DTO shape using a confirmed API response.
For real HTTP, pass a configured transport; it has its own dependencies and timeout
and redirect constraints. See [standalone setup](integration/standalone.md).

In Laravel, SDK configuration is supplied through an explicit client binding;
`app(DemoClient::class)` alone does not use a local `$config` variable.
The [complete registration guide](https://github.com/apisutra/laravel/blob/master/docs/en/guides/integration/laravel.md) uses the published service provider.

## Continue <a id="section-5"></a>

- [Create a complete SDK](../start/create-sdk.md) — API facts, design, and expanding coverage.
- [Add an operation](../start/add-operation.md) — the next request and its test.
- [Choose a DTO model](../start/describe-dto.md) — external rules or attributes.
- [Tutorial SDK sources](../examples/sdk.md) — file tree, execution, and expected result.
